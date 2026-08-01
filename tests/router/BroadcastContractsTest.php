<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Dispar\BroadcastRepository;
use WAS\Dispar\BroadcastService;
use WAS\Meta\TokenVault;

class BroadcastContractsTest extends WAS_Router_TestCase {

    protected function set_up() {
        global $wpdb;
        TenantContext::set_tenant_id(1);
        $now = current_time('mysql', true);
        $wpdb->insert(TableNameResolver::get_table_name('tenants'), ['id'=>1,'name'=>'Agenda','slug'=>'agenda','status'=>'active','created_at'=>$now]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_accounts'), ['id'=>7,'tenant_id'=>1,'waba_id'=>'waba-dispar','status'=>'active','created_at'=>$now]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_phone_numbers'), ['id'=>10,'tenant_id'=>1,'whatsapp_account_id'=>7,'phone_number_id'=>'meta-phone-dispar','is_default'=>1,'status'=>'active','created_at'=>$now]);
        $wpdb->insert(TableNameResolver::get_table_name('meta_tokens'), ['id'=>20,'tenant_id'=>1,'whatsapp_account_id'=>7,'access_token_encrypted'=>TokenVault::encrypt('dispar-token'),'status'=>'active','created_at'=>$now]);
    }

    public function test_contract_fixture_is_portable_and_describes_the_dispar_behaviors() {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../contracts/broadcast-contracts.json'), true);
        $this->assert_same('disparo', $fixture['module']);
        $this->assert_count(5, $fixture['cases']);
        $this->assert_same('create_queue_with_named_variables', $fixture['cases'][0]['id']);
        $this->assert_same('pause_prevents_processing', $fixture['cases'][3]['id']);
    }

    public function test_create_broadcast_normalizes_phones_and_persists_named_variable_rows() {
        global $wpdb;
        $this->insert_template(40, 'APPROVED', ['1'=>'nome','2'=>'compra']);
        $result = (new BroadcastService())->create([
            'template_id'=>40, 'phone_number_id'=>'meta-phone-dispar', 'interval_seconds'=>30, 'cost_per_message'=>0.075,
            'rows'=>[
                ['phone'=>'+55 (31) 99999-1111','variables'=>['nome'=>'Ana','compra'=>'Livro']],
                ['phone'=>'55 31 98888-2222','variables'=>['nome'=>'Bia','compra'=>'Curso']],
            ],
        ]);
        $broadcasts = $wpdb->tables[TableNameResolver::getBroadcastsTable()];
        $items = $wpdb->tables[TableNameResolver::getBroadcastItemsTable()];

        $this->assert_true($result['success']);
        $this->assert_same(2, $result['total_count']);
        $this->assert_same('draft', $broadcasts[0]['status']);
        $this->assert_same(30, (int)$broadcasts[0]['interval_seconds']);
        $this->assert_same('5531999991111', $items[0]['phone']);
        $this->assert_same(['nome'=>'Ana','compra'=>'Livro'], json_decode($items[0]['variables_json'], true));
        $this->assert_same('pending', $items[1]['status']);
    }

    public function test_create_rejects_unapproved_template_invalid_phone_and_missing_variable() {
        $this->insert_template(41, 'PENDING', ['1'=>'nome']);
        $pending = (new BroadcastService())->create(['template_id'=>41,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0,'rows'=>[['phone'=>'5531999991111','variables'=>['nome'=>'Ana']]]]);
        $this->assert_false($pending['success']);
        $this->assert_true(str_contains($pending['error'], 'aprovado'));

        $this->insert_template(42, 'APPROVED', ['1'=>'nome','2'=>'compra']);
        $missing = (new BroadcastService())->create(['template_id'=>42,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0,'rows'=>[['phone'=>'5531999991111','variables'=>['nome'=>'Ana']]]]);
        $this->assert_false($missing['success']);
        $this->assert_true(str_contains($missing['error'], 'compra'));

        $invalid = (new BroadcastService())->create(['template_id'=>42,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0,'rows'=>[['phone'=>'---','variables'=>['nome'=>'Ana','compra'=>'Livro']]]]);
        $this->assert_false($invalid['success']);
        $this->assert_true(str_contains($invalid['error'], 'telefone'));
    }

    public function test_pause_keeps_pending_items_and_process_next_does_not_send() {
        $this->insert_template(43, 'APPROVED', []);
        $created = (new BroadcastService())->create(['template_id'=>43,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0,'rows'=>[['phone'=>'5531999991111','variables'=>[]]]]);
        $repo = new BroadcastRepository();
        $repo->update($created['id'], ['status'=>'paused']);
        $result = (new BroadcastService())->process_next($created['id']);
        $item = $repo->items($created['id'])[0];

        $this->assert_false($result['success']);
        $this->assert_true($result['paused']);
        $this->assert_same('pending', $item->status);
        $this->assert_count(0, $GLOBALS['was_test_http_posts']);
    }

    public function test_process_next_sends_template_and_marks_item_sent() {
        $this->insert_template(44, 'APPROVED', ['1'=>'nome']);
        $created = (new BroadcastService())->create(['template_id'=>44,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0.05,'rows'=>[['phone'=>'5531999991111','variables'=>['nome'=>'Ana']]]]);
        $repo = new BroadcastRepository();
        $repo->update($created['id'], ['status'=>'running']);
        $GLOBALS['was_test_http_response'] = ['code'=>200,'body'=>['messages'=>[['id'=>'wamid.dispar.1']]]];

        $result = (new BroadcastService())->process_next($created['id']);
        $item = $repo->items($created['id'])[0];
        $payload = json_decode($GLOBALS['was_test_http_posts'][0]['args']['body'], true);

        $this->assert_true($result['success']);
        $this->assert_same('sent', $result['status']);
        $this->assert_same('sent', $item->status);
        $this->assert_same('wamid.dispar.1', $item->wa_message_id);
        $this->assert_same('5531999991111', $payload['to']);
        $this->assert_same('Ana', $payload['template']['components'][0]['parameters'][0]['text']);
    }

    public function test_process_next_records_meta_recipient_error_as_failed_item() {
        $this->insert_template(45, 'APPROVED', []);
        $created = (new BroadcastService())->create(['template_id'=>45,'phone_number_id'=>'meta-phone-dispar','interval_seconds'=>60,'cost_per_message'=>0,'rows'=>[['phone'=>'5531999991111','variables'=>[]]]]);
        (new BroadcastRepository())->update($created['id'], ['status'=>'running']);
        $GLOBALS['was_test_http_response'] = ['code'=>400,'body'=>['error'=>['message'=>'Recipient does not exist','code'=>131026]]];

        $result = (new BroadcastService())->process_next($created['id']);
        $item = (new BroadcastRepository())->items($created['id'])[0];

        $this->assert_true($result['success']);
        $this->assert_same('failed', $result['status']);
        $this->assert_same('failed', $item->status);
        $this->assert_true(str_contains($item->error_message, 'Recipient does not exist'));
        $this->assert_same('131026', $item->error_code);
    }

    private function insert_template($id, $status, array $variable_map) {
        global $wpdb;
        $wpdb->insert(TableNameResolver::get_table_name('message_templates'), [
            'id'=>$id,'tenant_id'=>1,'whatsapp_account_id'=>7,'name'=>'dispar_template_'.$id,'language'=>'pt_BR',
            'category'=>'UTILITY','body_text'=>empty($variable_map)?'Mensagem pronta':'Olá {{nome}}','status'=>$status,
            'variable_map'=>wp_json_encode($variable_map),'buttons_json'=>'[]','header_type'=>'NONE','header_text'=>'','footer_text'=>'','deleted_at'=>null,
        ]);
    }
}

<?php
namespace WAS\REST;

use WAS\Dispar\BroadcastService;
use WAS\Dispar\BroadcastRepository;
use WAS\Templates\TemplateRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) exit;

class BroadcastApiController {
    private function response($data, $status=200) { return new \WP_REST_Response($data, $status); }
    private function context(\WP_REST_Request $r) { $tenant=(int)$r->get_param('tenant_id'); if($tenant && current_user_can('was_view_master_dashboard')) TenantContext::set_runtime_tenant_id($tenant); }
    public function templates(\WP_REST_Request $r) { $this->context($r); return $this->response((new TemplateRepository())->list_templates(200, 0, $r->get_param('phone_number_id'))); }
    public function list(\WP_REST_Request $r) { $this->context($r); return $this->response((new BroadcastRepository())->list()); }
    public function create(\WP_REST_Request $r) { $this->context($r); $result=(new BroadcastService())->create($r->get_json_params() ?: []); return $this->response($result, !empty($result['success']) ? 201 : 400); }
    public function start(\WP_REST_Request $r) { $this->context($r); $repo=new BroadcastRepository(); $b=$repo->find($r['id']); if(!$b) return $this->response(['message'=>'Disparo não encontrado.'],404); $repo->update($b->id,['status'=>'running','started_at'=>$b->started_at ?: current_time('mysql',true)]); return $this->response(['success'=>true]); }
    public function pause(\WP_REST_Request $r) { $this->context($r); $repo=new BroadcastRepository(); $b=$repo->find($r['id']); if(!$b) return $this->response(['message'=>'Disparo não encontrado.'],404); $repo->update($b->id,['status'=>'paused']); return $this->response(['success'=>true]); }
    public function process(\WP_REST_Request $r) { $this->context($r); return $this->response((new BroadcastService())->process_next((int)$r['id'])); }
    public function status(\WP_REST_Request $r) { $this->context($r); $data=(new BroadcastService())->status((int)$r['id']); return $data ? $this->response($data) : $this->response(['message'=>'Disparo não encontrado.'],404); }
}

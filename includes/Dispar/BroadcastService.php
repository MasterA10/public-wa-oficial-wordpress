<?php
namespace WAS\Dispar;

use WAS\Templates\TemplateRepository;
use WAS\Templates\TemplateSendService;

if (!defined('ABSPATH')) exit;

class BroadcastService {
    private $repo;
    public function __construct() { $this->repo = new BroadcastRepository(); }

    public function create(array $data) {
        $template = (new TemplateRepository())->get_by_id((int)$data['template_id']);
        if (!$template) return ['success'=>false,'error'=>'Template não encontrado.'];
        if (strtolower((string)$template->status) !== 'approved') return ['success'=>false,'error'=>'O template precisa estar aprovado pela Meta.'];
        $rows = $data['rows'] ?? [];
        if (!is_array($rows) || !$rows) return ['success'=>false,'error'=>'A planilha não possui linhas válidas.'];
        $map = json_decode((string)$template->variable_map, true) ?: [];
        $allowed = array_values($map);
        foreach ($rows as &$row) {
            $row['phone'] = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
            if (strlen($row['phone']) < 8) return ['success'=>false,'error'=>'Há um telefone inválido na planilha: '.($row['phone'] ?: '(vazio)')];
            $vars = is_array($row['variables'] ?? null) ? $row['variables'] : [];
            foreach ($allowed as $variable) if (!array_key_exists($variable, $vars) || $vars[$variable] === '') return ['success'=>false,'error'=>"A variável {$variable} não foi preenchida em todas as linhas."];
            $row['variables'] = array_intersect_key($vars, array_flip($allowed));
        }
        unset($row);
        $id = $this->repo->create(['template_id'=>$template->id,'name'=>$template->name,'category'=>$template->category,'interval_seconds'=>(int)$data['interval_seconds'],'cost_per_message'=>(float)$data['cost_per_message']], $rows);
        return ['success'=>true,'id'=>$id,'total_count'=>count($rows)];
    }

    public function process_next($id) {
        $broadcast = $this->repo->find($id);
        if (!$broadcast || $broadcast->status !== 'running') return ['success'=>false,'paused'=>true,'message'=>'Disparo pausado ou encerrado.'];
        $item = $this->repo->next_item($id);
        if (!$item) { $this->repo->update($id,['status'=>'completed','finished_at'=>current_time('mysql', true)]); return ['success'=>true,'completed'=>true]; }
        $result = (new TemplateSendService())->send(null, (int)$broadcast->template_id, json_decode($item->variables_json, true) ?: [], [], $item->phone);
        if (!empty($result['success'])) {
            $this->repo->update_item($item->id,['status'=>'sent','wa_message_id'=>$result['wa_message_id'] ?? null,'sent_at'=>current_time('mysql', true)]);
            $this->repo->update($id,['sent_count'=>(int)($broadcast->sent_count ?? 0)+1]);
            return ['success'=>true,'item_id'=>$item->id,'status'=>'sent','phone'=>$item->phone];
        }
        $error = (string)($result['error'] ?? 'A Meta recusou o envio.');
        $this->repo->update_item($item->id,['status'=>'failed','error_code'=>(string)($result['code'] ?? ''),'error_message'=>$error]);
        $this->repo->update($id,['failed_count'=>(int)($broadcast->failed_count ?? 0)+1,'last_error'=>$error]);
        return ['success'=>true,'item_id'=>$item->id,'status'=>'failed','phone'=>$item->phone,'error'=>$error];
    }

    public function status($id) {
        $b = $this->repo->find($id); if (!$b) return null;
        $counts = $this->repo->counts($id); $summary = ['pending'=>0,'sending'=>0,'sent'=>0,'failed'=>0];
        foreach ($counts as $c) $summary[$c->status] = (int)$c->total;
        $b->summary = $summary; $b->items = $this->repo->items($id); return $b;
    }
}

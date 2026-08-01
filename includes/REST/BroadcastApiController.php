<?php
namespace WAS\REST;

use WAS\Dispar\BroadcastService;
use WAS\Dispar\BroadcastRepository;
use WAS\Templates\TemplateRepository;

if (!defined('ABSPATH')) exit;

class BroadcastApiController {
    private function response($data, $status=200) { return new \WP_REST_Response($data, $status); }
    public function templates() { return $this->response((new TemplateRepository())->list_templates(200)); }
    public function list() { return $this->response((new BroadcastRepository())->list()); }
    public function create(\WP_REST_Request $r) { $result=(new BroadcastService())->create($r->get_json_params() ?: []); return $this->response($result, !empty($result['success']) ? 201 : 400); }
    public function start(\WP_REST_Request $r) { $repo=new BroadcastRepository(); $b=$repo->find($r['id']); if(!$b) return $this->response(['message'=>'Disparo não encontrado.'],404); $repo->update($b->id,['status'=>'running','started_at'=>$b->started_at ?: current_time('mysql',true)]); return $this->response(['success'=>true]); }
    public function pause(\WP_REST_Request $r) { $repo=new BroadcastRepository(); $b=$repo->find($r['id']); if(!$b) return $this->response(['message'=>'Disparo não encontrado.'],404); $repo->update($b->id,['status'=>'paused']); return $this->response(['success'=>true]); }
    public function process(\WP_REST_Request $r) { return $this->response((new BroadcastService())->process_next((int)$r['id'])); }
    public function status(\WP_REST_Request $r) { $data=(new BroadcastService())->status((int)$r['id']); return $data ? $this->response($data) : $this->response(['message'=>'Disparo não encontrado.'],404); }
}

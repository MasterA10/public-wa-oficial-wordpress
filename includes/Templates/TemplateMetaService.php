<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\AccountService;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateMetaService {
    private $api_client;

    public function __construct() {
        $this->api_client = new MetaApiClient();
    }

    public function list(string $wabaId, string $token): array {
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Listando templates na Meta.', ['waba_id' => $wabaId]);
        $result = $this->api_client->get(
            'templates.list',
            ['waba_id' => $wabaId],
            ['fields' => 'id,name,status,category,language,components,rejected_reason'],
            $token
        );
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: list() concluído.', [
            'waba_id' => $wabaId,
            'count'   => count($result['data'] ?? []),
            'success' => isset($result['data']),
        ]);
        return $result;
    }

    public function get(string $templateId, string $token): array {
        return $this->api_client->get(
            'templates.get',
            ['template_id' => $templateId],
            ['fields' => 'id,name,status,category,language,components,rejected_reason'],
            $token
        );
    }

    public function create(string $wabaId, array $metaPayload, string $token): array {
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Criando template na Meta.', [
            'waba_id'      => $wabaId,
            'payload_name' => $metaPayload['name'] ?? null,
            'category'     => $metaPayload['category'] ?? null,
            'language'     => $metaPayload['language'] ?? null,
            'components'   => count($metaPayload['components'] ?? []),
        ]);

        $result = $this->api_client->postJson(
            'templates.create',
            ['waba_id' => $wabaId],
            $metaPayload,
            $token
        );

        if ($result['success'] ?? false) {
            \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Template criado na Meta com sucesso.', [
                'meta_id' => $result['id'] ?? null,
                'status'  => $result['status'] ?? null,
            ]);
        } else {
            \WAS\Core\SystemLogger::logError('TemplateMetaService: Meta REJEITOU criação de template.', [
                'waba_id'        => $wabaId,
                'error'          => $result['error'] ?? 'N/A',
                'error_code'     => $result['code'] ?? null,
                'error_subcode'  => $result['error_subcode'] ?? null,
                'full_response'  => $result,
                'sent_payload'   => $metaPayload,
            ]);
        }

        return $result;
    }

    public function update(string $templateId, array $metaPayload, string $token): array {
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Atualizando template na Meta.', [
            'meta_template_id' => $templateId,
            'payload_name'     => $metaPayload['name'] ?? null,
        ]);

        $result = $this->api_client->postJson(
            'templates.update',
            ['template_id' => $templateId],
            $metaPayload,
            $token
        );

        if (!($result['success'] ?? false)) {
            \WAS\Core\SystemLogger::logError('TemplateMetaService: Meta REJEITOU atualização de template.', [
                'meta_template_id' => $templateId,
                'error'            => $result['error'] ?? 'N/A',
                'full_response'    => $result,
                'sent_payload'     => $metaPayload,
            ]);
        } else {
            \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Template atualizado na Meta.', [
                'meta_template_id' => $templateId,
            ]);
        }

        return $result;
    }

    public function deleteByName(string $wabaId, string $name, string $token): array {
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Deletando template por nome.', [
            'waba_id' => $wabaId,
            'name'    => $name,
        ]);
        $result = $this->api_client->delete(
            'templates.delete',
            ['waba_id' => $wabaId],
            ['name' => $name],
            $token
        );
        if (!($result['success'] ?? false)) {
            \WAS\Core\SystemLogger::logError('TemplateMetaService: Falha ao deletar por nome.', [
                'waba_id' => $wabaId, 'name' => $name, 'error' => $result['error'] ?? 'N/A',
            ]);
        }
        return $result;
    }

    public function deleteById(string $wabaId, string $name, string $templateId, string $token): array {
        \WAS\Core\SystemLogger::logInfo('TemplateMetaService: Deletando template por ID e Nome.', [
            'waba_id' => $wabaId,
            'name'    => $name,
            'hsm_id'  => $templateId
        ]);
        $result = $this->api_client->delete(
            'templates.delete',
            ['waba_id' => $wabaId],
            ['name' => $name, 'hsm_id' => $templateId],
            $token
        );
        if (!($result['success'] ?? false)) {
            \WAS\Core\SystemLogger::logError('TemplateMetaService: Falha ao deletar por ID.', [
                'meta_template_id' => $templateId, 'error' => $result['error'] ?? 'N/A',
            ]);
        }
        return $result;
    }
}

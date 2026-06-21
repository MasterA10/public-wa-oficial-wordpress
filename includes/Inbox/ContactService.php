<?php
namespace WAS\Inbox;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço responsável por gerenciar a lógica de contatos, incluindo nomes e fontes de identidade.
 */
class ContactService {
    private $repository;

    public function __construct() {
        $this->repository = new ContactRepository();
    }

    /**
     * Realiza o upsert do nome do contato vindo do webhook da Meta.
     */
    public function upsertNameFromWebhook(int $tenantId, array $contactPayload): void {
        $waId = $contactPayload['wa_id'] ?? null;
        $profileName = $contactPayload['profile']['name'] ?? null;

        if (!$waId) {
            return;
        }

        $profileName = $this->sanitizeProfileName($profileName);
        $contact = $this->repository->find_by_wa_id($tenantId, $waId);

        if (!$contact) {
            // Criar novo contato com o nome do perfil
            $this->repository->create([
                'tenant_id'            => $tenantId,
                'wa_id'                => $waId,
                'phone'                => $waId,
                'profile_name'         => $profileName,
                'display_name'         => $profileName ?: $waId,
                'name_source'          => $profileName ? 'whatsapp_profile' : 'phone',
                'name_locked'          => 0,
                'last_profile_name_at' => current_time('mysql', true),
                'created_at'           => current_time('mysql', true),
                'updated_at'           => current_time('mysql', true),
            ]);
            return;
        }

        // Se o contato existe, atualizamos o profile_name sempre
        $data = [
            'profile_name'         => $profileName,
            'last_profile_name_at' => current_time('mysql', true),
            'updated_at'           => current_time('mysql', true),
        ];

        // Só atualiza display_name se não estiver bloqueado e se o nome atual estiver vazio ou for o wa_id
        if (!$contact->name_locked && $profileName) {
            $isDefaultName = empty($contact->display_name) || $contact->display_name === $contact->wa_id;
            
            if ($isDefaultName && $this->isUsefulName($profileName)) {
                $data['display_name'] = $profileName;
                $data['name_source'] = 'whatsapp_profile';
            }
        }

        $this->repository->update($contact->id, $data);
    }

    /**
     * Sanitiza o nome do perfil para caber no banco.
     */
    private function sanitizeProfileName(?string $name): ?string {
        if (!$name) return null;
        $name = trim($name);
        if ($name === '') return null;
        return mb_substr($name, 0, 190);
    }

    /**
     * Verifica se o nome é útil (contém letras ou números e não é apenas um caractere especial).
     */
    private function isUsefulName(?string $name): bool {
        if (!$name) return false;
        $name = trim($name);
        if (mb_strlen($name) < 2) return false;
        
        // Verifica se contém pelo menos uma letra ou número
        if (preg_match('/^[^\p{L}\p{N}]+$/u', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Atualiza o nome de exibição manualmente pelo atendente.
     */
    public function updateDisplayNameManual(int $contactId, string $newName): bool {
        return $this->repository->update($contactId, [
            'display_name' => $this->sanitizeProfileName($newName),
            'name_source'  => 'manual',
            'name_locked'  => 1,
            'updated_at'   => current_time('mysql', true)
        ]);
    }
}

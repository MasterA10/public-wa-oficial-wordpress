<?php

namespace WAS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seeder de Dados Demo para App Review
 */
class DemoSeeder {
    
    public static function seed() {
        global $wpdb;
        $tenants_table = TableNameResolver::getTenantsTable();
        
        // 1. Criar Tenant Demo se não existir
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tenants_table WHERE slug = %s", 'demo-review'));
        
        if (!$existing) {
            $wpdb->insert($tenants_table, [
                'name'       => 'Meta Review Demo',
                'slug'       => 'demo-review',
                'status'     => 'active',
                'plan'       => 'pro',
                'created_at' => current_time('mysql', 1)
            ]);
            $tenant_id = $wpdb->insert_id;
        } else {
            $tenant_id = $existing;
        }

        // 2. Criar Usuário Demo (WAS-120)
        self::createDemoUser($tenant_id);
    }

    private static function createDemoUser($tenant_id) {
        $username = 'meta_reviewer';
        $email = 'review@example.com';
        
        if (!username_exists($username)) {
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);
            
            // Vincular ao tenant (Usando tabela raw pois repositórios dependem de Dev 01)
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'was_tenant_users', [
                'tenant_id' => $tenant_id,
                'user_id'   => $user_id,
                'role'      => 'agent',
                'status'    => 'active'
            ]);

            // Guardar senha para o checklist (em ambiente real seria passado via canal seguro)
            update_option('was_demo_reviewer_password', $password);
        }
    }
}

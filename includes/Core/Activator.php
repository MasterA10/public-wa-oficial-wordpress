<?php

namespace WAS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsável pela ativação do plugin
 */
class Activator {
    public static function activate() {
        // 1. Instalar tabelas
        Installer::install();
        
        // 2. Gerar páginas legais para App Review
        \WAS\Compliance\LegalPagesGenerator::generateAll();

        // 3. Registrar versão do banco
        update_option('was_db_version', WAS_VERSION);

        // 4. Flush rewrite rules for /app/ routes
        flush_rewrite_rules();
    }
}

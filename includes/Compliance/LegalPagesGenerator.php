<?php

namespace WAS\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerador de Páginas Legais
 */
class LegalPagesGenerator {
    private static $pages = [
        'privacy-policy'         => 'Política de Privacidade',
        'terms-of-service'      => 'Termos de Serviço',
        'data-deletion'          => 'Exclusão de Dados',
        'data-deletion-status'   => 'Status da Exclusão',
        'acceptable-use-policy' => 'Política de Uso Aceitável',
        'security'               => 'Segurança',
        'contact'                => 'Contato',
        'support'                => 'Suporte',
        'docs'                   => 'Documentação'
    ];

    /**
     * Inicializa os hooks do gerador de páginas
     */
    public static function boot() {
        add_action('template_redirect', [self::class, 'handle_template_redirect']);
    }

    /**
     * Intercepta a renderização das páginas legais para usar o template do plugin sem o tema
     */
    public static function handle_template_redirect() {
        foreach (self::$pages as $slug => $title) {
            if (is_page($slug)) {
                $file_path = WAS_PLUGIN_DIR . "templates/legal/{$slug}.php";
                if (file_exists($file_path)) {
                    include $file_path;
                    exit;
                }
            }
        }
    }

    /**
     * Cria as páginas se não existirem
     */
    public static function generateAll() {
        foreach (self::$pages as $slug => $title) {
            self::createPage($slug, $title);
        }
    }

    private static function createPage($slug, $title) {
        $existing = get_page_by_path($slug);

        if (!$existing) {
            $content = self::getPlaceholderContent($slug);
            
            wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ]);
        }
    }

    private static function getPlaceholderContent($slug) {
        $file_path = WAS_PLUGIN_DIR . "templates/legal/{$slug}.php";
        
        if (file_exists($file_path)) {
            ob_start();
            include $file_path;
            return ob_get_clean();
        }

        return "Conteúdo para a página $slug. Esta página é necessária para o App Review da Meta.";
    }
}

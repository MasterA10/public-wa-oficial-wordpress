<?php

namespace WAS\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerador de Páginas Legais
 */
class LegalPagesGenerator {
	private const SETTINGS_KEY = 'was_legal_company_data';

	private static $defaults = [
		'company_name'       => 'Plataforma',
		'legal_name'         => 'Equipe do Produto',
		'cnpj'               => '',
		'address'            => '',
		'city_state'         => '',
		'email'              => '',
		'phone'              => '',
		'website'            => '',
		'contact_url'        => '',
		'dpo_name'           => '',
		'dpo_email'          => '',
	];
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

	public static function get_company_data() {
		$saved = get_option( self::SETTINGS_KEY, [] );
		return array_merge( self::$defaults, is_array( $saved ) ? $saved : [] );
	}

	public static function save_company_data( array $data ) {
		$clean = [];
		foreach ( self::$defaults as $key => $default ) {
			$clean[ $key ] = sanitize_text_field( $data[ $key ] ?? '' );
		}
		return update_option( self::SETTINGS_KEY, $clean );
	}

	public static function get_placeholder( $key, $fallback = '' ) {
		$data = self::get_company_data();
		$value = trim( (string) ( $data[ $key ] ?? '' ) );
		return $value !== '' ? $value : ( $fallback !== '' ? $fallback : ( self::$defaults[ $key ] ?? '' ) );
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

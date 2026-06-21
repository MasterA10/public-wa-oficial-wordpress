<?php
/**
 * LoginController class.
 *
 * @package WAS\Auth
 */

namespace WAS\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles SaaS login and authentication.
 */
class LoginController {

	/**
	 * Render the custom login page.
	 */
	public function render_login_page() {
		if ( is_user_logged_in() ) {
			wp_redirect( home_url( '/app/dashboard' ) );
			exit;
		}

		$errors = [];
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_login_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['was_login_nonce'], 'was_login_action' ) ) {
				$errors[] = 'Falha na verificação de segurança.';
			} else {
				$credentials = [
					'user_login'    => sanitize_text_field( $_POST['log'] ),
					'user_password' => $_POST['pwd'],
					'remember'      => isset( $_POST['rememberme'] ),
				];

				$user = wp_signon( $credentials, false );

				if ( is_wp_error( $user ) ) {
					$errors[] = 'Usuário ou senha inválidos.';
				} else {
					// Registrar auditoria de login
					\WAS\Compliance\AuditLogger::log('user_login', 'user', $user->ID, [
						'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
						'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
					]);
					
					wp_redirect( home_url( '/app/dashboard?onboarding=1' ) );
					exit;

				}
			}
		}

		$template = WAS_PLUGIN_DIR . 'templates/login.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			wp_die( 'Login template missing.' );
		}
	}
}

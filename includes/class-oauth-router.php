<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contrôleur OAuth : génère les URLs d'autorisation, gère les callbacks,
 * échange les codes contre des tokens et les confie au Token Store.
 */
class WeNetwork_Social_OAuth_Router {

	const STATE_TRANSIENT_PREFIX = 'wenetwork_social_state_';
	const QUERY_VAR              = 'wenetwork_social_callback';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Connect/disconnect sont déclenchés depuis l'admin WP : admin-post.php classique suffit,
		// ces URLs ne sont jamais communiquées à Meta/LinkedIn.
		add_action( 'admin_post_wenetwork_ig_connect', array( $this, 'handle_instagram_connect' ) );
		add_action( 'admin_post_wenetwork_ig_disconnect', array( $this, 'handle_instagram_disconnect' ) );

		add_action( 'admin_post_wenetwork_li_connect', array( $this, 'handle_linkedin_connect' ) );
		add_action( 'admin_post_wenetwork_li_disconnect', array( $this, 'handle_linkedin_disconnect' ) );

		add_action( 'admin_post_wenetwork_ig_sync_now', array( $this, 'handle_instagram_sync_now' ) );

		// Les callbacks OAuth, eux, doivent être des chemins "propres" sans query string
		// (voir class-api-instagram.php::redirect_uri() pour le pourquoi) -> rewrite rule dédiée.
		add_action( 'init', array( $this, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_callback' ) );
	}

	public static function activate_rewrite() {
		self::instance()->register_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function deactivate_rewrite() {
		flush_rewrite_rules();
	}

	public function register_rewrite_rule() {
		add_rewrite_rule(
			'^wenetwork-social/callback/(instagram|linkedin)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function maybe_handle_callback() {
		$network = get_query_var( self::QUERY_VAR );

		if ( 'instagram' === $network ) {
			$this->handle_instagram_callback();
		} elseif ( 'linkedin' === $network ) {
			$this->handle_linkedin_callback();
		}
	}

	/* ---------------------------------------------------------------- */
	/* Instagram                                                         */
	/* ---------------------------------------------------------------- */

	public function handle_instagram_connect() {
		$this->require_capability();

		if ( ! defined( 'WENETWORK_META_APP_ID' ) || ! defined( 'WENETWORK_META_APP_SECRET' ) ) {
			wp_die( esc_html__( 'WENETWORK_META_APP_ID / WENETWORK_META_APP_SECRET ne sont pas définis dans wp-config.php.', 'wenetwork-social' ) );
		}

		$state = $this->create_state( 'instagram' );

		wp_redirect( WeNetwork_Social_Api_Instagram::get_authorize_url( $state ) );
		exit;
	}

	public function handle_instagram_callback() {
		$this->require_capability();

		$redirect_back = $this->settings_url( 'instagram' );

		if ( ! empty( $_GET['error'] ) ) {
			$this->redirect_with_notice( $redirect_back, 'error', sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ) ) );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( ! $this->verify_state( 'instagram', $state ) || empty( $code ) ) {
			$this->redirect_with_notice( $redirect_back, 'error', __( 'Requête OAuth invalide ou expirée, merci de réessayer.', 'wenetwork-social' ) );
		}

		$result = WeNetwork_Social_Api_Instagram::exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			wenetwork_social_log( 'oauth-instagram', $result->get_error_message() );
			$this->redirect_with_notice( $redirect_back, 'error', __( 'Connexion Instagram impossible, merci de réessayer.', 'wenetwork-social' ) );
		}

		WeNetwork_Social_Token_Store::set( 'instagram', $result );

		// Premier fetch immédiat pour ne pas laisser le client sans posts en attendant le cron.
		do_action( 'wenetwork_social_sync_instagram' );

		$this->redirect_with_notice( $redirect_back, 'success', __( 'Compte Instagram connecté avec succès.', 'wenetwork-social' ) );
	}

	public function handle_instagram_sync_now() {
		$this->require_capability();

		$result = WeNetwork_Social_Cron_Sync::instance()->fetch_instagram_media();

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $this->settings_url( 'instagram' ), 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $this->settings_url( 'instagram' ), 'success', __( 'Synchronisation réussie, les posts ont été mis à jour.', 'wenetwork-social' ) );
	}

	public function handle_instagram_disconnect() {
		$this->require_capability();

		WeNetwork_Social_Token_Store::delete( 'instagram' );
		delete_transient( 'wenetwork_social_media_instagram' );
		delete_transient( 'wenetwork_social_profile_instagram' );
		delete_option( 'wenetwork_social_instagram_needs_reconnect' );
		WeNetwork_Social_Media_Cache::delete_network( 'instagram' );

		$this->redirect_with_notice( $this->settings_url( 'instagram' ), 'success', __( 'Compte Instagram déconnecté.', 'wenetwork-social' ) );
	}

	/* ---------------------------------------------------------------- */
	/* LinkedIn                                                          */
	/* ---------------------------------------------------------------- */

	public function handle_linkedin_connect() {
		$this->require_capability();

		if ( ! defined( 'WENETWORK_LINKEDIN_CLIENT_ID' ) || ! defined( 'WENETWORK_LINKEDIN_CLIENT_SECRET' ) ) {
			wp_die( esc_html__( 'WENETWORK_LINKEDIN_CLIENT_ID / WENETWORK_LINKEDIN_CLIENT_SECRET ne sont pas définis dans wp-config.php.', 'wenetwork-social' ) );
		}

		$state = $this->create_state( 'linkedin' );

		wp_redirect( WeNetwork_Social_Api_Linkedin::get_authorize_url( $state ) );
		exit;
	}

	public function handle_linkedin_callback() {
		$this->require_capability();

		$redirect_back = $this->settings_url( 'linkedin' );

		if ( ! empty( $_GET['error'] ) ) {
			$this->redirect_with_notice( $redirect_back, 'error', sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ) ) );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( ! $this->verify_state( 'linkedin', $state ) || empty( $code ) ) {
			$this->redirect_with_notice( $redirect_back, 'error', __( 'Requête OAuth invalide ou expirée, merci de réessayer.', 'wenetwork-social' ) );
		}

		$result = WeNetwork_Social_Api_Linkedin::exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			wenetwork_social_log( 'oauth-linkedin', $result->get_error_message() );
			$this->redirect_with_notice( $redirect_back, 'error', __( 'Connexion LinkedIn impossible, merci de réessayer.', 'wenetwork-social' ) );
		}

		WeNetwork_Social_Token_Store::set( 'linkedin', $result );

		$this->redirect_with_notice( $redirect_back, 'success', __( 'Compte LinkedIn connecté avec succès.', 'wenetwork-social' ) );
	}

	public function handle_linkedin_disconnect() {
		$this->require_capability();

		WeNetwork_Social_Token_Store::delete( 'linkedin' );
		delete_transient( 'wenetwork_social_media_linkedin' );

		$this->redirect_with_notice( $this->settings_url( 'linkedin' ), 'success', __( 'Compte LinkedIn déconnecté.', 'wenetwork-social' ) );
	}

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */

	private function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'wenetwork-social' ), 403 );
		}
	}

	private function create_state( $network ) {
		$state = wp_generate_password( 32, false );

		set_transient( self::STATE_TRANSIENT_PREFIX . $network, $state, 10 * MINUTE_IN_SECONDS );

		return $state;
	}

	private function verify_state( $network, $state ) {
		$expected = get_transient( self::STATE_TRANSIENT_PREFIX . $network );

		delete_transient( self::STATE_TRANSIENT_PREFIX . $network );

		return $expected && $state && hash_equals( $expected, $state );
	}

	private function settings_url( $tab ) {
		return add_query_arg(
			array(
				'page' => 'wenetwork-social',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	private function redirect_with_notice( $base_url, $type, $message ) {
		wp_redirect(
			add_query_arg(
				array(
					'wenetwork_notice'      => $type,
					'wenetwork_notice_text' => rawurlencode( $message ),
				),
				$base_url
			)
		);
		exit;
	}
}

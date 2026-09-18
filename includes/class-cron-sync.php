<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deux tâches planifiées distinctes :
 * - refresh_tokens (1x/jour) : prolonge les tokens avant expiration.
 * - fetch_media (toutes les 3h) : va chercher les derniers posts et les met en cache.
 */
class WeNetwork_Social_Cron_Sync {

	const REFRESH_HOOK = 'wenetwork_social_refresh_tokens';
	const FETCH_HOOK    = 'wenetwork_social_fetch_media';

	const MEDIA_TRANSIENT_TTL = 3 * HOUR_IN_SECONDS;
	const REFRESH_THRESHOLD   = 7 * DAY_IN_SECONDS; // refresh si expiration < 7 jours

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::REFRESH_HOOK );
		}

		if ( ! wp_next_scheduled( self::FETCH_HOOK ) ) {
			wp_schedule_event( time(), 'wenetwork_three_hours', self::FETCH_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
		wp_clear_scheduled_hook( self::FETCH_HOOK );
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );

		add_action( self::REFRESH_HOOK, array( $this, 'refresh_tokens' ) );
		add_action( self::FETCH_HOOK, array( $this, 'fetch_all_media' ) );

		// Déclenché juste après une connexion réussie, pour ne pas attendre le prochain cron.
		add_action( 'wenetwork_social_sync_instagram', array( $this, 'fetch_instagram_media' ) );
		add_action( 'wenetwork_social_sync_linkedin', array( $this, 'fetch_linkedin_media' ) );
	}

	public function register_schedule( $schedules ) {
		$schedules['wenetwork_three_hours'] = array(
			'interval' => 3 * HOUR_IN_SECONDS,
			'display'  => __( 'Toutes les 3 heures', 'wenetwork-social' ),
		);

		return $schedules;
	}

	/* ---------------------------------------------------------------- */
	/* Refresh                                                           */
	/* ---------------------------------------------------------------- */

	public function refresh_tokens() {
		$this->maybe_refresh_instagram();
		// LinkedIn : à activer une fois le refresh token confirmé disponible côté LinkedIn.
	}

	private function maybe_refresh_instagram() {
		$data = WeNetwork_Social_Token_Store::get( 'instagram' );

		if ( empty( $data['access_token'] ) ) {
			return;
		}

		if ( ( $data['expires_at'] ?? 0 ) - time() > self::REFRESH_THRESHOLD ) {
			return; // pas encore nécessaire
		}

		$result = WeNetwork_Social_Api_Instagram::refresh_token( $data['access_token'] );

		if ( is_wp_error( $result ) ) {
			wenetwork_social_log( 'cron-refresh-instagram', $result->get_error_message() );
			update_option( 'wenetwork_social_instagram_needs_reconnect', 1, false );
			return;
		}

		WeNetwork_Social_Token_Store::set(
			'instagram',
			array_merge( $data, $result )
		);
		delete_option( 'wenetwork_social_instagram_needs_reconnect' );
	}

	/* ---------------------------------------------------------------- */
	/* Fetch                                                             */
	/* ---------------------------------------------------------------- */

	public function fetch_all_media() {
		$this->fetch_instagram_media();
		$this->fetch_linkedin_media();
	}

	/**
	 * @return true|WP_Error Permet à un appel manuel (bouton admin) d'afficher l'erreur exacte.
	 */
	public function fetch_instagram_media() {
		$data = WeNetwork_Social_Token_Store::get( 'instagram' );

		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'wenetwork_ig_not_connected', __( 'Aucun compte Instagram connecté.', 'wenetwork-social' ) );
		}

		$media = WeNetwork_Social_Api_Instagram::fetch_media( $data['access_token'] );

		if ( is_wp_error( $media ) ) {
			wenetwork_social_log( 'cron-fetch-instagram', $media->get_error_message() );
			return $media;
		}

		foreach ( $media as &$item ) {
			$item['thumbnail'] = WeNetwork_Social_Media_Cache::cache_image( 'instagram', $item['id'], $item['thumbnail'] );
		}
		unset( $item );

		set_transient( 'wenetwork_social_media_instagram', $media, self::MEDIA_TRANSIENT_TTL );

		$profile = WeNetwork_Social_Api_Instagram::fetch_profile( $data['access_token'] );

		if ( ! is_wp_error( $profile ) ) {
			set_transient( 'wenetwork_social_profile_instagram', $profile, self::MEDIA_TRANSIENT_TTL );
		}

		return true;
	}

	public function fetch_linkedin_media() {
		$data = WeNetwork_Social_Token_Store::get( 'linkedin' );

		if ( empty( $data['access_token'] ) ) {
			return;
		}

		// TODO : brancher une fois WeNetwork_Social_Api_Linkedin::fetch_posts() implémenté.
	}
}

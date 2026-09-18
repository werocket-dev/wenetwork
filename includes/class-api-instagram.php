<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Instagram API (Instagram API with Instagram Login — scope
 * instagram_business_basic). Pas de Page Facebook requise avec ce flow.
 */
class WeNetwork_Social_Api_Instagram {

	const AUTHORIZE_URL          = 'https://www.instagram.com/oauth/authorize';
	const TOKEN_URL              = 'https://api.instagram.com/oauth/access_token';
	const LONG_LIVED_TOKEN_URL   = 'https://graph.instagram.com/access_token';
	const REFRESH_TOKEN_URL      = 'https://graph.instagram.com/refresh_access_token';
	const GRAPH_BASE_URL         = 'https://graph.instagram.com';

	public static function redirect_uri() {
		// Chemin propre, sans query string : certains fournisseurs OAuth (Instagram
		// inclus) ne préservent pas fidèlement les paramètres de requête personnalisés
		// dans la redirect_uri et reconstruisent l'URL de retour avec juste ?code=...
		return home_url( 'wenetwork-social/callback/instagram/' );
	}

	public static function get_authorize_url( $state ) {
		$params = array(
			'client_id'     => WENETWORK_META_APP_ID,
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'instagram_business_basic',
			'state'         => $state,
		);

		return self::AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * Echange le "code" contre un token court, puis contre un long-lived token.
	 *
	 * @return array|WP_Error
	 */
	public static function exchange_code_for_token( $code ) {
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'client_id'     => WENETWORK_META_APP_ID,
					'client_secret' => WENETWORK_META_APP_SECRET,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::redirect_uri(),
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'wenetwork_ig_token_exchange_failed', 'Réponse Instagram invalide.', $body );
		}

		return self::exchange_for_long_lived_token( $body['access_token'], $body['user_id'] ?? null );
	}

	/**
	 * @return array|WP_Error {access_token, expires_at, ig_user_id}
	 */
	private static function exchange_for_long_lived_token( $short_lived_token, $ig_user_id ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'grant_type'    => 'ig_exchange_token',
					'client_secret' => WENETWORK_META_APP_SECRET,
					'access_token'  => $short_lived_token,
				),
				self::LONG_LIVED_TOKEN_URL
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'wenetwork_ig_long_lived_exchange_failed', 'Echange long-lived token échoué.', $body );
		}

		return array(
			'access_token' => $body['access_token'],
			'expires_at'   => time() + (int) ( $body['expires_in'] ?? 5184000 ), // ~60 jours
			'ig_user_id'   => $ig_user_id,
		);
	}

	/**
	 * @return array|WP_Error {access_token, expires_at}
	 */
	public static function refresh_token( $access_token ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $access_token,
				),
				self::REFRESH_TOKEN_URL
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'wenetwork_ig_refresh_failed', 'Refresh du token Instagram échoué.', $body );
		}

		return array(
			'access_token' => $body['access_token'],
			'expires_at'   => time() + (int) ( $body['expires_in'] ?? 5184000 ),
		);
	}

	/**
	 * Récupère les derniers médias du compte.
	 *
	 * @param string $access_token
	 * @param int    $limit
	 * @return array|WP_Error Liste de médias normalisés.
	 */
	public static function fetch_media( $access_token, $limit = 12 ) {
		$fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';

		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => $fields,
					'limit'        => $limit,
					'access_token' => $access_token,
				),
				self::GRAPH_BASE_URL . '/me/media'
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) ) {
			return isset( $body['error'] )
				? new WP_Error( 'wenetwork_ig_fetch_failed', $body['error']['message'] ?? 'Erreur API Instagram.', $body )
				: array();
		}

		return array_map(
			static function ( $item ) {
				return array(
					'id'         => $item['id'],
					'caption'    => $item['caption'] ?? '',
					// IMAGE | VIDEO | CAROUSEL_ALBUM. Les Reels remontent en VIDEO :
					// à confirmer/affiner en inspectant une vraie réponse une fois connecté,
					// un champ media_product_type peut distinguer REELS des vidéos classiques.
					'media_type' => $item['media_type'] ?? 'IMAGE',
					'media_url'  => $item['media_url'] ?? '',
					'thumbnail'  => $item['thumbnail_url'] ?? ( $item['media_url'] ?? '' ),
					'permalink'  => $item['permalink'] ?? '',
					'timestamp'  => $item['timestamp'] ?? '',
					'like_count' => (int) ( $item['like_count'] ?? 0 ),
					'comments_count' => (int) ( $item['comments_count'] ?? 0 ),
				);
			},
			$body['data']
		);
	}

	/**
	 * @return array{username:string, followers_count:int}|WP_Error
	 */
	public static function fetch_profile( $access_token ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => 'username,followers_count',
					'access_token' => $access_token,
				),
				self::GRAPH_BASE_URL . '/me'
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['username'] ) ) {
			return new WP_Error( 'wenetwork_ig_profile_failed', $body['error']['message'] ?? 'Erreur API Instagram.', $body );
		}

		return array(
			'username'        => $body['username'],
			'followers_count' => (int) ( $body['followers_count'] ?? 0 ),
		);
	}
}

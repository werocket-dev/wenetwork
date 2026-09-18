<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client LinkedIn API.
 *
 * A CONFIRMER avant de coder fetch_posts() : contrairement à Instagram,
 * lire les posts d'une Page LinkedIn nécessite le scope r_organization_social,
 * qui fait partie du "Community Management API" et exige une validation
 * partenaire LinkedIn (pas un simple scope à cocher). Sans cette validation,
 * seul w_member_social (écriture) et le profil de base sont accessibles.
 * -> Vérifier l'accès obtenu dans LinkedIn Developer Portal avant d'aller plus loin.
 */
class WeNetwork_Social_Api_Linkedin {

	const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';
	const TOKEN_URL      = 'https://www.linkedin.com/oauth/v2/accessToken';

	public static function redirect_uri() {
		return home_url( 'wenetwork-social/callback/linkedin/' );
	}

	public static function get_authorize_url( $state ) {
		$params = array(
			'response_type' => 'code',
			'client_id'     => WENETWORK_LINKEDIN_CLIENT_ID,
			'redirect_uri'  => self::redirect_uri(),
			'state'         => $state,
			// r_organization_social ajouté ici une fois l'accès partenaire confirmé.
			'scope'         => 'r_liteprofile',
		);

		return self::AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * @return array|WP_Error {access_token, expires_at}
	 */
	public static function exchange_code_for_token( $code ) {
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => self::redirect_uri(),
					'client_id'     => WENETWORK_LINKEDIN_CLIENT_ID,
					'client_secret' => WENETWORK_LINKEDIN_CLIENT_SECRET,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'wenetwork_li_token_exchange_failed', 'Réponse LinkedIn invalide.', $body );
		}

		return array(
			'access_token' => $body['access_token'],
			'expires_at'   => time() + (int) ( $body['expires_in'] ?? 5184000 ),
		);
	}

	/**
	 * TODO : implémenter une fois le scope r_organization_social validé par LinkedIn.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_posts( $access_token, $organization_urn, $limit = 12 ) {
		return new WP_Error( 'wenetwork_li_not_implemented', 'Fetch des posts LinkedIn en attente de validation du scope r_organization_social.' );
	}
}

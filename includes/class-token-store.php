<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stockage chiffré des tokens OAuth, un jeu de tokens par réseau et par site
 * (chaque site WordPress est un client différent, la table wp_options est
 * déjà "par site" donc aucune clé supplémentaire n'est nécessaire).
 */
class WeNetwork_Social_Token_Store {

	const OPTION_NAME = 'wenetwork_social_tokens';
	const CIPHER      = 'aes-256-cbc';

	/**
	 * @param string $network 'instagram' | 'linkedin'
	 * @return array{access_token?:string, expires_at?:int, ig_user_id?:string, ...}|null
	 */
	public static function get( $network ) {
		$all = get_option( self::OPTION_NAME, array() );

		if ( empty( $all[ $network ] ) ) {
			return null;
		}

		$decrypted = self::decrypt( $all[ $network ] );

		return $decrypted ? json_decode( $decrypted, true ) : null;
	}

	/**
	 * @param string $network
	 * @param array  $data
	 */
	public static function set( $network, array $data ) {
		$all = get_option( self::OPTION_NAME, array() );

		$all[ $network ] = self::encrypt( wp_json_encode( $data ) );

		update_option( self::OPTION_NAME, $all, false );
	}

	public static function delete( $network ) {
		$all = get_option( self::OPTION_NAME, array() );

		unset( $all[ $network ] );

		update_option( self::OPTION_NAME, $all, false );
	}

	public static function is_connected( $network ) {
		$data = self::get( $network );

		return ! empty( $data['access_token'] );
	}

	private static function encryption_key() {
		// Dérivée des salts WordPress : jamais stockée nulle part ailleurs,
		// unique par site, suffit à empêcher la lecture en clair depuis la DB seule.
		$secret = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );

		return hash( 'sha256', $secret, true );
	}

	private static function encrypt( $plaintext ) {
		$key = self::encryption_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext );
	}

	private static function decrypt( $encoded ) {
		$key      = self::encryption_key();
		$raw      = base64_decode( $encoded, true );
		$iv_len   = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $raw || strlen( $raw ) <= $iv_len ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_len );
		$ciphertext = substr( $raw, $iv_len );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? '' : $plaintext;
	}
}

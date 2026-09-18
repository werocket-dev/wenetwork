<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Télécharge et stocke localement les vignettes Instagram/LinkedIn.
 *
 * Pourquoi : les URLs de médias renvoyées par les APIs (media_url, thumbnail_url)
 * sont temporaires et peuvent expirer/changer. Les servir directement casse
 * l'affichage de façon aléatoire. On les met donc en cache dans les uploads WP,
 * régénéré à chaque cycle de fetch (toutes les 3h).
 */
class WeNetwork_Social_Media_Cache {

	const SUBDIR = 'wenetwork-social';

	/**
	 * @param string $network
	 * @param string $media_id
	 * @param string $remote_url
	 * @return string URL locale si le téléchargement réussit, sinon l'URL distante d'origine.
	 */
	public static function cache_image( $network, $media_id, $remote_url ) {
		if ( empty( $remote_url ) ) {
			return $remote_url;
		}

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . self::SUBDIR . '/' . sanitize_key( $network );

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return $remote_url;
		}

		$extension = self::guess_extension( $remote_url );
		$filename  = sanitize_file_name( $media_id ) . '.' . $extension;
		$filepath  = trailingslashit( $target_dir ) . $filename;
		$file_url  = trailingslashit( $upload_dir['baseurl'] ) . self::SUBDIR . '/' . sanitize_key( $network ) . '/' . $filename;

		// Déjà en cache et récent (moins de 3h, aligné sur le cycle de fetch) : on ne re-télécharge pas.
		if ( file_exists( $filepath ) && ( time() - filemtime( $filepath ) ) < 3 * HOUR_IN_SECONDS ) {
			return $file_url;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = download_url( $remote_url, 15 );

		if ( is_wp_error( $tmp_file ) ) {
			wenetwork_social_log( 'media-cache', $tmp_file->get_error_message() );
			return $remote_url;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$copied = $wp_filesystem && $wp_filesystem->move( $tmp_file, $filepath, true );

		if ( ! $copied ) {
			@unlink( $tmp_file );
			return $remote_url;
		}

		return $file_url;
	}

	private static function guess_extension( $url ) {
		$path      = wp_parse_url( $url, PHP_URL_PATH );
		$extension = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

		return in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ? $extension : 'jpg';
	}
}

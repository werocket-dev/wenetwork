<?php
/**
 * Plugin Name: WeRocket Social
 * Description: Passerelle OAuth Instagram & LinkedIn pour afficher les posts des clients via un élément Breakdance.
 * Version: 1.0.4
 * Author: WeRocket
 * Text Domain: wenetwork-social
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WENETWORK_SOCIAL_VERSION', '1.0.4' );
define( 'WENETWORK_SOCIAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WENETWORK_SOCIAL_URL', plugin_dir_url( __FILE__ ) );

/*
 * Credentials agence (une seule app Meta / une seule app LinkedIn pour tous les
 * sites clients). A définir dans wp-config.php de chaque site, jamais en dur ici :
 *
 * define( 'WENETWORK_META_APP_ID', '...' );
 * define( 'WENETWORK_META_APP_SECRET', '...' );
 * define( 'WENETWORK_LINKEDIN_CLIENT_ID', '...' );
 * define( 'WENETWORK_LINKEDIN_CLIENT_SECRET', '...' );
 */

require_once WENETWORK_SOCIAL_PATH . 'includes/helpers.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-token-store.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-media-cache.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-api-instagram.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-api-linkedin.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-oauth-router.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-cron-sync.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-admin-settings.php';
require_once WENETWORK_SOCIAL_PATH . 'includes/class-shortcode.php';

require_once WENETWORK_SOCIAL_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/werocket-dev/wenetwork/',
	__FILE__,
	'wenetwork-social'
);

register_activation_hook( __FILE__, array( 'WeNetwork_Social_Cron_Sync', 'activate' ) );
register_activation_hook( __FILE__, array( 'WeNetwork_Social_OAuth_Router', 'activate_rewrite' ) );
register_deactivation_hook( __FILE__, array( 'WeNetwork_Social_Cron_Sync', 'deactivate' ) );
register_deactivation_hook( __FILE__, array( 'WeNetwork_Social_OAuth_Router', 'deactivate_rewrite' ) );

add_action( 'plugins_loaded', 'wenetwork_social_init' );
add_action( 'init', 'wenetwork_social_load_textdomain' );

// Les textes sources sont en français : toute variante d'anglais (en_GB, en_CA...) utilise le
// catalogue en_US. Ce filtre (WordPress 6.6+) couvre aussi les changements de langue en cours de
// requête, par exemple le message réservé à l'administrateur affiché sur un site en français.
add_filter(
	'load_translation_file',
	static function ( $file, $domain, $locale ) {
		if ( 'wenetwork-social' === $domain && 0 === strpos( (string) $locale, 'en_' ) ) {
			return WENETWORK_SOCIAL_PATH . 'languages/wenetwork-social-en_US.mo';
		}

		return $file;
	},
	10,
	3
);

function wenetwork_social_load_textdomain() {
	// Enregistre le dossier des traductions pour le chargement à la demande de WordPress.
	load_plugin_textdomain( 'wenetwork-social', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$locale = determine_locale();

	// Chargement explicite sous la locale réelle : avant WordPress 6.6, ou si le filtre n'est pas appliqué,
	// le fichier serait cherché sous le nom de la locale courante (en_GB, ...) et introuvable.
	if ( 0 === strpos( $locale, 'en_' ) ) {
		load_textdomain( 'wenetwork-social', WENETWORK_SOCIAL_PATH . 'languages/wenetwork-social-en_US.mo', $locale );
	}
}

function wenetwork_social_init() {
	WeNetwork_Social_OAuth_Router::instance();
	WeNetwork_Social_Cron_Sync::instance();
	WeNetwork_Social_Admin_Settings::instance();
	WeNetwork_Social_Shortcode::instance();
}

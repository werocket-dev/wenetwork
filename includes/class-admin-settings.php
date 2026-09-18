<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeNetwork_Social_Admin_Settings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'WeRocket Social', 'wenetwork-social' ),
			__( 'WeRocket Social', 'wenetwork-social' ),
			'manage_options',
			'wenetwork-social',
			array( $this, 'render_page' ),
			'dashicons-share'
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) && 'linkedin' === $_GET['tab'] ? 'linkedin' : 'instagram';

		$this->render_notice();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WeRocket Social', 'wenetwork-social' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wenetwork-social', 'tab' => 'instagram' ), admin_url( 'admin.php' ) ) ); ?>"
					class="nav-tab <?php echo 'instagram' === $tab ? 'nav-tab-active' : ''; ?>">
					Instagram
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wenetwork-social', 'tab' => 'linkedin' ), admin_url( 'admin.php' ) ) ); ?>"
					class="nav-tab <?php echo 'linkedin' === $tab ? 'nav-tab-active' : ''; ?>">
					LinkedIn
				</a>
			</h2>

			<div style="margin-top: 20px;">
				<?php if ( 'instagram' === $tab ) : ?>
					<?php $this->render_network_panel( 'instagram', __( 'Instagram', 'wenetwork-social' ) ); ?>
				<?php else : ?>
					<?php $this->render_network_panel( 'linkedin', __( 'LinkedIn', 'wenetwork-social' ) ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_network_panel( $network, $label ) {
		$connected      = WeNetwork_Social_Token_Store::is_connected( $network );
		$needs_reconnect = 'instagram' === $network && get_option( 'wenetwork_social_instagram_needs_reconnect' );
		?>
		<div class="card" style="max-width: 600px; padding: 20px;">
			<p>
				<?php
				if ( $needs_reconnect ) {
					echo '<strong style="color:#b32d2e;">' . esc_html__( 'La connexion a expiré, merci de vous reconnecter.', 'wenetwork-social' ) . '</strong>';
				} elseif ( $connected ) {
					echo '<span style="color:#1a7f37;">&#10003; ' . esc_html__( 'Compte connecté', 'wenetwork-social' ) . '</span>';
				} else {
					echo esc_html__( 'Aucun compte connecté pour le moment.', 'wenetwork-social' );
				}
				?>
			</p>

			<?php if ( $connected && ! $needs_reconnect ) : ?>
				<?php if ( 'instagram' === $network ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wenetwork_ig_sync_now' ) ); ?>" class="button" style="margin-right: 8px;">
						<?php esc_html_e( 'Forcer la synchronisation', 'wenetwork-social' ); ?>
					</a>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="wenetwork_<?php echo 'instagram' === $network ? 'ig' : 'li'; ?>_disconnect">
					<?php submit_button( __( 'Déconnecter', 'wenetwork-social' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wenetwork_' . ( 'instagram' === $network ? 'ig' : 'li' ) . '_connect' ) ); ?>"
					class="button button-primary" style="background:<?php echo 'instagram' === $network ? '#e1306c' : '#0a66c2'; ?>; border-color: transparent;">
					<?php
					printf(
						/* translators: %s: nom du réseau social */
						esc_html__( 'Se connecter à %s', 'wenetwork-social' ),
						esc_html( $label )
					);
					?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $connected && ! $needs_reconnect ) : ?>
			<?php $this->render_shortcode_generator( $network ); ?>
			<?php if ( 'instagram' === $network ) : ?>
				<?php $this->render_follow_button_card(); ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	private function render_follow_button_card() {
		$field_id_prefix = 'wenetwork-follow-instagram';
		?>
		<div class="card wenetwork-generator">
			<h2><?php esc_html_e( 'Bouton "Suivez-nous"', 'wenetwork-social' ); ?></h2>
			<p><?php esc_html_e( 'Affiche le nom du compte et son nombre d\'abonnés, avec un lien direct vers le profil Instagram.', 'wenetwork-social' ); ?></p>

			<div class="wenetwork-field-grid">
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-style"><?php esc_html_e( 'Style', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-style" class="wenetwork-shortcode-input" data-attr="style">
						<option value="gradient"><?php esc_html_e( 'Dégradé Instagram', 'wenetwork-social' ); ?></option>
						<option value="solid"><?php esc_html_e( 'Couleur unie', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-color"><?php esc_html_e( 'Couleur (si "Couleur unie")', 'wenetwork-social' ); ?></label>
					<input type="color" id="<?php echo esc_attr( $field_id_prefix ); ?>-color" class="wenetwork-shortcode-input" data-attr="color" value="#E1306C">
				</div>
			</div>

			<div class="wenetwork-output-row">
				<label for="<?php echo esc_attr( $field_id_prefix ); ?>-output"><strong><?php esc_html_e( 'Code à copier :', 'wenetwork-social' ); ?></strong></label>
				<p>
					<input type="text" readonly id="<?php echo esc_attr( $field_id_prefix ); ?>-output" class="widefat" data-network="instagram" data-shortcode="wenetwork_social_follow" onclick="this.select();">
				</p>

				<button type="button" class="button button-secondary wenetwork-copy-shortcode" data-target="<?php echo esc_attr( $field_id_prefix ); ?>-output">
					<?php esc_html_e( 'Copier le shortcode', 'wenetwork-social' ); ?>
				</button>
				<span class="wenetwork-copy-feedback" style="margin-left: 8px; color: #1a7f37; display: none;"><?php esc_html_e( 'Copié !', 'wenetwork-social' ); ?></span>
			</div>
		</div>
		<?php
	}

	private function render_shortcode_generator( $network ) {
		$field_id_prefix = 'wenetwork-shortcode-' . $network;
		?>
		<style>
			.wenetwork-generator { max-width: 1100px; padding: 24px 28px; margin-top: 20px; }
			.wenetwork-generator h2 { margin-top: 0; }
			.wenetwork-generator h3 { margin: 28px 0 4px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.04em; color: #646970; }
			.wenetwork-generator h3:first-of-type { margin-top: 16px; }
			.wenetwork-field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px 24px; margin-top: 12px; }
			.wenetwork-field { display: flex; flex-direction: column; gap: 6px; }
			.wenetwork-field label { font-weight: 600; }
			.wenetwork-field select,
			.wenetwork-field input[type="number"] { width: 100%; }
			.wenetwork-field input[type="color"] { width: 100%; height: 36px; padding: 2px; }
			.wenetwork-output-row { margin-top: 28px; }
			.wenetwork-output-row input { font-family: monospace; }
		</style>

		<div class="card wenetwork-generator">
			<h2><?php esc_html_e( 'Personnalisation de l\'affichage', 'wenetwork-social' ); ?></h2>
			<p><?php esc_html_e( 'Choisis les réglages ci-dessous, puis copie le code généré dans un élément "Shortcode" de Breakdance.', 'wenetwork-social' ); ?></p>

			<h3><?php esc_html_e( 'Contenu', 'wenetwork-social' ); ?></h3>
			<div class="wenetwork-field-grid">
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-filter"><?php esc_html_e( 'Type de contenu', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-filter" class="wenetwork-shortcode-input" data-attr="filter">
						<option value="all"><?php esc_html_e( 'Tout', 'wenetwork-social' ); ?></option>
						<option value="IMAGE"><?php esc_html_e( 'Photos', 'wenetwork-social' ); ?></option>
						<option value="VIDEO"><?php esc_html_e( 'Vidéos / Reels', 'wenetwork-social' ); ?></option>
						<option value="CAROUSEL_ALBUM"><?php esc_html_e( 'Carrousels', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-count"><?php esc_html_e( 'Nombre de posts', 'wenetwork-social' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $field_id_prefix ); ?>-count" class="wenetwork-shortcode-input" data-attr="count" value="6" min="1" max="24">
				</div>
			</div>

			<h3><?php esc_html_e( 'Mise en page', 'wenetwork-social' ); ?></h3>
			<div class="wenetwork-field-grid">
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-layout"><?php esc_html_e( 'Type d\'affichage', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-layout" class="wenetwork-shortcode-input" data-attr="layout">
						<option value="grid"><?php esc_html_e( 'Grille', 'wenetwork-social' ); ?></option>
						<option value="carousel"><?php esc_html_e( 'Carrousel', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-columns"><?php esc_html_e( 'Colonnes / images visibles par vue', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-columns" class="wenetwork-shortcode-input" data-attr="columns">
						<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, 3 ); ?>><?php echo esc_html( $i ); ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="wenetwork-field" data-show-when="layout=carousel">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-autoplay"><?php esc_html_e( 'Faire défiler tout seul (en plus des flèches)', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-autoplay" class="wenetwork-shortcode-input" data-attr="autoplay">
						<option value="no"><?php esc_html_e( 'Non', 'wenetwork-social' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Oui', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field" data-show-when="layout=carousel">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-autoplay_speed"><?php esc_html_e( 'Vitesse de défilement (secondes)', 'wenetwork-social' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $field_id_prefix ); ?>-autoplay_speed" class="wenetwork-shortcode-input" data-attr="autoplay_speed" value="3" min="1" max="20">
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-ratio"><?php esc_html_e( 'Format des images', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-ratio" class="wenetwork-shortcode-input" data-attr="ratio">
						<option value="square"><?php esc_html_e( 'Carré', 'wenetwork-social' ); ?></option>
						<option value="portrait"><?php esc_html_e( 'Portrait (4:5)', 'wenetwork-social' ); ?></option>
						<option value="landscape"><?php esc_html_e( 'Paysage (1.91:1)', 'wenetwork-social' ); ?></option>
						<option value="original"><?php esc_html_e( 'Original (garder les proportions)', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-gap"><?php esc_html_e( 'Espacement entre les posts (px)', 'wenetwork-social' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $field_id_prefix ); ?>-gap" class="wenetwork-shortcode-input" data-attr="gap" value="12" min="0" max="60">
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-lightbox"><?php esc_html_e( 'Ouvrir en grand au clic (sans quitter le site)', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-lightbox" class="wenetwork-shortcode-input" data-attr="lightbox">
						<option value="yes"><?php esc_html_e( 'Oui', 'wenetwork-social' ); ?></option>
						<option value="no"><?php esc_html_e( 'Non (renvoyer vers Instagram)', 'wenetwork-social' ); ?></option>
					</select>
				</div>
			</div>

			<h3><?php esc_html_e( 'Style visuel', 'wenetwork-social' ); ?></h3>
			<div class="wenetwork-field-grid">
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-radius"><?php esc_html_e( 'Rayon des bordures (px)', 'wenetwork-social' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $field_id_prefix ); ?>-radius" class="wenetwork-shortcode-input" data-attr="radius" value="8" min="0" max="40">
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-border_width"><?php esc_html_e( 'Épaisseur de bordure (px)', 'wenetwork-social' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $field_id_prefix ); ?>-border_width" class="wenetwork-shortcode-input" data-attr="border_width" value="0" min="0" max="10">
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-border_color"><?php esc_html_e( 'Couleur de bordure', 'wenetwork-social' ); ?></label>
					<input type="color" id="<?php echo esc_attr( $field_id_prefix ); ?>-border_color" class="wenetwork-shortcode-input" data-attr="border_color" value="#000000">
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-shadow"><?php esc_html_e( 'Ombrage', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-shadow" class="wenetwork-shortcode-input" data-attr="shadow">
						<option value="none"><?php esc_html_e( 'Aucun', 'wenetwork-social' ); ?></option>
						<option value="light"><?php esc_html_e( 'Léger', 'wenetwork-social' ); ?></option>
						<option value="strong"><?php esc_html_e( 'Prononcé', 'wenetwork-social' ); ?></option>
					</select>
				</div>
				<div class="wenetwork-field">
					<label for="<?php echo esc_attr( $field_id_prefix ); ?>-show_stats"><?php esc_html_e( 'Likes / commentaires au survol', 'wenetwork-social' ); ?></label>
					<select id="<?php echo esc_attr( $field_id_prefix ); ?>-show_stats" class="wenetwork-shortcode-input" data-attr="show_stats">
						<option value="no"><?php esc_html_e( 'Non', 'wenetwork-social' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Oui', 'wenetwork-social' ); ?></option>
					</select>
				</div>
			</div>

			<div class="wenetwork-output-row">
				<label for="<?php echo esc_attr( $field_id_prefix ); ?>-output"><strong><?php esc_html_e( 'Code à copier :', 'wenetwork-social' ); ?></strong></label>
				<p>
					<input type="text" readonly id="<?php echo esc_attr( $field_id_prefix ); ?>-output" class="widefat" data-network="<?php echo esc_attr( $network ); ?>" data-shortcode="wenetwork_social_feed" onclick="this.select();">
				</p>

				<button type="button" class="button button-secondary wenetwork-copy-shortcode" data-target="<?php echo esc_attr( $field_id_prefix ); ?>-output">
					<?php esc_html_e( 'Copier le shortcode', 'wenetwork-social' ); ?>
				</button>
				<span class="wenetwork-copy-feedback" style="margin-left: 8px; color: #1a7f37; display: none;"><?php esc_html_e( 'Copié !', 'wenetwork-social' ); ?></span>
			</div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			function buildShortcode( scope ) {
				var output = scope.querySelector( '[id$="-output"]' );
				if ( ! output ) {
					return;
				}
				var network = output.getAttribute( 'data-network' );
				var shortcodeName = output.getAttribute( 'data-shortcode' );
				var parts = [ 'network="' + network + '"' ];

				scope.querySelectorAll( '.wenetwork-shortcode-input' ).forEach( function ( input ) {
					parts.push( input.getAttribute( 'data-attr' ) + '="' + input.value + '"' );
				} );

				output.value = '[' + shortcodeName + ' ' + parts.join( ' ' ) + ']';
			}

			function applyConditionalFields( scope ) {
				scope.querySelectorAll( '[data-show-when]' ).forEach( function ( field ) {
					var condition = field.getAttribute( 'data-show-when' ).split( '=' );
					var input = scope.querySelector( '.wenetwork-shortcode-input[data-attr="' + condition[ 0 ] + '"]' );
					field.style.display = ( input && input.value === condition[ 1 ] ) ? '' : 'none';
				} );
			}

			document.querySelectorAll( '.wenetwork-shortcode-input' ).forEach( function ( input ) {
				var card = input.closest( '.card' );
				buildShortcode( card );
				applyConditionalFields( card );
				[ 'input', 'change' ].forEach( function ( evt ) {
					input.addEventListener( evt, function () {
						buildShortcode( card );
						applyConditionalFields( card );
					} );
				} );
			} );

			document.querySelectorAll( '.wenetwork-copy-shortcode' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var target = document.getElementById( button.getAttribute( 'data-target' ) );
					target.select();
					document.execCommand( 'copy' );

					var feedback = button.nextElementSibling;
					feedback.style.display = 'inline';
					setTimeout( function () {
						feedback.style.display = 'none';
					}, 1500 );
				} );
			} );
		} );
		</script>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['wenetwork_notice'] ) ) {
			return;
		}

		$type    = 'success' === $_GET['wenetwork_notice'] ? 'notice-success' : 'notice-error';
		$message = isset( $_GET['wenetwork_notice_text'] ) ? sanitize_text_field( wp_unslash( $_GET['wenetwork_notice_text'] ) ) : '';

		if ( empty( $message ) ) {
			return;
		}

		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [wenetwork_social_feed network="instagram" filter="all" count="6" columns="3"]
 * [wenetwork_social_follow network="instagram"]
 *
 * A utiliser dans l'élément natif "Shortcode" de Breakdance (ou n'importe quel
 * autre builder/thème) : évite de toucher à l'API interne des éléments Breakdance,
 * dont le format exact n'est pas documenté publiquement et casse le builder en cas
 * d'erreur (voir Element Studio dans l'admin Breakdance pour construire une vraie
 * UI drag-and-drop plus tard, en s'appuyant sur ce shortcode).
 */
class WeNetwork_Social_Shortcode {

	private static $instance = null;
	private static $assets_printed = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'wenetwork_social_feed', array( $this, 'render_feed' ) );
		add_shortcode( 'wenetwork_social_follow', array( $this, 'render_follow_button' ) );
	}

	public function render_feed( $atts ) {
		$atts = shortcode_atts(
			array(
				'network'        => 'instagram',
				'filter'         => 'all',
				'count'          => 6,
				'columns'        => 3,
				'ratio'          => 'square',
				'gap'            => 12,
				'radius'         => 8,
				'border_width'   => 0,
				'border_color'   => '#000000',
				'shadow'         => 'none',
				'show_stats'     => 'no',
				'lightbox'       => 'yes',
				'layout'         => 'grid',
				'autoplay'       => 'no',
				'autoplay_speed' => 3,
			),
			$atts,
			'wenetwork_social_feed'
		);

		$network      = in_array( $atts['network'], array( 'instagram', 'linkedin' ), true ) ? $atts['network'] : 'instagram';
		$filter       = sanitize_text_field( $atts['filter'] );
		$count        = max( 1, min( 24, (int) $atts['count'] ) );
		$columns      = max( 1, min( 6, (int) $atts['columns'] ) );
		$gap          = max( 0, min( 60, (int) $atts['gap'] ) );
		$radius       = max( 0, min( 40, (int) $atts['radius'] ) );
		$border_width = max( 0, min( 10, (int) $atts['border_width'] ) );
		$border_color = sanitize_hex_color( $atts['border_color'] ) ?: '#000000';
		$show_stats   = 'yes' === $atts['show_stats'];
		$use_lightbox = 'yes' === $atts['lightbox'];
		$is_carousel  = 'carousel' === $atts['layout'];
		$autoplay     = 'yes' === $atts['autoplay'];
		$autoplay_ms  = max( 1, min( 20, (int) $atts['autoplay_speed'] ) ) * 1000;

		$ratio_css = array(
			'square'    => '1 / 1',
			'portrait'  => '4 / 5',
			'landscape' => '1.91 / 1',
			'original'  => 'auto',
		);
		$aspect_ratio = $ratio_css[ $atts['ratio'] ] ?? $ratio_css['square'];
		$object_fit   = 'auto' === $aspect_ratio ? 'contain' : 'cover';

		$shadow_css = array(
			'none'   => 'none',
			'light'  => '0 2px 8px rgba(0,0,0,0.15)',
			'strong' => '0 8px 24px rgba(0,0,0,0.35)',
		);
		$box_shadow = $shadow_css[ $atts['shadow'] ] ?? $shadow_css['none'];

		$item_style = sprintf(
			'display:block;position:relative;aspect-ratio:%s;overflow:hidden;border-radius:%dpx;box-shadow:%s;%s',
			$aspect_ratio,
			$radius,
			$box_shadow,
			$border_width > 0 ? sprintf( 'border:%dpx solid %s;', $border_width, $border_color ) : ''
		);

		$assets = $this->maybe_print_assets();

		$media = get_transient( 'wenetwork_social_media_' . $network );

		if ( empty( $media ) || is_wp_error( $media ) ) {
			return $assets . '<p class="wenetwork-social-feed-empty">' . esc_html__( 'Flux en cours de synchronisation.', 'wenetwork-social' ) . '</p>';
		}

		if ( 'all' !== $filter ) {
			$media = array_filter(
				$media,
				static function ( $item ) use ( $filter ) {
					return ( $item['media_type'] ?? '' ) === $filter;
				}
			);
		}

		$media = array_slice( $media, 0, $count );

		if ( empty( $media ) ) {
			return $assets . '<p class="wenetwork-social-feed-empty">' . esc_html__( 'Aucun post à afficher.', 'wenetwork-social' ) . '</p>';
		}

		$items_html = '';
		foreach ( $media as $item ) {
			$items_html .= $this->render_item_html( $item, $item_style, $object_fit, $show_stats, $use_lightbox );
		}

		if ( $is_carousel ) {
			ob_start();
			?>
			<div class="wenetwork-social-carousel-wrapper" style="--wenetwork-cols:<?php echo esc_attr( $columns ); ?>;" data-autoplay="<?php echo $autoplay ? 'yes' : 'no'; ?>" data-speed="<?php echo esc_attr( $autoplay_ms ); ?>">
				<div class="wenetwork-social-carousel-track" style="gap:<?php echo esc_attr( $gap ); ?>px;">
					<?php
					foreach ( $media as $item ) {
						echo '<div class="wenetwork-social-carousel-slide">' . $this->render_item_html( $item, $item_style, $object_fit, $show_stats, $use_lightbox ) . '</div>';
					}
					?>
				</div>
				<button type="button" class="wenetwork-carousel-btn wenetwork-carousel-prev" aria-label="<?php esc_attr_e( 'Précédent', 'wenetwork-social' ); ?>">&#8249;</button>
				<button type="button" class="wenetwork-carousel-btn wenetwork-carousel-next" aria-label="<?php esc_attr_e( 'Suivant', 'wenetwork-social' ); ?>">&#8250;</button>
			</div>
			<?php
			return $assets . ob_get_clean();
		}

		ob_start();
		?>
		<div class="wenetwork-social-feed" style="display:grid;--wenetwork-cols:<?php echo esc_attr( $columns ); ?>;gap:<?php echo esc_attr( $gap ); ?>px;">
			<?php echo $items_html; ?>
		</div>
		<?php
		return $assets . ob_get_clean();
	}

	private function render_item_html( $item, $item_style, $object_fit, $show_stats, $use_lightbox ) {
		$is_video = 'VIDEO' === ( $item['media_type'] ?? '' );

		ob_start();
		?>
		<a
			href="<?php echo esc_url( $item['permalink'] ); ?>"
			<?php echo $use_lightbox ? '' : 'target="_blank" rel="noopener"'; ?>
			class="wenetwork-social-feed-item"
			style="<?php echo esc_attr( $item_style ); ?>"
			<?php if ( $use_lightbox ) : ?>
				data-wenetwork-lightbox="1"
				data-full="<?php echo esc_url( $item['media_url'] ?: $item['thumbnail'] ); ?>"
				data-type="<?php echo esc_attr( $item['media_type'] ?? 'IMAGE' ); ?>"
				data-caption="<?php echo esc_attr( wp_strip_all_tags( $item['caption'] ?? '' ) ); ?>"
				data-permalink="<?php echo esc_url( $item['permalink'] ); ?>"
				data-likes="<?php echo esc_attr( number_format_i18n( $item['like_count'] ?? 0 ) ); ?>"
				data-comments="<?php echo esc_attr( number_format_i18n( $item['comments_count'] ?? 0 ) ); ?>"
			<?php endif; ?>
		>
			<?php if ( $is_video ) : ?>
				<video class="wenetwork-social-feed-video" poster="<?php echo esc_url( $item['thumbnail'] ); ?>" data-src="<?php echo esc_url( $item['media_url'] ); ?>" muted loop playsinline preload="none" style="width:100%;height:100%;object-fit:<?php echo esc_attr( $object_fit ); ?>;"></video>
			<?php else : ?>
				<img src="<?php echo esc_url( $item['thumbnail'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $item['caption'] ?? '' ) ); ?>" loading="lazy" style="width:100%;height:100%;object-fit:<?php echo esc_attr( $object_fit ); ?>;">
			<?php endif; ?>
			<?php if ( $show_stats ) : ?>
				<span class="wenetwork-social-feed-stats">
					<span>&#10084; <?php echo esc_html( number_format_i18n( $item['like_count'] ?? 0 ) ); ?></span>
					<span>&#128172; <?php echo esc_html( number_format_i18n( $item['comments_count'] ?? 0 ) ); ?></span>
				</span>
			<?php endif; ?>
		</a>
		<?php
		return ob_get_clean();
	}

	public function render_follow_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'network' => 'instagram',
				'style'   => 'gradient',
				'color'   => '#E1306C',
			),
			$atts,
			'wenetwork_social_follow'
		);

		$network = in_array( $atts['network'], array( 'instagram', 'linkedin' ), true ) ? $atts['network'] : 'instagram';
		$profile = get_transient( 'wenetwork_social_profile_' . $network );

		if ( empty( $profile ) || is_wp_error( $profile ) ) {
			return '';
		}

		$assets = $this->maybe_print_assets();

		$url = 'instagram' === $network
			? 'https://instagram.com/' . rawurlencode( $profile['username'] )
			: '#';

		$color      = sanitize_hex_color( $atts['color'] ) ?: '#E1306C';
		$background = 'solid' === $atts['style']
			? $color
			: 'linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888)';

		ob_start();
		?>
		<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="wenetwork-social-follow-button" style="background:<?php echo esc_attr( $background ); ?>;">
			<span>@<?php echo esc_html( $profile['username'] ); ?></span>
			<span class="wenetwork-social-follow-count"><?php echo esc_html( number_format_i18n( $profile['followers_count'] ?? 0 ) ); ?> <?php esc_html_e( 'abonnés', 'wenetwork-social' ); ?></span>
		</a>
		<?php
		return $assets . ob_get_clean();
	}

	/**
	 * @return string Le HTML/CSS/JS communs (imprimé une seule fois), à préfixer au HTML retourné.
	 */
	private function maybe_print_assets() {
		if ( self::$assets_printed ) {
			return '';
		}

		self::$assets_printed = true;

		ob_start();
		?>
		<style>
			.wenetwork-social-feed,
			.wenetwork-social-carousel-wrapper {
				--wenetwork-max-cols: 999;
			}
			.wenetwork-social-feed {
				grid-template-columns: repeat(min(var(--wenetwork-cols), var(--wenetwork-max-cols)), 1fr);
			}
			.wenetwork-social-carousel-wrapper {
				position: relative;
			}
			.wenetwork-social-carousel-track {
				display: flex;
				overflow-x: auto;
				scroll-snap-type: x mandatory;
				scrollbar-width: none;
				-webkit-overflow-scrolling: touch;
			}
			.wenetwork-social-carousel-track::-webkit-scrollbar {
				display: none;
			}
			.wenetwork-social-carousel-slide {
				flex: 0 0 calc(100% / min(var(--wenetwork-cols), var(--wenetwork-max-cols)));
				scroll-snap-align: start;
			}
			.wenetwork-carousel-btn {
				position: absolute;
				top: 50%;
				transform: translateY(-50%);
				background: rgba(0, 0, 0, 0.5);
				color: #fff;
				border: none;
				width: 36px;
				height: 36px;
				border-radius: 50%;
				cursor: pointer;
				font-size: 18px;
				z-index: 2;
			}
			.wenetwork-carousel-prev { left: 8px; }
			.wenetwork-carousel-next { right: 8px; }
			@media (max-width: 900px) {
				.wenetwork-social-feed,
				.wenetwork-social-carousel-wrapper {
					--wenetwork-max-cols: 2;
				}
			}
			@media (max-width: 560px) {
				.wenetwork-social-feed,
				.wenetwork-social-carousel-wrapper {
					--wenetwork-max-cols: 1;
				}
			}
			.wenetwork-social-feed-stats {
				position: absolute;
				inset: 0;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 16px;
				background: rgba(0, 0, 0, 0.55);
				color: #fff;
				font-weight: 600;
				opacity: 0;
				transition: opacity 0.2s ease;
				pointer-events: none;
			}
			.wenetwork-social-feed-item:hover .wenetwork-social-feed-stats {
				opacity: 1;
			}
			.wenetwork-social-follow-button {
				display: inline-flex;
				align-items: center;
				gap: 10px;
				padding: 10px 18px;
				border-radius: 999px;
				color: #fff;
				font-weight: 600;
				text-decoration: none;
			}
			.wenetwork-social-follow-count {
				opacity: 0.85;
				font-weight: 400;
			}
			#wenetwork-lightbox {
				display: none;
				position: fixed;
				inset: 0;
				background: rgba(0, 0, 0, 0.85);
				z-index: 100000;
				align-items: center;
				justify-content: center;
			}
			#wenetwork-lightbox.is-open {
				display: flex;
			}
			#wenetwork-lightbox .wenetwork-lightbox-content {
				max-width: 90vw;
				max-height: 85vh;
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 12px;
			}
			#wenetwork-lightbox img,
			#wenetwork-lightbox video {
				max-width: 90vw;
				max-height: 70vh;
				border-radius: 8px;
			}
			#wenetwork-lightbox .wenetwork-lightbox-caption {
				color: #fff;
				max-width: 600px;
				text-align: center;
			}
			#wenetwork-lightbox .wenetwork-lightbox-meta {
				color: #ddd;
				display: flex;
				gap: 20px;
			}
			#wenetwork-lightbox .wenetwork-lightbox-close,
			#wenetwork-lightbox .wenetwork-lightbox-prev,
			#wenetwork-lightbox .wenetwork-lightbox-next {
				position: absolute;
				background: rgba(255,255,255,0.15);
				border: none;
				color: #fff;
				font-size: 20px;
				line-height: 1;
				width: 44px;
				height: 44px;
				border-radius: 50%;
				cursor: pointer;
			}
			#wenetwork-lightbox .wenetwork-lightbox-close { top: 20px; right: 20px; }
			#wenetwork-lightbox .wenetwork-lightbox-prev { left: 20px; top: 50%; transform: translateY(-50%); }
			#wenetwork-lightbox .wenetwork-lightbox-next { right: 20px; top: 50%; transform: translateY(-50%); }
		</style>

		<div id="wenetwork-lightbox">
			<button type="button" class="wenetwork-lightbox-close" aria-label="Fermer">&times;</button>
			<button type="button" class="wenetwork-lightbox-prev" aria-label="Précédent">&#8249;</button>
			<button type="button" class="wenetwork-lightbox-next" aria-label="Suivant">&#8250;</button>
			<div class="wenetwork-lightbox-content">
				<div class="wenetwork-lightbox-media"></div>
				<p class="wenetwork-lightbox-caption"></p>
				<div class="wenetwork-lightbox-meta">
					<span class="wenetwork-lightbox-likes"></span>
					<span class="wenetwork-lightbox-comments"></span>
					<a class="wenetwork-lightbox-permalink" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Voir sur Instagram', 'wenetwork-social' ); ?></a>
				</div>
			</div>
		</div>

		<script>
		(function () {
			// Lecture des vidéos/reels au survol, pause + reset à la sortie.
			document.addEventListener( 'mouseover', function ( e ) {
				var video = e.target.closest( '.wenetwork-social-feed-video' );
				if ( ! video ) {
					return;
				}
				if ( ! video.src && video.dataset.src ) {
					video.src = video.dataset.src;
				}
				video.play().catch( function () {} );
			}, true );

			document.addEventListener( 'mouseout', function ( e ) {
				var video = e.target.closest( '.wenetwork-social-feed-video' );
				if ( ! video ) {
					return;
				}
				video.pause();
				video.currentTime = 0;
			}, true );

			// Carrousels : initialisé après le parsing complet du DOM, pour ne pas dépendre
			// de l'ordre d'impression de ce script par rapport au HTML du carrousel lui-même
			// (le script commun n'est imprimé qu'une seule fois, avant le 1er shortcode rencontré).
			function initCarousels() {
				document.querySelectorAll( '.wenetwork-social-carousel-wrapper:not([data-wenetwork-init])' ).forEach( function ( wrapper ) {
					wrapper.setAttribute( 'data-wenetwork-init', '1' );

					var track = wrapper.querySelector( '.wenetwork-social-carousel-track' );
					var prevBtn = wrapper.querySelector( '.wenetwork-carousel-prev' );
					var nextBtn = wrapper.querySelector( '.wenetwork-carousel-next' );

					if ( ! track || ! prevBtn || ! nextBtn ) {
						return;
					}

					function slideStep() {
						var slide = track.querySelector( '.wenetwork-social-carousel-slide' );
						if ( ! slide ) {
							return track.clientWidth;
						}
						var style = getComputedStyle( track );
						var gap = parseFloat( style.columnGap || style.gap || 0 ) || 0;
						return slide.getBoundingClientRect().width + gap;
					}

					function scrollNext() {
						var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
						track.scrollTo( atEnd ? { left: 0, behavior: 'smooth' } : { left: track.scrollLeft + slideStep(), behavior: 'smooth' } );
					}

					function scrollPrev() {
						track.scrollBy( { left: -slideStep(), behavior: 'smooth' } );
					}

					nextBtn.addEventListener( 'click', scrollNext );
					prevBtn.addEventListener( 'click', scrollPrev );

					var autoplay = 'yes' === wrapper.getAttribute( 'data-autoplay' );
					var speed = parseInt( wrapper.getAttribute( 'data-speed' ), 10 ) || 3000;
					var timer = null;

					function start() {
						if ( autoplay && ! timer ) {
							timer = setInterval( scrollNext, speed );
						}
					}

					function stop() {
						clearInterval( timer );
						timer = null;
					}

					start();
					wrapper.addEventListener( 'mouseenter', stop );
					wrapper.addEventListener( 'mouseleave', start );
				} );
			}

			if ( 'loading' === document.readyState ) {
				document.addEventListener( 'DOMContentLoaded', initCarousels );
			} else {
				initCarousels();
			}

			// Lightbox.
			var lightbox = document.getElementById( 'wenetwork-lightbox' );
			if ( ! lightbox ) {
				return;
			}

			var mediaEl     = lightbox.querySelector( '.wenetwork-lightbox-media' );
			var captionEl   = lightbox.querySelector( '.wenetwork-lightbox-caption' );
			var likesEl     = lightbox.querySelector( '.wenetwork-lightbox-likes' );
			var commentsEl  = lightbox.querySelector( '.wenetwork-lightbox-comments' );
			var permalinkEl = lightbox.querySelector( '.wenetwork-lightbox-permalink' );
			var items       = [];
			var currentIndex = 0;

			function openAt( index ) {
				if ( ! items.length ) {
					return;
				}
				currentIndex = ( index + items.length ) % items.length;
				var item = items[ currentIndex ];

				mediaEl.innerHTML = '';
				if ( 'VIDEO' === item.type ) {
					var video = document.createElement( 'video' );
					video.src = item.full;
					video.controls = true;
					video.autoplay = true;
					mediaEl.appendChild( video );
				} else {
					var img = document.createElement( 'img' );
					img.src = item.full;
					mediaEl.appendChild( img );
				}

				captionEl.textContent   = item.caption;
				likesEl.textContent     = '❤ ' + item.likes;
				commentsEl.textContent  = '💬 ' + item.comments;
				permalinkEl.href        = item.permalink;

				lightbox.classList.add( 'is-open' );
			}

			function close() {
				lightbox.classList.remove( 'is-open' );
				mediaEl.innerHTML = '';
			}

			document.addEventListener( 'click', function ( e ) {
				var trigger = e.target.closest( '[data-wenetwork-lightbox]' );
				if ( ! trigger ) {
					return;
				}
				e.preventDefault();

				var group = trigger.closest( '.wenetwork-social-feed, .wenetwork-social-carousel-track' );
				items = Array.prototype.map.call( group.querySelectorAll( '[data-wenetwork-lightbox]' ), function ( el ) {
					return {
						full: el.getAttribute( 'data-full' ),
						type: el.getAttribute( 'data-type' ),
						caption: el.getAttribute( 'data-caption' ),
						permalink: el.getAttribute( 'data-permalink' ),
						likes: el.getAttribute( 'data-likes' ),
						comments: el.getAttribute( 'data-comments' ),
					};
				} );

				var clickedIndex = Array.prototype.indexOf.call( group.querySelectorAll( '[data-wenetwork-lightbox]' ), trigger );
				openAt( clickedIndex );
			} );

			lightbox.querySelector( '.wenetwork-lightbox-close' ).addEventListener( 'click', close );
			lightbox.querySelector( '.wenetwork-lightbox-prev' ).addEventListener( 'click', function () { openAt( currentIndex - 1 ); } );
			lightbox.querySelector( '.wenetwork-lightbox-next' ).addEventListener( 'click', function () { openAt( currentIndex + 1 ); } );

			lightbox.addEventListener( 'click', function ( e ) {
				if ( e.target === lightbox ) {
					close();
				}
			} );

			document.addEventListener( 'keydown', function ( e ) {
				if ( ! lightbox.classList.contains( 'is-open' ) ) {
					return;
				}
				if ( 'Escape' === e.key ) {
					close();
				} else if ( 'ArrowLeft' === e.key ) {
					openAt( currentIndex - 1 );
				} else if ( 'ArrowRight' === e.key ) {
					openAt( currentIndex + 1 );
				}
			} );
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}

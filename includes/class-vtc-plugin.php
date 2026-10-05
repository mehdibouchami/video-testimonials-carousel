<?php
/**
 * Plugin singleton: widget registration and asset registration.
 *
 * @package VideoTestimonialsCarousel
 */

defined( 'ABSPATH' ) || exit;

class VTC_Plugin {

	/**
	 * @var VTC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return VTC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_scripts' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_styles' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add issue and support links to this plugin's row on the Plugins screen.
	 *
	 * @param array  $links Row meta links.
	 * @param string $file  Plugin file the row belongs to.
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( VTC_PATH . 'video-testimonials-carousel.php' ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://github.com/mehdibouchami/video-testimonials-carousel/issues' ),
			esc_html__( 'Report an issue', 'video-testimonials-carousel' )
		);

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://ba9chich.com/fr/mehdibouchami' ),
			esc_html__( 'Support the developer', 'video-testimonials-carousel' )
		);

		return $links;
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
	 */
	public function register_widgets( $widgets_manager ) {
		require_once VTC_PATH . 'includes/class-vtc-widget.php';
		$widgets_manager->register( new VTC_Widget() );
	}

	/**
	 * Elementor ships Swiper itself, but the handle moved over time: Swiper 5 under
	 * "swiper", Swiper 8 under "e-swiper" from Elementor 3.11. Resolve by asking what is
	 * actually registered rather than comparing versions, so a future rename degrades to
	 * an empty dependency instead of a broken one.
	 *
	 * @param string $type "script" or "style".
	 * @return array
	 */
	private static function swiper_dependency( $type ) {
		$candidates = 'style' === $type
			? array( 'e-swiper', 'swiper' )
			: array( 'swiper', 'e-swiper' );

		foreach ( $candidates as $handle ) {
			$registered = 'style' === $type
				? wp_style_is( $handle, 'registered' )
				: wp_script_is( $handle, 'registered' );

			if ( $registered ) {
				return array( $handle );
			}
		}

		return array();
	}

	public function register_scripts() {
		wp_register_script(
			'vtc-carousel',
			VTC_URL . 'assets/js/vtc-carousel.js',
			self::swiper_dependency( 'script' ),
			VTC_VERSION,
			true
		);
	}

	public function register_styles() {
		wp_register_style(
			'vtc-carousel',
			VTC_URL . 'assets/css/vtc-carousel.css',
			self::swiper_dependency( 'style' ),
			VTC_VERSION
		);
	}
}

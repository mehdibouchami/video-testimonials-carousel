<?php
/**
 * Plugin Name:       Video Testimonials Carousel for Elementor
 * Plugin URI:        https://github.com/mehdibouchami/video-testimonials-carousel
 * Description:       A carousel of testimonial cards where video is optional per card. Nothing video-related loads until a card is clicked, and VideoObject schema keeps the videos visible to search engines.
 * Version:           1.6.0
 * Author:            Mehdi Bouchami
 * Author URI:        https://github.com/mehdibouchami
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       video-testimonials-carousel
 *
 * @package VideoTestimonialsCarousel
 */

defined( 'ABSPATH' ) || exit;

define( 'VTC_VERSION', '1.6.0' );
define( 'VTC_PATH', plugin_dir_path( __FILE__ ) );
define( 'VTC_URL', plugin_dir_url( __FILE__ ) );
define( 'VTC_MIN_ELEMENTOR', '3.5.0' );
define( 'VTC_MIN_PHP', '7.4' );

/**
 * Show an admin notice instead of ever fataling out.
 *
 * @param string $message Already-translated message text.
 */
function vtc_admin_notice( $message ) {
	add_action(
		'admin_notices',
		function () use ( $message ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Video Testimonials Carousel:', 'video-testimonials-carousel' ),
				esc_html( $message )
			);
		}
	);
}

/**
 * Check requirements, then boot.
 */
function vtc_bootstrap() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		vtc_admin_notice( __( 'Elementor is not active. Activate Elementor to use this widget.', 'video-testimonials-carousel' ) );
		return;
	}

	if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, VTC_MIN_ELEMENTOR, '<' ) ) {
		vtc_admin_notice(
			sprintf(
				/* translators: %s: minimum Elementor version. */
				__( 'Elementor %s or newer is required.', 'video-testimonials-carousel' ),
				VTC_MIN_ELEMENTOR
			)
		);
		return;
	}

	if ( version_compare( PHP_VERSION, VTC_MIN_PHP, '<' ) ) {
		vtc_admin_notice(
			sprintf(
				/* translators: %s: minimum PHP version. */
				__( 'PHP %s or newer is required.', 'video-testimonials-carousel' ),
				VTC_MIN_PHP
			)
		);
		return;
	}

	require_once VTC_PATH . 'includes/class-vtc-plugin.php';
	VTC_Plugin::instance();
}
add_action( 'plugins_loaded', 'vtc_bootstrap' );

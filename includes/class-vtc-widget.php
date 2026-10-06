<?php
/**
 * The Video Testimonials Carousel widget.
 *
 * @package VideoTestimonialsCarousel
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;

class VTC_Widget extends Widget_Base {

	/**
	 * Extensions accepted as a self-hosted (HTML5) video.
	 */
	const SELF_HOSTED_EXTENSIONS = array( 'mp4', 'm4v', 'webm', 'ogv', 'ogg', 'mov' );

	public function get_name() {
		return 'video_testimonials_carousel';
	}

	public function get_title() {
		return esc_html__( 'Video Testimonials Carousel', 'video-testimonials-carousel' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'carousel', 'slider', 'video', 'testimonial', 'youtube', 'vimeo', 'reels' );
	}

	public function get_script_depends() {
		return array( 'vtc-carousel' );
	}

	public function get_style_depends() {
		return array( 'vtc-carousel' );
	}

	/* ---------------------------------------------------------------------
	 * Video source detection
	 * ------------------------------------------------------------------ */

	/**
	 * Work out the provider from the URL alone.
	 *
	 * Returns an empty array for an empty or unrecognised URL, which the renderer
	 * treats as "image-only card" — a bad URL must never produce a broken player.
	 *
	 * IDs are whitelisted by regex rather than merely escaped, so nothing arbitrary
	 * can reach an iframe src.
	 *
	 * @param string $url Raw URL from the repeater.
	 * @return array
	 */
	public static function detect_video( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return array();
		}

		// YouTube: watch?v=, youtu.be/, /embed/, /shorts/, /live/, /v/.
		// Delimiter is ~ here on purpose: the pattern contains a literal # in a
		// character class, which would end a #-delimited pattern early.
		if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $m[1],
			);
		}

		// Vimeo, including unlisted videos whose privacy hash must be passed along.
		if ( preg_match( '#vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/|album/\d+/video/)?(\d+)#i', $url, $m ) ) {
			$hash = '';

			if ( preg_match( '#[?&]h=([A-Za-z0-9]+)#', $url, $h ) ) {
				$hash = $h[1];
			} elseif ( preg_match( '#vimeo\.com/(?:video/)?\d+/([A-Za-z0-9]+)#i', $url, $h ) ) {
				$hash = $h[1];
			}

			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'hash'     => $hash,
			);
		}

		// Self-hosted / direct media file.
		$parts  = wp_parse_url( $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';

		if ( empty( $parts['host'] ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return array();
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( in_array( $ext, self::SELF_HOSTED_EXTENSIONS, true ) ) {
			return array(
				'provider' => 'self',
				'src'      => esc_url_raw( $url ),
			);
		}

		return array();
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	protected function register_controls() {
		$this->register_items_section();
		$this->register_carousel_section();
		$this->register_video_section();
		$this->register_seo_section();
		$this->register_card_style_section();
		$this->register_play_style_section();
		$this->register_arrows_style_section();
		$this->register_dots_style_section();
	}

	private function register_items_section() {
		$this->start_controls_section(
			'section_items',
			array( 'label' => esc_html__( 'Testimonials', 'video-testimonials-carousel' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Label', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => esc_html__( 'e.g. Sarah — dentist', 'video-testimonials-carousel' ),
				'description' => esc_html__( 'Editor only. Names this row in the panel and is reused as the image alt text and the play button label for screen readers. Nothing is printed on the card.', 'video-testimonials-carousel' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Card Image', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => Utils::get_placeholder_image_src() ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'video_url',
			array(
				'label'       => esc_html__( 'Video URL', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'input_type'  => 'url',
				'label_block' => true,
				'placeholder' => 'https://youtu.be/… · https://vimeo.com/… · …/clip.mp4',
				'description' => esc_html__( 'Optional. Leave empty for a plain image card with no play behaviour. YouTube, Vimeo and direct media files (mp4, webm, mov…) are detected automatically.', 'video-testimonials-carousel' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'seo_title',
			array(
				'label'       => esc_html__( 'Video Title', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Used for the VideoObject name. Google asks for unique text per video. Falls back to the Label.', 'video-testimonials-carousel' ),
				'condition'   => array( 'video_url!' => '' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'seo_description',
			array(
				'label'       => esc_html__( 'Video Description', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'description' => esc_html__( 'Recommended by Google, and unique per video.', 'video-testimonials-carousel' ),
				'condition'   => array( 'video_url!' => '' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'upload_date',
			array(
				'label'          => esc_html__( 'Upload Date', 'video-testimonials-carousel' ),
				'type'           => Controls_Manager::DATE_TIME,
				'picker_options' => array( 'enableTime' => false ),
				'description'    => esc_html__( 'Required by Google. When the video was first published. Left empty, the page\'s own publish date is used so the markup stays valid, but a real date is better.', 'video-testimonials-carousel' ),
				'condition'      => array( 'video_url!' => '' ),
			)
		);

		$repeater->add_control(
			'duration',
			array(
				'label'       => esc_html__( 'Duration', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '1:23',
				'description' => esc_html__( 'Recommended. Minutes and seconds, like 1:23, or 1:02:30 with hours. Converted to the ISO 8601 form Google expects.', 'video-testimonials-carousel' ),
				'condition'   => array( 'video_url!' => '' ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => esc_html__( 'Cards', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ label || "Testimonial" }}}',
				'default'     => array(
					array( 'label' => esc_html__( 'Testimonial 1', 'video-testimonials-carousel' ) ),
					array( 'label' => esc_html__( 'Testimonial 2', 'video-testimonials-carousel' ) ),
					array( 'label' => esc_html__( 'Testimonial 3', 'video-testimonials-carousel' ) ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'card_image',
				'default'   => 'large',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	private function register_carousel_section() {
		$this->start_controls_section(
			'section_carousel',
			array( 'label' => esc_html__( 'Carousel', 'video-testimonials-carousel' ) )
		);

		$this->add_responsive_control(
			'slides_per_view',
			array(
				'label'          => esc_html__( 'Slides Per View', 'video-testimonials-carousel' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '4',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				// Also drives the pre-init CSS fallback, so there is no layout shift.
				'selectors'      => array( '{{WRAPPER}} .vtc' => '--vtc-spv: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => esc_html__( 'Gap', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'       => esc_html__( 'Infinite Loop', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Ignored when there are not more cards than slides per view.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'   => esc_html__( 'Transition Speed (ms)', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 100,
				'max'     => 3000,
				'step'    => 50,
				'default' => 500,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'     => esc_html__( 'Autoplay', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => esc_html__( 'Autoplay Delay (ms)', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1000,
				'max'       => 15000,
				'step'      => 500,
				'default'   => 4000,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'     => esc_html__( 'Pause On Hover', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'pause_on_interaction',
			array(
				'label'       => esc_html__( 'Stop On Interaction', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Autoplay stops for good once the visitor swipes or uses the arrows.', 'video-testimonials-carousel' ),
				'condition'   => array( 'autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'arrows',
			array(
				'label'     => esc_html__( 'Arrows', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'dots',
			array(
				'label'   => esc_html__( 'Dots', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'grab_cursor',
			array(
				'label'   => esc_html__( 'Grab Cursor', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	private function register_video_section() {
		$this->start_controls_section(
			'section_video',
			array( 'label' => esc_html__( 'Video', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'show_play_icon',
			array(
				'label'       => esc_html__( 'Show Play Button', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Turn off when your card images already have a play button baked in. The cards stay clickable either way.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'revert_on_end',
			array(
				'label'   => esc_html__( 'Return To Image When Video Ends', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_close',
			array(
				'label'   => esc_html__( 'Close Button While Playing', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'embed_fit',
			array(
				'label'       => esc_html__( 'Video Fit', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'contain',
				'options'     => array(
					'contain' => esc_html__( 'Contain (letterbox)', 'video-testimonials-carousel' ),
					'cover'   => esc_html__( 'Cover (crop to fill card)', 'video-testimonials-carousel' ),
				),
				'description' => esc_html__( 'How a video whose shape differs from the card is fitted. Cover crops the edges, contain adds bars.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'heading_self_hosted',
			array(
				'label'     => esc_html__( 'Self-Hosted Video', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'self_controls',
			array(
				'label'   => esc_html__( 'Show Controls', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'self_muted',
			array(
				'label'   => esc_html__( 'Start Muted', 'video-testimonials-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'self_preload',
			array(
				'label'       => esc_html__( 'Preload', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'metadata',
				'options'     => array(
					'none'     => esc_html__( 'None', 'video-testimonials-carousel' ),
					'metadata' => esc_html__( 'Metadata', 'video-testimonials-carousel' ),
					'auto'     => esc_html__( 'Auto', 'video-testimonials-carousel' ),
				),
				'description' => esc_html__( 'Applies only once a card has been clicked. No video data is fetched before that.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'heading_embeds',
			array(
				'label'     => esc_html__( 'YouTube & Vimeo', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'privacy_mode',
			array(
				'label'       => esc_html__( 'Privacy Mode', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Embeds via youtube-nocookie.com and sends Vimeo dnt=1, so no tracking cookies are set.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'minimal_branding',
			array(
				'label'       => esc_html__( 'Minimal Branding', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Hides related videos and annotations, and on Vimeo the title, byline and avatar. YouTube has ignored modestbranding since 2023: to drop its title and channel bar, switch Show Provider Controls off below.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'embed_muted',
			array(
				'label'       => esc_html__( 'Start Muted', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Makes autoplay-after-click reliable on every browser, at the cost of the visitor having to unmute.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'embed_controls',
			array(
				'label'       => esc_html__( 'Show Provider Controls', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Off hides the provider control bar. On YouTube it leaves the title and channel header in place — use Crop Provider Chrome below for that.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'embed_zoom',
			array(
				'label'       => esc_html__( 'Crop Provider Chrome', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min'  => 100,
						'max'  => 160,
						'step' => 1,
					),
				),
				'default'     => array(
					'size' => 100,
					'unit' => '%',
				),
				'description' => esc_html__( 'YouTube gives no way to hide its title, channel name and Shorts watermark. Scaling the player past 100% pushes them outside the card. Around 130% clears both; the trade-off is that the video is zoomed in and loses a little top and bottom. Leave at 100% for Vimeo and self-hosted video, which need no cropping.', 'video-testimonials-carousel' ),
				'selectors'   => array( '{{WRAPPER}} .vtc' => '--vtc-embed-zoom: calc({{SIZE}} / 100);' ),
			)
		);

		$this->end_controls_section();
	}

	private function register_seo_section() {
		$this->start_controls_section(
			'section_seo',
			array( 'label' => esc_html__( 'SEO', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'enable_schema',
			array(
				'label'        => esc_html__( 'VideoObject Schema', 'video-testimonials-carousel' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'description'  => esc_html__( 'Outputs JSON-LD describing each video, so search engines can see videos that only load when a visitor clicks. Turn off if an SEO plugin already outputs video schema for this page, to avoid duplicates.', 'video-testimonials-carousel' ),
			)
		);

		$this->add_control(
			'schema_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Cards with no video URL are never included. A card is skipped if it has no title or no image, since Google treats both as required.', 'video-testimonials-carousel' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'enable_schema' => 'yes' ),
			)
		);

		$this->add_control(
			'eager_images',
			array(
				'label'       => esc_html__( 'Preload First Images', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 6,
				'default'     => 1,
				'description' => esc_html__( 'How many of the first card images load immediately instead of lazily. The first visible card is usually the page\'s largest element, and lazy-loading it delays Largest Contentful Paint. 0 lazy-loads everything.', 'video-testimonials-carousel' ),
			)
		);

		$this->end_controls_section();
	}

	private function register_card_style_section() {
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => esc_html__( 'Card', 'video-testimonials-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'aspect_ratio',
			array(
				'label'     => esc_html__( 'Aspect Ratio', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '9 / 16',
				'options'   => array(
					'9 / 16' => esc_html__( '9:16 Portrait', 'video-testimonials-carousel' ),
					'4 / 5'  => esc_html__( '4:5 Portrait', 'video-testimonials-carousel' ),
					'1 / 1'  => esc_html__( '1:1 Square', 'video-testimonials-carousel' ),
					'16 / 9' => esc_html__( '16:9 Landscape', 'video-testimonials-carousel' ),
				),
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-ratio: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 12,
					'right'    => 12,
					'bottom'   => 12,
					'left'     => 12,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .vtc' => '--vtc-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .vtc__card',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .vtc__card',
			)
		);

		$this->add_control(
			'image_zoom',
			array(
				'label'       => esc_html__( 'Hover Zoom', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '' ),
				'range'       => array(
					'' => array(
						'min'  => 1,
						'max'  => 1.4,
						'step' => 0.01,
					),
				),
				'default'     => array( 'size' => 1.05 ),
				'description' => esc_html__( 'Applies to clickable video cards only.', 'video-testimonials-carousel' ),
				'selectors'   => array( '{{WRAPPER}} .vtc' => '--vtc-zoom: {{SIZE}};' ),
			)
		);

		$this->add_control(
			'overlay_color',
			array(
				'label'     => esc_html__( 'Overlay Tint', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-overlay: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	private function register_play_style_section() {
		$this->start_controls_section(
			'section_style_play',
			array(
				'label'     => esc_html__( 'Play Button', 'video-testimonials-carousel' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_play_icon' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'play_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 12,
						'max' => 120,
					),
				),
				'default'    => array(
					'size' => 28,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-play-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'play_padding',
			array(
				'label'      => esc_html__( 'Padding', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'default'    => array(
					'size' => 18,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-play-pad: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'play_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
					'%'  => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'default'    => array(
					'size' => 50,
					'unit' => '%',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-play-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'play_opacity',
			array(
				'label'      => esc_html__( 'Opacity', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array(
					'' => array(
						'min'  => 0.1,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'default'    => array( 'size' => 1 ),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-play-opacity: {{SIZE}};' ),
			)
		);

		$this->start_controls_tabs( 'tabs_play' );

		$this->start_controls_tab(
			'tab_play_normal',
			array( 'label' => esc_html__( 'Normal', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'play_color',
			array(
				'label'     => esc_html__( 'Icon Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-play-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'play_bg',
			array(
				'label'     => esc_html__( 'Background', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(0, 0, 0, 0.45)',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-play-bg: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_play_hover',
			array( 'label' => esc_html__( 'Hover', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'play_color_hover',
			array(
				'label'     => esc_html__( 'Icon Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-play-color-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'play_bg_hover',
			array(
				'label'     => esc_html__( 'Background', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-play-bg-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'play_scale_hover',
			array(
				'label'      => esc_html__( 'Scale', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array(
					'' => array(
						'min'  => 1,
						'max'  => 1.5,
						'step' => 0.01,
					),
				),
				'default'    => array( 'size' => 1.08 ),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-play-scale: {{SIZE}};' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_arrows_style_section() {
		$this->start_controls_section(
			'section_style_arrows',
			array(
				'label'     => esc_html__( 'Arrows', 'video-testimonials-carousel' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'arrows' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'arrow_box',
			array(
				'label'      => esc_html__( 'Button Size', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 24,
						'max' => 80,
					),
				),
				'default'    => array(
					'size' => 44,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-box: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 40,
					),
				),
				'default'    => array(
					'size' => 18,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'arrow_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
					'%'  => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'default'    => array(
					'size' => 50,
					'unit' => '%',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'arrow_offset',
			array(
				'label'       => esc_html__( 'Horizontal Offset', 'video-testimonials-carousel' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array(
					'px' => array(
						'min' => -60,
						'max' => 60,
					),
				),
				'default'     => array(
					'size' => 8,
					'unit' => 'px',
				),
				'description' => esc_html__( 'Negative values push the arrows outside the carousel.', 'video-testimonials-carousel' ),
				'selectors'   => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-offset: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->start_controls_tabs( 'tabs_arrows' );

		$this->start_controls_tab(
			'tab_arrow_normal',
			array( 'label' => esc_html__( 'Normal', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'arrow_color',
			array(
				'label'     => esc_html__( 'Icon Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_bg',
			array(
				'label'     => esc_html__( 'Background', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(0, 0, 0, 0.5)',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-bg: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_arrow_hover',
			array( 'label' => esc_html__( 'Hover', 'video-testimonials-carousel' ) )
		);

		$this->add_control(
			'arrow_color_hover',
			array(
				'label'     => esc_html__( 'Icon Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-color-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_bg_hover',
			array(
				'label'     => esc_html__( 'Background', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-arrow-bg-hover: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_dots_style_section() {
		$this->start_controls_section(
			'section_style_dots',
			array(
				'label'     => esc_html__( 'Dots', 'video-testimonials-carousel' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'dots' => 'yes' ),
			)
		);

		$this->add_control(
			'dot_size',
			array(
				'label'      => esc_html__( 'Size', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 4,
						'max' => 24,
					),
				),
				'default'    => array(
					'size' => 8,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-dot-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'dot_gap',
			array(
				'label'      => esc_html__( 'Spacing', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 30,
					),
				),
				'default'    => array(
					'size' => 8,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-dot-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'dots_offset',
			array(
				'label'      => esc_html__( 'Distance From Carousel', 'video-testimonials-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .vtc' => '--vtc-dots-offset: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'dot_color',
			array(
				'label'     => esc_html__( 'Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#D0D0D0',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-dot-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'dot_active_color',
			array(
				'label'     => esc_html__( 'Active Color', 'video-testimonials-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1A1A1A',
				'selectors' => array( '{{WRAPPER}} .vtc' => '--vtc-dot-active: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	/**
	 * Per-device value for a responsive control, falling back to the desktop value.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Control name.
	 * @param mixed  $fallback Value to use when nothing is set.
	 * @return array
	 */
	private function responsive_values( $settings, $key, $fallback ) {
		$desktop = isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $fallback;
		$tablet  = isset( $settings[ $key . '_tablet' ] ) && '' !== $settings[ $key . '_tablet' ] ? $settings[ $key . '_tablet' ] : $desktop;
		$mobile  = isset( $settings[ $key . '_mobile' ] ) && '' !== $settings[ $key . '_mobile' ] ? $settings[ $key . '_mobile' ] : $tablet;

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Same as responsive_values(), for SLIDER controls which store ['size' => n].
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Control name.
	 * @param float  $fallback Value to use when nothing is set.
	 * @return array
	 */
	private function responsive_slider_values( $settings, $key, $fallback ) {
		$read = function ( $name ) use ( $settings ) {
			if ( isset( $settings[ $name ]['size'] ) && '' !== $settings[ $name ]['size'] ) {
				return (float) $settings[ $name ]['size'];
			}

			return null;
		};

		$desktop = $read( $key );
		$desktop = null === $desktop ? (float) $fallback : $desktop;

		$tablet = $read( $key . '_tablet' );
		$tablet = null === $tablet ? $desktop : $tablet;

		$mobile = $read( $key . '_mobile' );
		$mobile = null === $mobile ? $tablet : $mobile;

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Resolve the image URL for one repeater item, honouring the Image Size control.
	 *
	 * @param array $item     Repeater item.
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function get_item_image_url( $item, $settings ) {
		$url = '';

		if ( ! empty( $item['image']['id'] ) ) {
			$url = Group_Control_Image_Size::get_attachment_image_src( $item['image']['id'], 'card_image', $settings );
		}

		if ( empty( $url ) && ! empty( $item['image']['url'] ) ) {
			$url = $item['image']['url'];
		}

		return (string) $url;
	}

	/**
	 * The alt text: the item label, else whatever the Media Library holds.
	 *
	 * @param array  $item  Repeater item.
	 * @param string $label Item label.
	 * @return string
	 */
	private function get_item_alt( $item, $label ) {
		if ( '' !== $label ) {
			return $label;
		}

		if ( ! empty( $item['image']['id'] ) ) {
			$alt = get_post_meta( $item['image']['id'], '_wp_attachment_image_alt', true );

			if ( ! empty( $alt ) ) {
				return (string) $alt;
			}
		}

		return '';
	}

	/**
	 * Width and height of the rendered image, so the browser reserves the box
	 * and the card never shifts while loading.
	 *
	 * @param array $item     Repeater item.
	 * @param array $settings Widget settings.
	 * @return array{0:int,1:int} Zero values mean "unknown, omit the attributes".
	 */
	private function get_item_image_dimensions( $item, $settings ) {
		if ( empty( $item['image']['id'] ) ) {
			return array( 0, 0 );
		}

		$size = isset( $settings['card_image_size'] ) ? $settings['card_image_size'] : 'large';

		if ( 'custom' === $size ) {
			return array( 0, 0 );
		}

		$src = wp_get_attachment_image_src( $item['image']['id'], $size );

		if ( ! $src || empty( $src[1] ) || empty( $src[2] ) ) {
			return array( 0, 0 );
		}

		return array( (int) $src[1], (int) $src[2] );
	}

	/**
	 * Full-size image URL for thumbnailUrl — Google prefers the largest available.
	 *
	 * @param array  $item     Repeater item.
	 * @param string $fallback URL to use when there is no attachment.
	 * @return string
	 */
	private function get_item_thumbnail( $item, $fallback ) {
		if ( ! empty( $item['image']['id'] ) ) {
			$full = wp_get_attachment_image_url( $item['image']['id'], 'full' );

			if ( $full ) {
				return $full;
			}
		}

		return $fallback;
	}

	/**
	 * Convert "1:23" or "1:02:30" to the ISO 8601 duration Google expects.
	 *
	 * A value already in ISO form is passed through untouched.
	 *
	 * @param string $value Raw duration.
	 * @return string Empty when nothing usable was given.
	 */
	public static function iso_duration( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '~^P(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+S)?)?$~i', $value ) ) {
			return strtoupper( $value );
		}

		if ( ! preg_match( '~^\d{1,3}(?::[0-5]?\d){0,2}$~', $value ) ) {
			return '';
		}

		$parts = array_reverse( array_map( 'intval', explode( ':', $value ) ) );

		$seconds = isset( $parts[0] ) ? $parts[0] : 0;
		$minutes = isset( $parts[1] ) ? $parts[1] : 0;
		$hours   = isset( $parts[2] ) ? $parts[2] : 0;

		$out = 'PT';

		if ( $hours ) {
			$out .= $hours . 'H';
		}

		if ( $minutes ) {
			$out .= $minutes . 'M';
		}

		if ( $seconds || ( ! $hours && ! $minutes ) ) {
			$out .= $seconds . 'S';
		}

		return $out;
	}

	/**
	 * Upload date as ISO 8601. Falls back to the current post's publish date so
	 * the markup keeps its required property rather than being dropped.
	 *
	 * @param string $value Raw date from the control.
	 * @return string
	 */
	public static function iso_date( $value ) {
		$value = trim( (string) $value );

		if ( '' !== $value ) {
			$timestamp = strtotime( $value );

			if ( $timestamp ) {
				return wp_date( 'c', $timestamp );
			}
		}

		$post_id = get_the_ID();

		if ( $post_id ) {
			$published = get_post_time( 'c', false, $post_id );

			if ( $published ) {
				return $published;
			}
		}

		return wp_date( 'c' );
	}

	/**
	 * Build one VideoObject node, or an empty array when a required property
	 * (name, thumbnailUrl, uploadDate, contentUrl/embedUrl) cannot be supplied.
	 *
	 * @param array $card Prepared card data.
	 * @return array
	 */
	private function build_video_schema( $card ) {
		$video = $card['video'];

		if ( empty( $video['provider'] ) || '' === $card['schema_name'] || '' === $card['thumb'] ) {
			return array();
		}

		$node = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => $card['schema_name'],
			'thumbnailUrl' => $card['thumb'],
			'uploadDate'   => self::iso_date( $card['upload_date'] ),
		);

		if ( 'self' === $video['provider'] ) {
			$node['contentUrl'] = $video['src'];
		} elseif ( 'youtube' === $video['provider'] ) {
			$node['embedUrl'] = 'https://www.youtube.com/embed/' . $video['id'];
		} elseif ( 'vimeo' === $video['provider'] ) {
			$node['embedUrl'] = 'https://player.vimeo.com/video/' . $video['id'];
		}

		if ( '' !== $card['schema_description'] ) {
			$node['description'] = $card['schema_description'];
		}

		$duration = self::iso_duration( $card['duration'] );

		if ( '' !== $duration ) {
			$node['duration'] = $duration;
		}

		return $node;
	}

	private function play_icon_svg() {
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.3-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14z"/></svg>';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items    = ! empty( $settings['items'] ) ? $settings['items'] : array();

		if ( empty( $items ) ) {
			return;
		}

		$spv  = $this->responsive_values( $settings, 'slides_per_view', '4' );
		$gaps = $this->responsive_slider_values( $settings, 'gap', 16 );

		$cards = array();

		foreach ( $items as $item ) {
			$image_url = $this->get_item_image_url( $item, $settings );

			if ( '' === $image_url ) {
				continue;
			}

			$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
			$title = isset( $item['seo_title'] ) ? trim( (string) $item['seo_title'] ) : '';

			list( $width, $height ) = $this->get_item_image_dimensions( $item, $settings );

			$cards[] = array(
				'image'              => $image_url,
				'thumb'              => $this->get_item_thumbnail( $item, $image_url ),
				'width'              => $width,
				'height'             => $height,
				'alt'                => $this->get_item_alt( $item, $label ),
				'label'              => $label,
				'schema_name'        => '' !== $title ? $title : $label,
				'schema_description' => isset( $item['seo_description'] ) ? trim( (string) $item['seo_description'] ) : '',
				'upload_date'        => isset( $item['upload_date'] ) ? (string) $item['upload_date'] : '',
				'duration'           => isset( $item['duration'] ) ? (string) $item['duration'] : '',
				'video'              => self::detect_video( isset( $item['video_url'] ) ? $item['video_url'] : '' ),
			);
		}

		if ( empty( $cards ) ) {
			return;
		}

		$config = array(
			'count'              => count( $cards ),
			'slidesPerView'      => array(
				'desktop' => (float) $spv['desktop'],
				'tablet'  => (float) $spv['tablet'],
				'mobile'  => (float) $spv['mobile'],
			),
			'gap'                => $gaps,
			'loop'               => 'yes' === $settings['loop'],
			'speed'              => (int) $settings['speed'],
			'grabCursor'         => 'yes' === $settings['grab_cursor'],
			'arrows'             => 'yes' === $settings['arrows'],
			'dots'               => 'yes' === $settings['dots'],
			'autoplay'           => 'yes' === $settings['autoplay'],
			'autoplayDelay'      => (int) $settings['autoplay_delay'],
			'pauseOnHover'       => 'yes' === $settings['pause_on_hover'],
			'pauseOnInteraction' => 'yes' === $settings['pause_on_interaction'],
			'revertOnEnd'        => 'yes' === $settings['revert_on_end'],
			'selfControls'       => 'yes' === $settings['self_controls'],
			'selfMuted'          => 'yes' === $settings['self_muted'],
			'selfPreload'        => (string) $settings['self_preload'],
			'privacy'            => 'yes' === $settings['privacy_mode'],
			'minimalBranding'    => 'yes' === $settings['minimal_branding'],
			'embedMuted'         => 'yes' === $settings['embed_muted'],
			'embedControls'      => 'yes' === $settings['embed_controls'],
		);

		$show_play  = 'yes' === $settings['show_play_icon'];
		$show_close = 'yes' === $settings['show_close'];
		$eager      = isset( $settings['eager_images'] ) ? (int) $settings['eager_images'] : 1;
		$index      = 0;
		?>
		<div class="vtc" data-vtc="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" data-fit="<?php echo esc_attr( $settings['embed_fit'] ); ?>"
			role="region" aria-roledescription="carousel"
			aria-label="<?php echo esc_attr__( 'Video testimonials', 'video-testimonials-carousel' ); ?>">
			<div class="vtc__swiper swiper swiper-container">
				<div class="swiper-wrapper">
					<?php foreach ( $cards as $card ) : ?>
						<?php
						$video    = $card['video'];
						$is_video = ! empty( $video['provider'] );

						$classes = 'vtc__card' . ( $is_video ? ' vtc__card--video' : '' );

						// Kept unescaped here; escaped once at the attribute below.
						$aria = '';

						if ( $is_video ) {
							$aria = '' !== $card['label']
								/* translators: %s: testimonial label. */
								? sprintf( __( 'Play video: %s', 'video-testimonials-carousel' ), $card['label'] )
								: __( 'Play video', 'video-testimonials-carousel' );
						}
						?>
						<div class="swiper-slide vtc__slide">
							<div
								class="<?php echo esc_attr( $classes ); ?>"
								<?php if ( $is_video ) : ?>
									role="button"
									tabindex="0"
									aria-label="<?php echo esc_attr( $aria ); ?>"
									data-provider="<?php echo esc_attr( $video['provider'] ); ?>"
									<?php if ( 'self' === $video['provider'] ) : ?>
										data-src="<?php echo esc_url( $video['src'] ); ?>"
									<?php else : ?>
										data-id="<?php echo esc_attr( $video['id'] ); ?>"
										<?php if ( ! empty( $video['hash'] ) ) : ?>
											data-hash="<?php echo esc_attr( $video['hash'] ); ?>"
										<?php endif; ?>
									<?php endif; ?>
								<?php endif; ?>
							>
								<?php
								// The first visible card is usually the LCP element, and a
								// lazy-loaded LCP image measurably delays it.
								$is_eager = $index < $eager;
								++$index;
								?>
								<img
									class="vtc__img"
									src="<?php echo esc_url( $card['image'] ); ?>"
									alt="<?php echo esc_attr( $card['alt'] ); ?>"
									<?php if ( $card['width'] && $card['height'] ) : ?>
										width="<?php echo esc_attr( $card['width'] ); ?>"
										height="<?php echo esc_attr( $card['height'] ); ?>"
									<?php endif; ?>
									loading="<?php echo $is_eager ? 'eager' : 'lazy'; ?>"
									<?php if ( 1 === $index && $is_eager ) : ?>
										fetchpriority="high"
									<?php endif; ?>
									decoding="async"
								/>

								<?php if ( $is_video && $show_play ) : ?>
									<span class="vtc__play" aria-hidden="true"><?php echo $this->play_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<?php endif; ?>

								<?php if ( $is_video ) : ?>
									<div class="vtc__player"></div>
									<?php if ( $show_close ) : ?>
										<button type="button" class="vtc__close" aria-label="<?php echo esc_attr__( 'Close video', 'video-testimonials-carousel' ); ?>" hidden>
											<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
										</button>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( 'yes' === $settings['arrows'] ) : ?>
				<button type="button" class="vtc__arrow vtc__arrow--prev" aria-label="<?php echo esc_attr__( 'Previous', 'video-testimonials-carousel' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>
				</button>
				<button type="button" class="vtc__arrow vtc__arrow--next" aria-label="<?php echo esc_attr__( 'Next', 'video-testimonials-carousel' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>
				</button>
			<?php endif; ?>

			<?php if ( 'yes' === $settings['dots'] ) : ?>
				<div class="vtc__dots swiper-pagination"></div>
			<?php endif; ?>
		</div>
		<?php
		$this->render_schema( $cards, 'yes' === $settings['enable_schema'] );
	}

	/**
	 * Emit VideoObject JSON-LD for the cards that carry a video.
	 *
	 * The players are only built on click, so without this the videos are
	 * invisible to search engines.
	 *
	 * @param array $cards   Prepared cards.
	 * @param bool  $enabled Whether the schema control is on.
	 */
	private function render_schema( $cards, $enabled ) {
		if ( ! $enabled ) {
			return;
		}

		$nodes = array();

		foreach ( $cards as $card ) {
			$node = $this->build_video_schema( $card );

			if ( ! empty( $node ) ) {
				$nodes[] = $node;
			}
		}

		if ( empty( $nodes ) ) {
			return;
		}

		// JSON_HEX_TAG turns < and > into escapes, so no value can close the script tag.
		$json = wp_json_encode(
			1 === count( $nodes ) ? $nodes[0] : $nodes,
			JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( false === $json ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD, escaped above.
	}
}

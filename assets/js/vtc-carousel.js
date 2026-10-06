/**
 * Video Testimonials Carousel
 *
 * Builds the Swiper instance and creates a player inside a card on click.
 * Nothing video-related exists in the DOM until then, and cards without a
 * video URL carry no behaviour at all.
 */
( function () {
	'use strict';

	var YT_ORIGINS = [ 'https://www.youtube.com', 'https://www.youtube-nocookie.com' ];
	var VIMEO_ORIGIN = 'https://player.vimeo.com';

	/**
	 * The one card currently playing, anywhere on the page.
	 *
	 * @type {{card: Element, root: Element, node: Element, onMessage: Function|null, resumeAutoplay: boolean}|null}
	 */
	var active = null;

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function parseConfig( root ) {
		try {
			return JSON.parse( root.getAttribute( 'data-vtc' ) || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function swiperMajorVersion() {
		var version = window.Swiper && window.Swiper.version ? String( window.Swiper.version ) : '';

		return parseInt( version.split( '.' )[ 0 ], 10 ) || 0;
	}

	/* ------------------------------------------------------------------
	 * Carousel
	 * --------------------------------------------------------------- */

	/**
	 * Read the layout from the CSS custom properties Elementor generates.
	 *
	 * Elementor already emits --vtc-spv and --vtc-gap inside media queries built
	 * from the site's own breakpoints, including any custom ones. Reading them
	 * back is both simpler and more correct than rebuilding that breakpoint map
	 * in JavaScript, and it keeps the pre-init CSS layout and the Swiper layout
	 * driven by a single source of truth.
	 *
	 * @param {Element} root         The .vtc element.
	 * @param {number}  fallbackSpv  Slides per view if the property is missing.
	 * @param {number}  fallbackGap  Gap if the property is missing.
	 * @return {{spv: number, gap: number}}
	 */
	function readLayout( root, fallbackSpv, fallbackGap ) {
		var styles = window.getComputedStyle( root );
		var spv = parseFloat( styles.getPropertyValue( '--vtc-spv' ) );
		var gap = parseFloat( styles.getPropertyValue( '--vtc-gap' ) );

		return {
			spv: spv > 0 ? spv : fallbackSpv,
			gap: gap >= 0 ? gap : fallbackGap
		};
	}

	/**
	 * Fallback values from the widget config, used only when the CSS properties
	 * are unavailable.
	 *
	 * @param {Object} cfg Widget config.
	 * @return {{spv: number, gap: number}}
	 */
	function configFallback( cfg ) {
		var spv = cfg.slidesPerView || {};
		var gap = cfg.gap || {};

		return {
			spv: spv.desktop || 4,
			gap: typeof gap.desktop === 'number' ? gap.desktop : 16
		};
	}

	/**
	 * Re-read the layout after a resize or an editor device switch.
	 *
	 * @param {Element} root The .vtc element.
	 */
	function syncLayout( root ) {
		var swiper = root.vtcSwiper;

		if ( ! swiper || swiper.destroyed ) {
			return;
		}

		var fallback = configFallback( root.vtcCfg || {} );
		var layout = readLayout( root, fallback.spv, fallback.gap );

		// The container can change width without the window doing so: arrows
		// moving outside reserve a gutter, the editor re-renders, a parent column
		// resizes, a tab or accordion reveals the carousel. Swiper does not
		// re-measure on its own in those cases, which leaves slides at their old
		// width inside a narrower frame.
		var width = swiper.el ? Math.round( swiper.el.getBoundingClientRect().width ) : 0;

		if (
			swiper.params.slidesPerView === layout.spv &&
			swiper.params.spaceBetween === layout.gap &&
			root.vtcWidth === width
		) {
			return;
		}

		root.vtcWidth = width;
		swiper.params.slidesPerView = layout.spv;
		swiper.params.spaceBetween = layout.gap;
		swiper.update();
	}

	function createSwiper( root, cfg ) {
		var el = root.querySelector( '.vtc__swiper' );

		if ( ! el || typeof window.Swiper !== 'function' ) {
			return null;
		}

		var spv = cfg.slidesPerView || {};
		var count = cfg.count || el.querySelectorAll( '.swiper-slide' ).length;

		// The widest any breakpoint goes decides whether looping is worth it at all.
		var widest = Math.max( spv.desktop || 4, spv.tablet || 0, spv.mobile || 0 );

		var fallback = configFallback( cfg );
		var layout = readLayout( root, fallback.spv, fallback.gap );

		var options = {
			slidesPerView: layout.spv,
			spaceBetween: layout.gap,
			// Looping with no more slides than fit leaves Swiper with blank space.
			loop: !! cfg.loop && count > widest,
			speed: cfg.speed || 500,
			grabCursor: !! cfg.grabCursor,
			watchOverflow: true,
			a11y: { enabled: true }
		};

		if ( cfg.dots ) {
			var dots = root.querySelector( '.vtc__dots' );

			if ( dots ) {
				options.pagination = {
					el: dots,
					clickable: true
				};
			}
		}

		if ( cfg.arrows ) {
			var next = root.querySelector( '.vtc__arrow--next' );
			var prev = root.querySelector( '.vtc__arrow--prev' );

			if ( next && prev ) {
				options.navigation = {
					nextEl: next,
					prevEl: prev
				};
			}
		}

		if ( cfg.autoplay && ! prefersReducedMotion() ) {
			options.autoplay = {
				delay: cfg.autoplayDelay || 4000,
				disableOnInteraction: !! cfg.pauseOnInteraction,
				pauseOnMouseEnter: !! cfg.pauseOnHover
			};
		}

		var swiper = new window.Swiper( el, options );

		// pauseOnMouseEnter landed in Swiper 6.6; do it by hand on older copies.
		// Some bundles do not expose Swiper.version at all — those are modern ones,
		// so an unknown version is left to pauseOnMouseEnter rather than shimmed.
		var major = swiperMajorVersion();

		if ( options.autoplay && cfg.pauseOnHover && major > 0 && major < 6 ) {
			el.addEventListener( 'mouseenter', function () {
				if ( ! active && swiper.autoplay ) {
					swiper.autoplay.stop();
				}
			} );
			el.addEventListener( 'mouseleave', function () {
				if ( ! active && swiper.autoplay ) {
					swiper.autoplay.start();
				}
			} );
		}

		return swiper;
	}

	/* ------------------------------------------------------------------
	 * Players
	 * --------------------------------------------------------------- */

	/**
	 * iPhone or iPad, including an iPad reporting itself as a Mac, which it has
	 * done since iPadOS 13 — hence the touch-point check rather than the UA alone.
	 *
	 * @return {boolean}
	 */
	function isAppleTouchDevice() {
		var ua = navigator.userAgent || '';

		if ( /iPad|iPhone|iPod/.test( ua ) ) {
			return true;
		}

		return /Mac/.test( ua ) && navigator.maxTouchPoints > 1;
	}

	/**
	 * iOS grants sound only to a gesture made on the player itself, and a tap on
	 * our card is not a gesture inside a cross-origin iframe. So asking for
	 * autoplay there buys muted playback. Skipping autoplay leaves the provider's
	 * own play button, and that tap plays with sound.
	 *
	 * @param {Object} cfg Widget config.
	 * @return {boolean}
	 */
	function shouldAutoplayEmbed( cfg ) {
		return ! ( 'tap' === cfg.iosEmbed && isAppleTouchDevice() );
	}

	function buildYouTube( card, cfg ) {
		var id = card.getAttribute( 'data-id' );
		var host = cfg.privacy ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';

		var params = [
			'autoplay=' + ( shouldAutoplayEmbed( cfg ) ? '1' : '0' ),
			'playsinline=1',
			'enablejsapi=1',
			'controls=' + ( cfg.embedControls ? '1' : '0' ),
			'mute=' + ( cfg.embedMuted ? '1' : '0' ),
			'origin=' + encodeURIComponent( window.location.origin )
		];

		if ( cfg.minimalBranding ) {
			params.push( 'rel=0', 'modestbranding=1', 'iv_load_policy=3' );
		}

		var iframe = document.createElement( 'iframe' );

		iframe.src = host + '/embed/' + encodeURIComponent( id ) + '?' + params.join( '&' );
		iframe.title = card.getAttribute( 'aria-label' ) || 'YouTube video';
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'frameborder', '0' );

		return iframe;
	}

	function buildVimeo( card, cfg ) {
		var id = card.getAttribute( 'data-id' );
		var hash = card.getAttribute( 'data-hash' );

		var params = [
			'autoplay=' + ( shouldAutoplayEmbed( cfg ) ? '1' : '0' ),
			'playsinline=1',
			'controls=' + ( cfg.embedControls ? '1' : '0' ),
			'muted=' + ( cfg.embedMuted ? '1' : '0' )
		];

		if ( cfg.privacy ) {
			params.push( 'dnt=1' );
		}

		if ( cfg.minimalBranding ) {
			params.push( 'title=0', 'byline=0', 'portrait=0' );
		}

		if ( hash ) {
			params.push( 'h=' + encodeURIComponent( hash ) );
		}

		var iframe = document.createElement( 'iframe' );

		iframe.src = VIMEO_ORIGIN + '/video/' + encodeURIComponent( id ) + '?' + params.join( '&' );
		iframe.title = card.getAttribute( 'aria-label' ) || 'Vimeo video';
		iframe.allow = 'autoplay; fullscreen; picture-in-picture';
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'frameborder', '0' );

		return iframe;
	}

	/**
	 * Build the element only. Playback is started after it is in the document,
	 * because iOS refuses to play a detached media element and then our retry
	 * would quietly mute it.
	 */
	function buildSelfHosted( card, cfg, onEnded ) {
		var video = document.createElement( 'video' );
		var poster = card.querySelector( '.vtc__img' );

		video.src = card.getAttribute( 'data-src' );
		video.setAttribute( 'playsinline', '' );
		video.playsInline = true;
		video.preload = cfg.selfPreload || 'metadata';

		if ( cfg.selfControls ) {
			video.controls = true;
		}

		if ( cfg.selfMuted ) {
			video.muted = true;
		}

		if ( poster && poster.currentSrc ) {
			video.poster = poster.currentSrc;
		}

		if ( onEnded ) {
			video.addEventListener( 'ended', onEnded );
		}

		return video;
	}

	/**
	 * Start a self-hosted video, once it is in the document.
	 *
	 * Must stay synchronous with the click that triggered it: iOS only allows
	 * sound when play() comes directly out of the gesture handler.
	 *
	 * @param {HTMLVideoElement} video The video element.
	 */
	function startSelfHosted( video ) {
		var attempt = video.play();

		if ( ! attempt || typeof attempt.catch !== 'function' ) {
			return;
		}

		attempt.catch( function () {
			// Refused even with the gesture. Muting is the only thing that
			// reliably gets a play through, so it beats a dead card.
			video.muted = true;

			var retry = video.play();

			if ( retry && typeof retry.catch === 'function' ) {
				retry.catch( function () {} );
			}
		} );
	}

	/**
	 * Listen for "video finished" without loading either provider's SDK.
	 *
	 * Best-effort: if a provider stops sending these messages the card simply stays
	 * on the player, which is what the close button is for.
	 *
	 * @param {string}   provider youtube|vimeo
	 * @param {Element}  iframe   The player iframe.
	 * @param {Function} onEnded  Called when the video reports it finished.
	 * @return {Function} The message handler, so it can be removed later.
	 */
	function listenForEnd( provider, iframe, onEnded ) {
		var origins = 'youtube' === provider ? YT_ORIGINS : [ VIMEO_ORIGIN ];
		var target = iframe.src.indexOf( 'youtube-nocookie' ) !== -1
			? 'https://www.youtube-nocookie.com'
			: ( 'youtube' === provider ? 'https://www.youtube.com' : VIMEO_ORIGIN );

		function handler( event ) {
			if ( origins.indexOf( event.origin ) === -1 ) {
				return;
			}

			if ( ! iframe.contentWindow || event.source !== iframe.contentWindow ) {
				return;
			}

			var data = event.data;

			if ( typeof data === 'string' ) {
				try {
					data = JSON.parse( data );
				} catch ( e ) {
					return;
				}
			}

			if ( ! data ) {
				return;
			}

			if ( 'youtube' === provider ) {
				// playerState 0 === ended.
				if ( data.info && 0 === data.info.playerState ) {
					onEnded();
				}

				return;
			}

			if ( 'ended' === data.event || 'finish' === data.event ) {
				onEnded();
			}
		}

		window.addEventListener( 'message', handler );

		iframe.addEventListener( 'load', function () {
			if ( ! iframe.contentWindow ) {
				return;
			}

			if ( 'youtube' === provider ) {
				iframe.contentWindow.postMessage(
					JSON.stringify( { event: 'listening', id: 'vtc' } ),
					target
				);

				return;
			}

			iframe.contentWindow.postMessage(
				JSON.stringify( { method: 'addEventListener', value: 'ended' } ),
				target
			);
		} );

		return handler;
	}

	/* ------------------------------------------------------------------
	 * Play / pause / stop
	 * --------------------------------------------------------------- */

	/**
	 * Below this much of the card still showing, the video counts as gone.
	 */
	var OFFSCREEN_THRESHOLD = 0.25;

	/**
	 * Drive the active player without loading either vendor SDK. YouTube needs
	 * enablejsapi=1, which the embed already carries.
	 *
	 * @param {Element} node     The iframe or video element.
	 * @param {string}  provider youtube|vimeo|self
	 * @param {string}  command  "pause" or "play"
	 */
	function playerCommand( node, provider, command ) {
		if ( ! node ) {
			return;
		}

		if ( 'VIDEO' === node.tagName ) {
			if ( 'pause' === command ) {
				try {
					node.pause();
				} catch ( e ) {}

				return;
			}

			var resumed = node.play();

			if ( resumed && typeof resumed.catch === 'function' ) {
				resumed.catch( function () {} );
			}

			return;
		}

		if ( ! node.contentWindow ) {
			return;
		}

		var origin = 'youtube' === provider
			? ( node.src.indexOf( 'youtube-nocookie' ) !== -1 ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com' )
			: VIMEO_ORIGIN;

		var message = 'youtube' === provider
			? { event: 'command', func: 'pause' === command ? 'pauseVideo' : 'playVideo', args: [] }
			: { method: 'pause' === command ? 'pause' : 'play' };

		node.contentWindow.postMessage( JSON.stringify( message ), origin );
	}

	function pauseActive() {
		if ( ! active || active.paused ) {
			return;
		}

		playerCommand( active.node, active.provider, 'pause' );
		active.paused = true;
		active.card.classList.add( 'is-paused' );
	}

	/**
	 * @param {Element} card The card clicked.
	 * @return {boolean} True when this click resumed a paused video.
	 */
	function resumeCard( card ) {
		if ( ! active || active.card !== card || ! active.paused ) {
			return false;
		}

		playerCommand( active.node, active.provider, 'play' );
		active.paused = false;
		card.classList.remove( 'is-paused' );

		return true;
	}

	/**
	 * Stop or pause the video once its card is mostly off screen — swiped past
	 * inside the carousel, or scrolled off the page. IntersectionObserver covers
	 * both, because it accounts for the carousel's own overflow clipping.
	 *
	 * @param {Element} card The playing card.
	 * @param {string}  mode stop|pause|none
	 * @return {IntersectionObserver|null}
	 */
	function watchVisibility( card, mode ) {
		if ( 'none' === mode || typeof window.IntersectionObserver !== 'function' ) {
			return null;
		}

		var observer = new window.IntersectionObserver(
			function ( entries ) {
				var entry = entries[ entries.length - 1 ];

				if ( ! entry || entry.intersectionRatio >= OFFSCREEN_THRESHOLD ) {
					return;
				}

				if ( 'pause' === mode ) {
					pauseActive();

					return;
				}

				stopActive( false );
			},
			{ threshold: [ 0, OFFSCREEN_THRESHOLD ] }
		);

		observer.observe( card );

		return observer;
	}

	function stopActive( returnFocus ) {
		if ( ! active ) {
			return;
		}

		var current = active;

		active = null;

		if ( current.onMessage ) {
			window.removeEventListener( 'message', current.onMessage );
		}

		if ( current.observer ) {
			current.observer.disconnect();
		}

		if ( current.node ) {
			if ( 'VIDEO' === current.node.tagName ) {
				try {
					current.node.pause();
				} catch ( e ) {}

				current.node.removeAttribute( 'src' );
			}

			// Removing the node is what actually tears down the player and its
			// network activity.
			if ( current.node.parentNode ) {
				current.node.parentNode.removeChild( current.node );
			}
		}

		if ( current.card ) {
			current.card.classList.remove( 'is-playing' );
			current.card.classList.remove( 'is-paused' );

			var close = current.card.querySelector( '.vtc__close' );

			if ( close ) {
				close.hidden = true;
			}

			if ( returnFocus && document.contains( current.card ) ) {
				current.card.focus();
			}
		}

		if ( current.resumeAutoplay && current.root && current.root.vtcSwiper ) {
			var swiper = current.root.vtcSwiper;

			if ( swiper.autoplay && ! swiper.destroyed ) {
				swiper.autoplay.start();
			}
		}
	}

	function play( card, root ) {
		var cfg = root.vtcCfg || {};
		var holder = card.querySelector( '.vtc__player' );
		var provider = card.getAttribute( 'data-provider' );

		if ( ! holder || ! provider ) {
			return;
		}

		// One video at a time, page-wide.
		stopActive( false );

		var onEnded = function () {
			if ( cfg.revertOnEnd ) {
				stopActive( false );
			}
		};

		var node;
		var onMessage = null;

		if ( 'self' === provider ) {
			node = buildSelfHosted( card, cfg, onEnded );
		} else if ( 'youtube' === provider ) {
			node = buildYouTube( card, cfg );
		} else if ( 'vimeo' === provider ) {
			node = buildVimeo( card, cfg );
		} else {
			return;
		}

		holder.appendChild( node );

		if ( 'self' === provider ) {
			startSelfHosted( node );
		}

		if ( 'self' !== provider && cfg.revertOnEnd ) {
			onMessage = listenForEnd( provider, node, onEnded );
		}

		card.classList.add( 'is-playing' );

		var close = card.querySelector( '.vtc__close' );

		if ( close ) {
			close.hidden = false;
		}

		var resumeAutoplay = false;
		var swiper = root.vtcSwiper;

		if ( swiper && swiper.autoplay && swiper.autoplay.running ) {
			swiper.autoplay.stop();
			resumeAutoplay = true;
		}

		active = {
			card: card,
			root: root,
			node: node,
			provider: provider,
			paused: false,
			onMessage: onMessage,
			observer: null,
			resumeAutoplay: resumeAutoplay
		};

		active.observer = watchVisibility( card, cfg.offscreen || 'stop' );
	}

	/* ------------------------------------------------------------------
	 * Binding
	 * --------------------------------------------------------------- */

	function bindCards( root ) {
		var wrapper = root.querySelector( '.swiper-wrapper' );

		if ( ! wrapper ) {
			return;
		}

		var down = null;

		wrapper.addEventListener(
			'pointerdown',
			function ( event ) {
				down = { x: event.clientX, y: event.clientY };
			},
			{ passive: true }
		);

		wrapper.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			if ( target.closest( '.vtc__close' ) ) {
				stopActive( true );

				return;
			}

			var card = target.closest( '.vtc__card--video' );

			if ( ! card ) {
				return;
			}

			// A swipe ends in a click too; only a tap should open a video.
			if ( down && Math.sqrt( Math.pow( event.clientX - down.x, 2 ) + Math.pow( event.clientY - down.y, 2 ) ) > 8 ) {
				return;
			}

			if ( card.classList.contains( 'is-playing' ) ) {
				// A card paused by scrolling away resumes on the next click.
				resumeCard( card );

				return;
			}

			play( card, root );
		} );

		wrapper.addEventListener( 'keydown', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			var card = target.closest( '.vtc__card--video' );

			if ( ! card || target.closest( '.vtc__close' ) ) {
				return;
			}

			if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
				event.preventDefault();

				if ( card.classList.contains( 'is-playing' ) ) {
					resumeCard( card );
				} else {
					play( card, root );
				}
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Init
	 * --------------------------------------------------------------- */

	function initRoot( root ) {
		if ( ! root || root.hasAttribute( 'data-vtc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-vtc-ready', '' );

		// An editor re-render can replace the card that was playing.
		if ( active && ! document.contains( active.card ) ) {
			active = null;
		}

		var cfg = parseConfig( root );

		root.vtcCfg = cfg;
		root.vtcSwiper = createSwiper( root, cfg );

		bindCards( root );

		// Fonts and late CSS can change the computed properties after init.
		syncLayout( root );

		// Catches every container resize, including the ones no window event
		// reports. The work is deferred to the next frame rather than done inside
		// the callback: re-measuring resizes the slides, which would resize the
		// thing being observed, and the browser then drops notifications to break
		// the loop — leaving slides at a stale width about two times in three.
		if ( typeof window.ResizeObserver === 'function' && root.vtcSwiper && root.vtcSwiper.el ) {
			var pending = false;

			root.vtcResizeObserver = new window.ResizeObserver( function () {
				if ( pending ) {
					return;
				}

				pending = true;

				window.requestAnimationFrame( function () {
					pending = false;
					syncLayout( root );
				} );
			} );

			root.vtcResizeObserver.observe( root.vtcSwiper.el );
		}
	}

	var resizeTimer = null;

	function onViewportChange() {
		window.clearTimeout( resizeTimer );

		resizeTimer = window.setTimeout( function () {
			Array.prototype.forEach.call(
				document.querySelectorAll( '.vtc[data-vtc-ready]' ),
				syncLayout
			);
		}, 100 );
	}

	window.addEventListener( 'resize', onViewportChange );
	window.addEventListener( 'orientationchange', onViewportChange );

	// A hidden tab runs no animation frames, so a resize that happened while it
	// was in the background has not been applied yet when it comes back.
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			onViewportChange();
		}
	} );

	function initAll( scope ) {
		var context = scope && scope.querySelectorAll ? scope : document;
		var roots = context.querySelectorAll( '.vtc' );

		Array.prototype.forEach.call( roots, initRoot );

		// element_ready hands us the widget wrapper, which may be the root's parent
		// or the root itself depending on the Elementor version.
		if ( scope && scope.classList && scope.classList.contains( 'vtc' ) ) {
			initRoot( scope );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}

	window.addEventListener( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/video_testimonials_carousel.default',
			function ( scope ) {
				var el = scope && scope[ 0 ] ? scope[ 0 ] : scope;

				initAll( el );
			}
		);
	} );
}() );

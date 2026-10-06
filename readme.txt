=== Video Testimonials Carousel for Elementor ===
Contributors: mehdibouchami
Tags: elementor, carousel, testimonials, video, slider
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A testimonial carousel where video is optional per card. Players are built only on click, so nothing loads until a visitor asks for it.

== Description ==

**Elementor is required.** The free Elementor plugin, version 3.5 or newer, must be installed and active; Elementor Pro is not needed. WordPress will enforce this for you on 6.5 and above, and the plugin stays inert with an admin notice otherwise.

An Elementor widget that shows testimonial cards in a carousel. Each card is an image, and **a video is optional**: paste a URL and the card becomes clickable, leave it empty and the card stays a plain image with no play button and no click behaviour.

No iframe or video element is written to the page on load, for any card. Nothing is requested from YouTube, Vimeo or your own server until a visitor clicks a card, which keeps the page fast and sets no third-party cookies before playback.

The provider is detected from the URL, so there is no provider setting to maintain:

* YouTube: `watch?v=`, `youtu.be/`, Shorts, `/embed/`, `/live/`
* Vimeo: standard links, player links, and unlisted links carrying a privacy hash
* Self-hosted: any direct `.mp4`, `.m4v`, `.webm`, `.ogv`, `.ogg` or `.mov` file, including the Media Library

A URL that matches none of these degrades to a plain image card rather than breaking.

= Search engines =

Because the players are only built on click, the videos would otherwise be invisible to search engines. The widget outputs **VideoObject JSON-LD** for every card that has a video, carrying the name, thumbnail, upload date, duration, description and the embed or content URL. It can be switched off in one click if your SEO plugin already handles video schema.

= Playback behaviour =

* Only one video plays at a time, anywhere on the page
* A video stops when its card is swiped past or scrolled off the screen, so nothing keeps talking out of sight. It can pause and resume instead, or be left playing
* Carousel autoplay pauses during playback and resumes afterwards
* The card returns to its image when the video ends
* A close button sits over the player
* Swiping never triggers playback; a tap does

= Performance and accessibility =

* Zero video payload until click
* The first card image can load eagerly with high fetch priority, so it does not hold back Largest Contentful Paint
* Images carry width and height, so cards do not shift while loading
* Video cards are keyboard operable with Enter and Space, and carry screen-reader labels
* Cards without a video are not focusable and carry no behaviour at all
* Honours `prefers-reduced-motion`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip through Plugins → Add New → Upload Plugin.
2. Activate it. Elementor must be active; the plugin shows a notice and stays inert otherwise.
3. Edit a page with Elementor and search the widget panel for "Video Testimonials Carousel".

== Frequently Asked Questions ==

= Does every card need a video? =

No. That is the point of the widget. A card with no video URL renders as a plain image: no play button, no pointer cursor, no click behaviour, and nothing in the structured data.

= Do I have to choose YouTube, Vimeo or self-hosted? =

No. The provider is worked out from the URL you paste.

= Does anything load from YouTube or Vimeo before a visitor clicks? =

No. No iframe exists until the click, so no request and no cookie. With Privacy Mode on, playback then goes through youtube-nocookie.com and sends Vimeo `dnt=1`.

= Can I remove the YouTube title and channel name from the player? =

Not through any YouTube setting. `modestbranding` has been ignored since 2023 and `showinfo` was removed in 2018, and `controls=0` hides only the buttons. The widget offers Crop Provider Chrome, which scales the player past the card so the header and the Shorts watermark fall outside it; around 130% clears both, at the cost of a slightly zoomed video. Self-hosted video has no branding at all.

= Will Google index videos that only load on click? =

Google's video guidance asks you not to rely on user actions to load a video, so a click-to-play card is not ideal for video indexing on its own. That is exactly why the widget emits VideoObject structured data: it declares each video, its thumbnail and its embed URL independently of the DOM. Fill in a real upload date and duration per card for the best chance of rich results.

= Is Elementor Pro required? =

No. The free Elementor plugin, version 3.5 or newer, is enough.

== Screenshots ==

1. A row of portrait testimonial cards, some with a play button and some without.
2. A card playing a video in place, with the close button.
3. The widget's Content settings, including the per-card video URL and SEO fields.
4. The SEO section, with VideoObject schema and image preloading.

== Changelog ==

= 1.5.0 =
* Added Video → On iPhone And iPad. Set it to "Wait for one tap" to keep the sound on YouTube and Vimeo: iOS only grants sound to a tap on the player itself, so starting the embed automatically can only ever play muted.
* Added Focus colours for the arrows, applied on keyboard focus only.

= 1.4.0 =
* Added an Active colour and background for the arrows, shown while an arrow is pressed.
* Fixed the arrows keeping their hover colour after a tap on touch screens. Hover styling is now limited to devices that actually have a pointer, which also stops the card zoom and the close button sticking.
* Fixed self-hosted video sometimes playing muted on iPhone. Playback was being started before the video element was in the page, which iOS refuses, and the fallback then muted it to get it playing at all.

= 1.3.0 =
* A playing video now stops when its card is swiped past or scrolled out of view, instead of carrying on unseen. Choose Pause or Keep playing instead under Video → When Scrolled Out Of View.
* Measured from how much of the card is actually visible, so it behaves correctly at any number of slides per view.

= 1.2.0 =
* Added VideoObject JSON-LD for every card that has a video, with per-card title, description, upload date and duration fields.
* Added eager loading and high fetch priority for the first card images, so the carousel does not delay Largest Contentful Paint.
* Images now carry width and height attributes.
* The carousel is exposed to assistive technology as a labelled carousel region.
* Renamed to "Video Testimonials Carousel for Elementor".

= 1.1.1 =
* Fixed slides per view ignoring the tablet and mobile values and falling back to the desktop count. The layout now follows the CSS custom properties Elementor generates, so custom breakpoints work too.

= 1.1.0 =
* Added Crop Provider Chrome, the only reliable way to hide YouTube's title, channel name and Shorts watermark.
* Added "Report an issue" and "Support the developer" links on the Plugins screen.

= 1.0.1 =
* Fixed YouTube URLs never being detected, which made those cards render as plain images.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.5.0 =
Adds an iPhone and iPad option that keeps the sound on YouTube and Vimeo, and keyboard focus colours for the arrows.

= 1.4.0 =
Fixes arrows staying stuck in their hover colour after a tap, and self-hosted video playing muted on iPhone.

= 1.3.0 =
Videos no longer keep playing after you swipe past them, which mattered most on phones showing one card at a time.

= 1.2.0 =
Adds VideoObject structured data so click-to-play videos are visible to search engines, plus Largest Contentful Paint improvements.

# Video Testimonials Carousel for Elementor

An Elementor widget: a carousel of portrait testimonial cards where each card *may* open a video in place.
Cards with no video URL stay plain images — no play icon, no click behaviour, no JavaScript attached to them.

No iframe or `<video>` element exists on the page until a visitor clicks a card, so YouTube, Vimeo and your
own media files cost nothing on load.

## Requirements

* WordPress 5.9+
* PHP 7.4+
* Elementor 3.5+ (the free plugin — Elementor Pro is not needed)

## Install

1. Copy the `video-testimonials-carousel` folder into `wp-content/plugins/`, or zip that folder and upload it
   under **Plugins → Add New → Upload Plugin**.
2. Activate **Video Testimonials Carousel**.
3. Edit a page with Elementor and search the widget panel for **Video Testimonials Carousel**.

## Adding cards

Each row under **Content → Testimonials → Cards** has three fields:

| Field | Purpose |
|---|---|
| **Label** | Editor only. Names the row in the panel, and becomes the image `alt` text and the play button's screen-reader label. Nothing is printed on the card. |
| **Card Image** | What visitors see. Cropped to the card's aspect ratio. |
| **Video URL** | **Optional.** Leave empty for a plain image card. |

The provider is detected from the URL — there is nothing to select:

* `youtube.com/watch?v=…`, `youtu.be/…`, `/shorts/…`, `/embed/…`, `/live/…`
* `vimeo.com/123456789`, `player.vimeo.com/video/…`, and unlisted links carrying a privacy hash
  (`vimeo.com/123456789/abc123` or `?h=abc123`)
* any direct media file: `.mp4`, `.m4v`, `.webm`, `.ogv`, `.ogg`, `.mov` — including files from your
  WordPress Media Library

A URL that matches none of these is treated as "no video": the card renders as a plain image rather than
breaking.

## Behaviour while a video plays

* Only one video plays at a time, anywhere on the page. Opening a second card tears the first one down.
* Carousel autoplay pauses, and resumes when the video ends or is closed.
* The card returns to its image when the video ends (**Video → Return To Image When Video Ends**).
* A close button sits over the player (**Video → Close Button While Playing**).

## Settings worth knowing

* **Video → Show Play Button** — turn this off if your card images already have a play button drawn on them.
  The cards stay clickable either way.
* **Video → Video Fit** — `Contain` letterboxes a 16:9 video inside a portrait card; `Cover` crops it to fill.
* **Video → Privacy Mode** — embeds via `youtube-nocookie.com` and sends Vimeo `dnt=1`, so no tracking
  cookies are set before playback.
* **Video → Crop Provider Chrome** — the only way to remove YouTube's title and channel bar.
  See the note below.
* **Video → Preload** — applies only after a click; nothing is fetched before that.
* **Style → Card → Aspect Ratio** — 9:16 by default, with 4:5, 1:1 and 16:9 available per device.

## SEO

Click-to-play is good for speed and privacy but bad for video indexing, and Google says so directly:
*"Don't rely on user actions (such as swiping, clicking, or typing) to load the video."*
([Video SEO best practices](https://developers.google.com/search/docs/appearance/video))

The widget resolves that by declaring the videos independently of the DOM. With **SEO → VideoObject
Schema** on, every card that has a video emits JSON-LD:

| Property | Where it comes from | |
|---|---|---|
| `name` | Video Title, falling back to the card Label | required |
| `thumbnailUrl` | the card image at full size | required |
| `uploadDate` | Upload Date, falling back to the page's publish date | required |
| `embedUrl` / `contentUrl` | derived from the video URL | required |
| `description` | Video Description | recommended |
| `duration` | Duration, converted from `1:23` to `PT1M23S` | recommended |

A card is left out of the markup if it has no video, no title or no image, because Google treats items
missing a required property as ineligible rather than partially valid. Switch the whole thing off if
Yoast, Rank Math or similar already emits video schema for these pages — two VideoObject blocks for one
video is worse than none.

Fill in a real upload date and duration where you can. The page-date fallback keeps the markup *valid*,
but it is not *true*, and accuracy is what earns rich results.

Other search-facing details handled for you:

* The first card image loads eagerly with `fetchpriority="high"` (**SEO → Preload First Images**), so the
  carousel does not become a lazy-loaded Largest Contentful Paint element.
* Images carry `width` and `height`, so cards reserve their space and do not shift.
* The carousel is exposed as a labelled `region` with `aria-roledescription="carousel"`.

## Support

Found a bug or need a hand? Open an issue on
[GitHub](https://github.com/mehdibouchami/video-testimonials-carousel/issues), or email
<75722625+mehdibouchami@users.noreply.github.com>.

If the plugin saved you some time, you can buy me a coffee on
[Ba9chich](https://ba9chich.com/fr/mehdibouchami).

## A note on YouTube branding

Vimeo lets you hide its chrome completely, which is why a Vimeo card looks clean. YouTube does not, and no
embed parameter changes that:

* `modestbranding=1` has been **ignored since August 2023**. The plugin still sends it; it does nothing.
* `showinfo=0`, which used to hide the title, was **removed in 2018**.
* `controls=0` hides only the control *buttons*. The title, channel name, avatar and the Shorts watermark
  stay on screen. (Verified against a Shorts embed, both states side by side.)

The chrome is hidden while the video plays uninterrupted and reappears on hover or pause — which is when
most people notice it.

**The one thing that works is cropping it:** `Video → Crop Provider Chrome`. This scales the player taller
than the card so the header and the watermark fall outside the visible area. About **130%** clears both. The
trade-off is a zoomed-in video that loses a little off the top and bottom, so it is set to 100% (off) by
default — Vimeo and self-hosted video need no cropping.

If a completely unbranded, uncropped player matters more than hosting on YouTube, serve those testimonials
as self-hosted MP4s from the Media Library: the HTML5 player has no branding of any kind.

## A note on "return to image when video ends"

For self-hosted video this is exact (the browser's own `ended` event). For YouTube and Vimeo it uses each
provider's `postMessage` channel rather than loading their JavaScript SDKs, which keeps the "nothing loads
until clicked" promise. That channel is reliable in practice but not guaranteed — if a provider ever stops
reporting the end of playback, the card simply stays on the player, and the close button still works.

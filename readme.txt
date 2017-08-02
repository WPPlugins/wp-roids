=== WP Roids ===

Stable tag: 2.2.0
Requires at least: 4.2
Tested up to: 4.7.2

License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0-standalone.html

Contributors: philmeadows
Donate link: http://philmeadows.com/say-thank-you/
Tags: cache,caching,minify,minification,page speed,pagespeed,optimize,optimise,performance,compress,fast

Probably the fastest Caching and Minification for WordPress®. Tested FASTER than: WP Super Cache, W3 Total Cache, and many more!

== Description ==

**Fast AF caching! Plus minifies your site's HTML, CSS & Javascript**

= 13 February 2017 ~ Version 2.2 Released! =

**Tweaks to CSS/Javascript minification code - was freaking out at `base64` strings containing `//`s**

= Getting Started =

No complicated settings to deal with;

1. Deactivate/delete any current caching or minification plugins
2. Install WP Roids
3. Activate WP Roids
4. Log out
5. Refresh your home page TWICE

= "It broke my site, arrrrrrgh!" =

**Do the following steps, having your home page open in another browser. Or log out after each setting change if using the same browser. After each step refresh your home page TWICE**

1. Switch your site's theme to "Twenty Seventeen". If it then works, you have a moody theme
2. If still broken, disable all plugins except WP Roids. If WP Roids starts to work, we have a plugin conflit
3. Reactivate each plugin one by one and refresh your home page each time time until it breaks
4. Log a [support topic](https://wordpress.org/support/plugin/wp-roids) and tell me as much as you can about what happened

= How Fast Is It? =

In testing, WP Roids was FASTER than:

- WP Super Cache
- W3 Total Cache
- WP Fastest Cache
- Comet Cache
- ...and many more!

See the [screenshots tab](https://wordpress.org/plugins/wp-roids/screenshots) for test results

= Where Can I Check Site Speed? =

Either of these two sites is good:

- [Pingdom](https://tools.pingdom.com)
- [GTmetrix](https://gtmetrix.com)

= Software Requirements =

In addition to the [WordPress Requirements](http://wordpress.org/about/requirements/), WP Roids requires the following:

-	**`.htaccess` file to be writable**

	some security plugins disable this, so just turn this protection off for a minute during install/activation	
-	**PHP version greater than 5.4**

	It WILL throw errors on activation if not! No damage should occur though	
-	**PHP cURL extension enabled**

	Usually is by default on most decent hosts

== Installation ==

**NOTE:** WP Roids requires the `.htaccess` file to have permissions set to 644 at time of activation. Some security plugins (quite rightly) change the permissions to disable editing. Please temporarily disable this functionality for a moment whilst activating WP Roids.

The quickest and easiest way to install is via the `Plugins > Add New` feature within WordPress®. But if you must, manual installation instructions follow...

1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.

== Screenshots ==

1. Baseline - no caching applied
2. Cachify
3. W3 Total Cache
4. WP Super Cache
5. Simple Cache
6. Comet Cache
7. Cache Enabler
8. WP Fastest Cache
9. WP Roids - not bad, eh?

== Frequently Asked Questions ==

= Is WP Roids compatible with iThemes Security? =

Yes. But if you have `.htaccess` protected (**Security > Settings > System Tweaks**), you will need to disable this when activating WP Roids. You can re-enable it after activation

= Is WP Roids compatible with Jetpack? =

Yes

= Is WP Roids compatible with Yoast SEO? =

Yes

= Is WP Roids compatible with WooCommerce? =

Yes

= Is WP Roids compatible with Storefront Theme for WooCommerce? =

Yes

= Is WP Roids compatible with Storefront Pro (Premium) plugin for WooCommerce? =

Yes

= Is WP Roids compatible with WPBakery Visual Composer? =

Yes

= Is WP Roids compatible with Page Builder by SiteOrigin? =

Yes

= Is WP Roids compatible with Genesis Framework and Themes? =

Yes. Though I have only tested with Metro Pro theme

= Is WP Roids compatible with the "Instagram Feed" plugin? =

Yes, but you must tick the "Are you using an Ajax powered theme?" option to "Yes"

= Is WP Roids compatible with the "Disqus Comment System" plugin? =

Yes

= Is WP Roids compatible with the "Visual Form Builder" plugin? =

Yes

= Is WP Roids compatible with the "Simple Share Buttons Adder" plugin? =

Yes

= Is WP Roids compatible with Cloudflare? =

Yes, but you may want to flush your Cloudflare cache after activating WP Roids. Also disable the minification options at Cloudflare

= Does WP Roids work on NGINX servers? =

Maybe. [See this support question](https://wordpress.org/support/topic/nginx-15)

= Does WP Roids work on IIS (Windows) servers? =

Probably not

== Under The Hood ==

WP Roids decides which Pages and Posts are suitable for caching and/or minification of HTML, CSS & Javascript.

For caching and minification of CSS & Javascript, the rules generally are:

- User is not logged in
- User doesn't have an active WooCommerce basket, even in not logged in
- Current view is a Page or Post (or Custom Post Type)
- Current view is NOT an Archive i.e. list of Posts (or Custom Post Types)
- Current view has not received an HTTP POST request e.g. a form submission
- Current view is not a WooCommerce Basket/Checkout page

If any of the above rules fail, WP Roids will simply just minify the HTML output. All else gets the CSS & Javascript combined and minified (and requeued), then the Page/Post HTML is minified and a static `.html` file copy saved in the cache folder.

The cache automatically clears itself on Page/Post changes, Theme switches and Plugin activations/deactivations

WP Roids can also detect if you have manually edited a Theme CSS or Javascript file and will update iteself with the new versions

== License ==

Copyright: © 2017 [Philip K Meadows](http://philmeadows.com) (coded in Great Britain)

Released under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-3.0-standalone.html)

= Credits / Additional Acknowledgments =

* Software designed for WordPress®
	- GPL License <http://codex.wordpress.org/GPL>
	- WordPress® <http://wordpress.org>
* Photograph of Lance Armstrong
	- Source: <http://www.newsactivist.com/en/articles/604-103-lf/lance-armstrong-cheater-0>
	- Used without permission, but "Fair Use" for the purposes of caricature and parody

== Upgrade Notice ==

= v2.2.0 =

More regex fixes on asset minification

= v2.1.1 =

Regex fixes for `data:image` and `base64` strings

= v2.1.0 =

Formatting error fix for Visual Composer

= v2.0.1 =

Minor tweak

= v2.0.0 =

Urgent update, install immediately!

= v1.3.6 =

Compatibility check that caused deactivation fixed

= v1.3.5 =

JS comment crash fixed

= v1.3.4 =

rewritebase error, was killing some sites dead AF, sorry

= v1.3.3 =

Minor fixes

= v1.3.1 =

Switched off debugger

= v1.3.0 =

Another HUGE update! Recommend deactivating and reactivating after update to ensure correct operation

= v1.2.0 =

HUGE update! Recommend deactivating and reactivating after update to ensure correct operation

= v1.1.4 =

More asset enqueuing issue fixes

= v1.1.3 =

Asset enqueuing issue fixes

= v1.1.2 =

Version numbering cockup

= v1.1.1 =

Issue fixes

= v1.1.0 =

Several improvements and issue fixes

= v1.0.0 =

Requires WordPress 4.2+

== Changelog ==

= v2.2.0 =

- **Improved:** Regex for stripping CSS and Javascript comments rewritten and separated
	* The Javascript one, in particular, was treating `base64` strings containing two or more `//` as a comment and deleting them
	
- **Improved:** Fallback serving of cache files via PHP when `.htaccess` has a hiccup was sometimes pulling single post file on achive pages :/

= v2.1.1 =

- **Fixed:** Regex tweak for handling `data:image` background images in CSS

- **Fixed:** Regex tweak where some `base64` encoded strings containing two or more `/` being stripped believing they were comments

= v2.1.0 =

- **Fixed:** There is a teeny-tiny glitch in [WPBakery's Visual Composer](https://vc.wpbakery.com/) that was causing massive display errors

- **Improved:** Negated the need to deactivate and reactivate to remove v1 `.htaccess` rules

- **Improved:** HTML `<!-- WP Roids comments -->` with better explanations

- **Improved:** Some additional cookie and WooCommerce checks

= v2.0.1 =

- **Minor Fix:** Code to remove v1 rules from `.htaccess` **DEACTIVATE AND REACTIVATE WP ROIDS ASAP**

= v2.0.0 MASSIVE NEW IMPROVEMENTS! =

- **Fixed:** CSS and Javascript minifying and enqueuing had issues with:
	* Some inline items were being missed out or rendered improperly
	* Absolute paths to some assets such as images and fonts loaded via `@font-face` were sometimes incorrect
	* Some items were queued twice
	* Some items were not queued at all!
	
- **Fixed:** HTML minification was sometimes removing legitimate spaces, for example between two `<a>` tags
	
- **Fixed:** Caching function was sometimes running twice, adding unecessary overhead
	
- **Improved:** Some `.htaccess` rules
- **New:** Function checking in background `.htaccess` content is OK, with automatic/silent reboot of plugin if not
- **New:** Additional explanatory HTML `<-- comments -->` added to bottom of cache pages

= v1.3.6 =

- **Fixed:** Issue with PHP `is_writable()` check on `.htaccess` was causing plugin to not activate at all or silently deactivate upon next login

= v1.3.5 =

- **Fixed:** Regex to remove inline JS comments was failing if no space after `//`
- **Imprevement:** Compatability check when activating new plugins. On failure automatically deactivates WP Roids and restores `.htaccess` file

= v1.3.4 =

 - **Fixed:** `.htaccess` rewritebase error, was killing some sites dead AF, sorry

= v1.3.3 =

 - **Fixed:** `.htaccess` PHP fallback has conditions checking user not logged in
 - **Fixed:** WordFence JS conflict fixed

= v1.3.1 =

 - Disabled the debug logging script, you don't need me filling your server up!

= v1.3.0 =

 - **Fixed:** Directories and URLs management for assets
 - **Fixed:** Localized scripts with dependencies now requeued with their data AND new cache script dependency

= v1.2.0 =

- **Fixed:** Combining and minifying CSS and Javascript **MASSIVELY IMPROVED!**
	* For CSS, now deals with conditional stylesheets e.g. `<!--[if lt IE 9]>`, this was causing a few hiccups
	* For Javascript, now deals with scripts that load data via inline `<script>` tags containing `/* <![CDATA[ */`
	* Minification process now does newlines first, then whitespace. Was the other way around, which could cause issues

= v1.1.4 =

- **Fixed:** Sorry, I meant site domain !== FALSE. I'm an idiot, going to bed

= v1.1.3 =

- **Fixed:** Removed googleapis.com = FALSE check of v1.1.1 and replaced with site domain = TRUE check
- **Fixed:** Check for WooCommerce assets made more specific to WC core only, now excludes "helper" WC plugins

= v1.1.2 =

- **Fixed:** Version numbering blip

= v1.1.1 =

- **Fixed:** Encoding of `.htaccess` rules template changed from Windows to UNIX as was adding extra line breaks
- **Fixed:** Additional check to avoid googleapis.com assets being considered "local"
- **Fixed:** Removed lookup of non-existent parameter from "Flush Cache" links

= v1.1.0 =

- "Flush Cache" links and buttons added
- Five minutes applied to browser caching (was previously an hour)
- Whole HTML cache flush on Page/Post create/update/delete, so that home pages/widgets etc. are updated
- **Fixed:** PHP Workaround for hosts who struggle with basic `.htaccess` rewrites :/ #SMH
- **Fixed:** Additional checks before editing `.htaccess` as sometimes lines were being repeated, my bad
- **Fixed:** For you poor souls who are hosted with 1&1, a bizarre home directory inconsistency
- **Fixed:** Scheduled task to flush HTML cache hourly wasn't clearing properly on deactivation

= v1.0.0 =

- Initial release
=== Complete Maintenance Mode ===
Contributors: kimopensourcer
Tags: maintenance mode, under construction, coming soon, private site
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete maintenance and under construction mode with full customization. 10 built-in themes, social links, access control, and more.

== Description ==

**Complete Maintenance Mode** puts your WordPress site into maintenance mode with a beautiful, customizable landing page. All features are included right out of the box.

= Features =

* **10 Built-in Themes** &mdash; Gradient Mesh, Cosmos, Zen, Emerald, Neon Glow, Light, Construction, Rocket, Dark Minimal, Clock Timer
* **Full Customization** &mdash; title, heading, body text, meta description, custom CSS
* **Social Media Links** &mdash; Facebook, Twitter/X, Instagram, YouTube, Telegram, LinkedIn, Email and more
* **Access Control** &mdash; whitelist specific user roles and individual users
* **Auto-Disable** &mdash; schedule maintenance mode to turn off automatically
* **SEO Friendly** &mdash; proper 503 status code, Retry-After header, noindex meta tag
* **Google Analytics 4** &mdash; track visitors even while in maintenance mode
* **Admin Bar Controls** &mdash; toggle maintenance mode, preview, and access settings from anywhere
* **Login Button** &mdash; optional login link on the maintenance page
* **Multisite Compatible**

= Why This Plugin =

All maintenance mode features are included. No premium upsells, no locked features, no external tracking. Just a complete maintenance mode plugin that respects your users.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/complete-maintenance-mode` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Maintenance Mode** to configure.
4. Enable maintenance mode from the admin bar or settings page.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. The plugin only adds a lightweight check on the frontend when maintenance mode is enabled. When disabled, it has virtually zero overhead.

= Can I customize the look of the maintenance page? =

Yes! Choose from 10 built-in themes and add your own custom CSS via the Advanced settings. You can also customize the title, heading, and body text.

= Can I still access my site while maintenance mode is on? =

Yes. Administrators can access the site by default. You can also whitelist specific user roles and individual users.

= Does this work with caching plugins? =

Yes. The plugin hooks early in the WordPress load process and sends proper 503 headers. If you experience issues, try clearing your cache after changing maintenance mode status.

= Is this plugin GDPR compliant? =

Yes. The plugin does not send any data to external services. Google Analytics integration is optional and only enabled if you explicitly configure it.

== Changelog ==

= 1.0.2 =
* Improved theme styling and social icon rendering
* Added proper uninstall cleanup
* Code quality improvements and security hardening
* Fixed admin JavaScript compatibility

= 1.0.1 =
* Initial release

== Upgrade Notice ==

= 1.0.2 =
This update improves theme styling, adds proper data cleanup on uninstall, and includes security and code quality enhancements.

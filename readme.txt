=== RRZE Formular ===
Contributors: rrze-webteam
Tags: form, contact, block, wizard, mail
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simple form wizard for the block editor without HTML knowledge.

== Description ==

RRZE Formular lets editors create forms directly in the block editor. You only define the fields and their order. Design, markup and spam protection are handled automatically.

Features:

* Block editor integration (no shortcodes required)
* Limited set of form field types
* Section headings to structure longer forms
* Templates for FAU websites (contact, teaching, events, research, IT, public relations)
* Server-controlled sender address
* Delivery to the webmaster or another address on an allowed domain
* Sanitized field values in operator mails only
* Optional confirmation mails only for allowed domains
* Invisible anti-spam measures (honeypot, time token, rate limiting)
* SSO / logged-in user data can be included via filter or WordPress login

== Installation ==

1. Upload the `rrze-formular` folder to `/wp-content/plugins/`.
2. Activate the plugin via the Plugins menu.
3. Configure allowed domains under Settings > RRZE Formular.
4. Insert the "RRZE Formular" block in the editor.

== Usage ==

1. Add the block to a page or post.
2. Choose a template or build your own fields.
3. Optionally set a recipient address on an allowed domain.
4. Publish the page.

== Frequently Asked Questions ==

= Can users set the sender e-mail address? =

No. The sender address always comes from the server configuration.

= When are confirmation mails sent? =

Only when enabled on the block and the submitter e-mail uses a domain listed under allowed confirmation domains.

= How does SSO integration work? =

If a user is logged in, name and e-mail are appended to the operator mail. External SSO systems can supply data via the `rrze_formular_sso_user_data` filter.

== Changelog ==

= 0.0.2 =
* Initial release

=== RRZE Formular ===
Contributors: rrze-webteam
Tags: form, contact, block, mail, spam-protection
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.3.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simple forms for the block editor with automatic design, spam protection and secure mail delivery.

== Description ==

RRZE Formular lets editors create forms directly in the block editor. You define the fields and their order; design, markup, spam protection and mail delivery are handled automatically.

= Features =

* Block editor integration (no shortcodes required)
* Form field types with section headings for longer forms
* Templates for FAU websites (contact, teaching, events, research, IT, public relations and more)
* Fixed sender address and name from the website configuration
* Recipient resolution: block setting, plugin default, or site administrator e-mail
* Optional recipient name for block and default recipient
* Domain validation for recipient addresses
* Allowed domains from **RRZE Settings** (network) or from the plugin settings when RRZE Settings is inactive
* Privacy link on every form (`/datenschutz` on German sites, `/privacy` otherwise)
* Publishing is blocked when a page contains a form block but the required privacy page is not published
* Publishing is blocked when a block recipient uses a domain that is not allowed
* Optional confirmation e-mails to the submitter
* Optional CSV attachment with submitted field values in operator e-mails (per block)
* Invisible anti-spam measures (honeypot, time token, rate limiting)
* SSO / logged-in user data via WordPress login or filter hook

== Installation ==

1. Upload the `rrze-formular` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** menu.
3. Configure allowed e-mail domains:
   * If **RRZE Settings** is active: Network Admin → RRZE Settings → Plugins → RRZE Formular
   * Otherwise: **Settings → RRZE Formular**
4. Create and publish a privacy page at `/datenschutz` (German) or `/privacy` (other languages).
5. Insert the **RRZE Formular** block in the editor.

== Usage ==

1. Add the block to a page or post.
2. Choose a template or build your own fields.
3. Optionally set a recipient e-mail and name on an allowed domain.
4. Optionally enable **Attach CSV to operator e-mail** in the block settings.
5. Publish the page (requires a published privacy page and valid recipient configuration).

== Frequently Asked Questions ==

= Can users set the sender e-mail address? =

No. The sender always uses the site e-mail address and site name from WordPress.

= Where are allowed domains configured? =

When **RRZE Settings** is active, allowed domains are managed network-wide for RRZE Formular. Otherwise use **Settings → RRZE Formular**.

= When are confirmation mails sent? =

When enabled on the block and the submitter provides a valid e-mail address. If allowed domains are configured, the submitter address must match one of them.

= When is a CSV file attached? =

When **Attach CSV to operator e-mail** is enabled on the block. The CSV has two rows: field names in the first row, submitted values in the second (RFC 4180, comma-separated). The file is sent only with the operator mail, not with confirmation mails.

= Why can I not publish a page with a form? =

Publishing is blocked when either the required privacy page is missing or not published, or a form block uses a recipient address outside the allowed domains.

= How does SSO integration work? =

If a user is logged in, name and e-mail can be appended to the operator mail. External SSO systems can supply data via the `rrze_formular_sso_user_data` filter.

== Hooks ==

* `rrze_formular_defaults` – plugin settings structure
* `rrze_formular_allowed_domains` – allowed recipient domains
* `rrze_formular_sso_user_data` – SSO user data for operator mails
* `rrze_formular_resolved_recipient` – resolved recipient after block/settings/default
* `rrze_formular_templates` – form templates in the block editor
* `rrze_formular_token_ttl` – anti-spam token lifetime
* `rrze_formular_allowed_confirmation_email` – whether a confirmation mail may be sent
* `rrze_formular_privacy_page_reachable` – override privacy page availability check


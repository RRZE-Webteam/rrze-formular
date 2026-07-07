=== RRZE Formular ===
Contributors: rrze-webteam
Tags: form, contact, block, mail, spam-protection
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.4.6
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simple forms for the block editor with automatic design, spam protection and secure mail delivery.

== Description ==

**RRZE Formular** provides simple forms for the block editor with automatic design, spam protection and secure mail delivery.

RRZE Formular lets editors create forms directly in the block editor. You define the fields and their order; design, markup, spam protection and mail delivery are handled automatically.

= Features =

* Block editor integration
* Form field types with section headings for longer forms
* Templates for university websites (contact, teaching, events, research, public relations and more)
* Fixed sender address and name from the website configuration
* Recipient resolution: block setting → plugin default → site administrator e-mail
* Optional recipient name for block and default recipient
* Domain validation for recipient addresses
* Allowed domains from **RRZE Settings** (network) or from plugin settings when RRZE Settings is inactive
* Privacy link on every form (`/datenschutz` on German sites, `/privacy` otherwise)
* Publishing blocked when the required privacy page is not published
* Publishing blocked when a block recipient uses a domain that is not allowed
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

When enabled on the block, the submitter provides a valid e-mail address, and that address uses a domain from the dedicated **allowed confirmation domains** list. If no confirmation domains are configured, confirmation mails are never sent.

Confirmation mails contain only a short receipt (form title, site link, date) — not submitted field values — and are rate-limited per submitter address.

= When is a CSV file attached? =

When **Attach CSV to operator e-mail** is enabled on the block. The CSV has two rows: field names in the first row, submitted values in the second. The file is sent only with the operator mail, not with confirmation mails.

= Why can I not publish a page with a form? =

Publishing is blocked when either the required privacy page is missing or not published, or a form block uses a recipient address outside the allowed domains.

= How does SSO integration work? =

If a user is logged in, name and e-mail can be appended to the operator mail. External SSO systems can supply data via the `rrze_formular_sso_user_data` filter.

= How is the submit endpoint protected? =

`POST /wp-json/rrze-formular/v1/submit` is intentionally public so anonymous visitors can send forms. A WordPress REST nonce (`wp_rest`) is **not** used or required.

Protection is enforced server-side in `FormHandler`:

* **Signed form configuration** (`formConfig` + `formConfigSig`) — only fields defined in the block can be submitted
* **One-time submission token** (`token`) — issued lazily via `POST /wp-json/rrze-formular/v1/token` when a visitor interacts with or submits the form (not during HTML rendering), HMAC-signed, bound to the form config, consumed before mail delivery
* **Minimum submit delay** — rejects submissions faster than the configured threshold
* **Honeypot** (`website`) — must stay empty
* **Rate limiting** — per client IP (and per submitter e-mail for confirmation mails)
* **Confirmation domain allowlist** — confirmation mails are only sent when dedicated confirmation domains are configured; submitted content is not included in confirmation mails
* **Field validation** — required fields, e-mail format, allowed recipient domains

The REST route validates the request shape (required parameters, `values` object, optional URL/locale) before processing.

= Cache compatibility =

Form rendering is designed to be cache-safe. Rendered HTML contains an empty token field plus a signed form configuration, but it does not create or persist submission tokens. This keeps normal page rendering, REST-rendered post output and feed-rendered output free of token writes.

Full-page caches may cache pages that contain forms. The per-submission token is fetched later in the browser via the public token REST endpoint and consumed before mail delivery.

The plugin can require a persistent object cache for public form endpoints so anonymous token and rate-limit state is not written to the database. This package currently sets `$GLOBALS['rrze_formular_require_persistent_object_cache'] = false` in the main plugin file, so database fallback is allowed when infrastructure-level rate limiting protects the public REST routes. Set the global to `true` or use the `rrze_formular_require_persistent_object_cache` filter to make `/wp-json/rrze-formular/v1/token` and `/wp-json/rrze-formular/v1/submit` return HTTP 503 when Redis, Memcached or another persistent object cache is unavailable.

== Hooks ==

* `rrze_formular_defaults` – Plugin settings structure
* `rrze_formular_allowed_domains` – Allowed recipient domains
* `rrze_formular_sso_user_data` – SSO user data for operator mails
* `rrze_formular_resolved_recipient` – Resolved recipient after block/settings/default
* `rrze_formular_templates` – Form templates in the block editor
* `rrze_formular_token_ttl` – Anti-spam token lifetime
* `rrze_formular_allowed_confirmation_email` – Whether a confirmation mail may be sent (after domain check)
* `rrze_formular_confirmation_domains` – Allowed domains for confirmation mails
* `rrze_formular_privacy_page_reachable` – Override privacy page availability check
* `rrze_formular_require_persistent_object_cache` – Require persistent object cache for public form endpoints; overrides the plugin-level global

== Links ==

* [Plugin on GitHub](https://github.com/RRZE-Webteam/rrze-formular)
* [Documentation](https://www.wp.rrze.fau.de/)

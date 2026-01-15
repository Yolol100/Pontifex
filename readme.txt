=== Pontifex OI ===
Contributors: andrewbaeten
Tags: pontifex, exam registration, SOAP API, planning, payments, shortcodes
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pontifex-oi
Domain Path: /languages

Short Description:
Adds Pontifex exam planning, registration and payment functionality to WordPress using shortcodes.

== Description ==

Pontifex OI is a WordPress plugin that connects your website to the Pontifex Open Inschrijvingen SOAP API.  
It enables you to display exam planning, collect candidate registrations, and process payments directly within WordPress.

The plugin uses a shortcode-based workflow, making it easy to place each step of the process on its own page.  
It is built with modern object-oriented PHP, follows WordPress coding standards, and is designed to be extendable.

Some manual configuration is required before the plugin can be used.

== Features ==

* Display exam planning retrieved from the Pontifex API
* Frontend registration forms for candidates
* Payment handling via Mollie
* REST API endpoints for frontend interaction
* Daily synchronization using WordPress Cron
* Object-oriented, Composer-ready codebase
* Translation-ready (i18n)
* Overrideable templates

== Installation ==

1. Upload the `pontifex-oi` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Provide your Pontifex API credentials using filters or the plugin settings.
4. Create pages and insert the required shortcodes.

== Shortcodes ==

Each shortcode should be placed on a **separate page**.

### `[pontifex_oi_planning]`
Displays the available exam planning.

Use this shortcode on an overview or landing page.

---

### `[pontifex_oi_registration]`
Displays the registration form for a selected exam.

Example:
`[pontifex_oi_registration exam_id="12345"]`

---

### `[pontifex_oi_payment_success]`
Displays a confirmation message after a successful payment.

Place this shortcode on a thank-you page.

== Usage Notes ==

* Use separate pages for planning, registration and confirmation.
* Ensure WordPress pretty permalinks are enabled.
* Templates can be overridden by copying them into your theme.

== REST API ==

The plugin registers the following REST API routes:

* `GET /wp-json/pontifex-oi/v1/planning`  
  Returns exam planning data.

* `GET /wp-json/pontifex-oi/v1/price`  
  Returns pricing information.

* `POST /wp-json/pontifex-oi/v1/webhook`  
  Handles Mollie payment webhooks secured with a shared secret.

== Cron Jobs ==

A daily synchronization with the Pontifex API runs automatically at **03:15 local time** using WordPress Cron.

== Requirements ==

* Valid Pontifex API credentials
* PHP 8.1 or higher
* WordPress 6.0 or higher

== Frequently Asked Questions ==

= Do I need to configure anything after installation? =
Yes. Pontifex API credentials must be provided and the shortcodes must be placed on pages.

= Does this plugin store personal data? =
Only data required for the registration and payment process is processed.

= Can the plugin be customized? =
Yes. Templates and hooks can be overridden or extended.

== Screenshots ==

1. Plugin settings screen  
2. Exam planning overview  
3. Registration form  
4. Payment confirmation page

== Upgrade Notice ==

= 1.0.0 =
Initial public release.

== Changelog ==

= 1.0.0 - 2025-10-07 =
* Initial release.
* Exam planning, registration and payment workflow.
* REST API and WP-Cron integration.

== Credits ==

* Pontifex Certificatie — API documentation  
* Developed by Andrew Baeten

== Support ==

For support or custom development inquiries:  
Email: **info@andrewbaeten.nl**


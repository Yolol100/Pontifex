=== Pontifex OI ===
Contributors: andrewbaeten
Tags: pontifex, exams, registration, SOAP API, planning, payments, shortcodes
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pontifex-oi
Domain Path: /languages

Short Description:
Integrates Pontifex exam planning, registrations, and payments into WordPress using shortcodes and a SOAP API.

== Description ==

**Pontifex OI** is a WordPress plugin that integrates with the Pontifex Open Inschrijvingen SOAP API.  
It allows website owners to display exam planning, collect registrations, and handle payments within WordPress using a clear and structured shortcode-based workflow.

The plugin is built with modern object-oriented PHP, follows WordPress coding standards, and is designed for long-term maintainability.  
It supports REST endpoints, scheduled data synchronization, and overrideable templates.

Some configuration is required before the plugin becomes fully operational.

== Features ==

* Display up-to-date exam planning from the Pontifex API
* Candidate registration via frontend forms
* Payment handling with Mollie
* REST API endpoints for frontend and integrations
* Daily automated synchronization using WP-Cron
* Object-oriented and Composer-ready architecture
* Translation-ready (i18n)
* Template overrides supported

== Installation ==

1. Upload the `pontifex-oi` directory to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure your Pontifex API credentials using filters or the plugin settings.
4. Create pages and insert the required shortcodes.

== Shortcodes ==

Each shortcode should be placed on a **separate page**.

### `[pontifex_oi_planning]`
Displays the available exam planning and sessions.

Recommended for an overview or landing page.

---

### `[pontifex_oi_registration]`
Displays the registration form for a selected exam.

Example:
`[pontifex_oi_registration exam_id="12345"]`

---

### `[pontifex_oi_payment_success]`
Displays a confirmation message after a successful payment.

Use this shortcode on a thank-you page.

== Usage Notes ==

* Use separate pages for planning, registration, and confirmation.
* WordPress pretty permalinks must be enabled.
* Templates can be overridden by copying them into your theme.

== REST API ==

The plugin registers the following REST API routes:

* `GET /wp-json/pontifex-oi/v1/planning`  
  Returns exam planning data.

* `GET /wp-json/pontifex-oi/v1/price`  
  Returns pricing information.

* `POST /wp-json/pontifex-oi/v1/webhook`  
  Handles Mollie payment webhooks (secured via a shared secret).

== Cron Jobs ==

A daily synchronization with the Pontifex API runs automatically at **03:15 local time** using WordPress Cron.

== Requirements ==

* Valid Pontifex API credentials
* PHP 8.1 or higher
* WordPress 6.0 or higher

== Frequently Asked Questions ==

= Do I need to configure anything after installation? =
Yes. You must provide Pontifex API credentials and add the shortcodes to dedicated pages.

= Does this plugin store personal data? =
Only data required for the registration and payment process is processed.

= Can I customize the plugin? =
Yes. Templates and hooks can be overridden or extended.

== Screenshots ==

1. Plugin settings screen  
2. Exam planning overview (frontend)  
3. Registration form (frontend)  
4. Payment confirmation page

== Upgrade Notice ==

= 1.0.0 =
Initial public release.

== Changelog ==

= 1.0.0 - 2025-10-07 =
* Initial release.
* Exam planning, registration, and payment workflow.
* REST API and WP-Cron integration.

== Credits ==

* Pontifex Certificatie — API documentation and access  
* Developed by Andrew Baeten

== Support ==

For support or custom development inquiries:  
Email: **info@andrewbaeten.nl**

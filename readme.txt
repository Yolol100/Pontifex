=== Pontifex OI ===
Contributors: andrewbaeten
Tags: pontifex, registration, exams, SOAP API, planning, payment, shortcodes
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pontifex-oi
Domain Path: /languages

Summary:  
Comprehensive Pontifex registration and payment integration with shortcodes and SOAP API support.

== Description ==

**Pontifex OI** is a complete WordPress plugin that integrates with the Pontifex Open Inschrijvingen SOAP API.  
Use it to display up-to-date exam planning, let candidates register, and handle secure payments — all via simple shortcodes and flexible API endpoints.

Built with modern, object-oriented code, Composer-ready architecture, and extensibility in mind. Templates and logic are overrideable, and the plugin follows WordPress coding standards and best practices.

⚠️ **Note:** Some API settings must be configured manually (via settings page or filters) for full functionality.

== Installation ==

1. Upload the `pontifex-oi` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Enter your Pontifex API credentials (user_identifier, company_identifier, SHA256 hash) via the plugin settings or filter hooks.
4. Add the provided shortcodes to your pages as described below.

== Shortcodes ==

Use the following shortcodes on separate pages to build your front-end flow:

* `[pontifex_oi_planning]`  
  Displays the current exam planning. Ideal for your “Exams” landing page.

* `[pontifex_oi_registration]`  
  Shows the exam registration form.  
  _Example:_ `[pontifex_oi_registration exam_id="12345"]`

* `[pontifex_oi_payment_success]`  
  Shows the payment confirmation message after a successful transaction.

> 💡 Use distinct pages for planning, registration, and success. This ensures clean navigation and avoids form conflicts.

== Usage ==

Below is a quick overview of how the plugin works:

### Shortcodes
- **Planning View:** `[pontifex_oi_planning]` — shows exams and filters  
- **Registration Form:** `[pontifex_oi_registration]` — user sign-up  
- **Payment Success:** `[pontifex_oi_payment_success]` — confirmation after payment

== REST API Endpoints ==

The plugin registers REST routes for AJAX or JavaScript interactions:

* `GET /wp-json/pontifex-oi/v1/planning` — returns planning data  
* `GET /wp-json/pontifex-oi/v1/price` — returns price info  
* `POST /wp-json/pontifex-oi/v1/webhook` — Mollie payment webhook (secured via secret parameter)

== Cron Jobs ==

A daily sync with the Pontifex API is scheduled at **03:15 local time** via WordPress Cron.

== Requirements & Notes ==

- **Pontifex API credentials** must be provided (settings page or filters).  
- **Pretty Permalinks** should be enabled for REST endpoints and shortcodes to work reliably.  
- **Template Overrides:** Copy any plugin template into your theme and edit as needed.

== Frequently Asked Questions ==

= Do I have to configure anything manually? =  
Yes — you must provide your Pontifex API credentials and add the shortcodes on separate pages.

= Is the plugin GDPR compliant? =  
Yes — no personal data is stored outside what WordPress requires.

= Can I extend or customize the plugin? =  
Yes — templates and many hooks are overrideable.

== Screenshots ==

1. Example values in the settings screen.  
2. Exam planning with filters (front-end).  
3. Exam registration form (front-end).  
4. Payment confirmation / thank you page.

== Upgrade Notice ==

= 1.0.0 =
* Initial release with planning listing, registration form and payment flow.  
* Fully OOP, Composer-ready and extendable.  
* Overrideable templates, internationalization (i18n) support.

== Changelog ==

= 1.0.0 - 2025-10-07 =
* Initial release featuring planning, registration, and payment shortcodes.  
* Plugin architecture ready for extensions and custom templates.

== Credits ==

* Pontifex Certificatie for API access and documentation.  
* Built and maintained by Andrew Baeten.

== Support ==

Need help or custom development?  
Email: **info@andrewbaeten.nl**

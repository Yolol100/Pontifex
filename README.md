# Pontifex OI
**Contributors:** andrewbaeten  
**Tags:** pontifex, exam registration, SOAP API, planning, payments, mollie  
**Requires at least:** 6.0  
**Tested up to:** 6.5  
**Requires PHP:** 8.1  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  

Seamlessly connect WordPress to the Pontifex Open Inschrijvingen (Open Registrations) SOAP API for exam planning and candidate management.

---

### Description

**Pontifex OI** is a robust WordPress solution designed to bridge your website with the Pontifex API. It automates the entire workflow: from displaying real-time exam schedules to processing secure payments via Mollie.

The plugin utilizes a clean, shortcode-based workflow. Every step of the funnel—planning, registration, and confirmation—can be placed on its own page for maximum conversion tracking and user experience control.

### Key Features

* **Real-time Sync:** Automatic daily synchronization (03:15 local time) via WP-Cron.
* **Mollie Integration:** Secure payment handling including automated webhooks.
* **Shortcode Workflow:** Complete control over the customer journey on your own pages.
* **Developer Friendly:** Built on PHP 8.1+ with OOP architecture and overrideable templates.
* **REST API:** Custom endpoints available for modern frontend integrations.

---

### Installation

1.  Upload the `pontifex-oi` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the **Plugins** menu in WordPress.
3.  Configure your API credentials under **Settings > Pontifex OI**.
4.  Create your pages and insert the required shortcodes.

### Shortcodes

For optimal performance, place each shortcode on a unique page:

* `[pontifex_oi_planning]` – Displays the list of available exams.
* `[pontifex_oi_registration]` – The registration form (requires an exam ID).
* `[pontifex_oi_payment_success]` – The thank-you page shown after successful payment.

---

### Technical Details

**REST API Endpoints:**
* `GET /wp-json/pontifex-oi/v1/planning`
* `POST /wp-json/pontifex-oi/v1/webhook` (Mollie handler)

**System Requirements:**
* PHP 8.1 or higher
* Mollie API Key (Live or Test)
* Valid Pontifex SOAP interface access

---

### Changelog

**1.0.0**
* Initial release.
* SOAP synchronization & Mollie payment engine integration.

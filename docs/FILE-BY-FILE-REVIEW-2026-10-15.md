# Pontifex OI — bestand-voor-bestand review

**Datum:** 15 oktober 2026 · **Bron:** `Yolol100/Pontifex` · **Branch:** `audit/wordpress-standards-2026-10-15` · **Pluginversie:** 1.0.1.

## Auditstatus en methode

Alle hieronder genoemde bron-/documentbestanden zijn in de GitHub-repository geïnventariseerd. PHP/JS/CSS is per bestand met een statische patroonanalyse onderzocht; de belangrijkste codepaden zijn ook handmatig inhoudelijk beoordeeld. **De notities zijn risicosignalen en testsuggesties, niet een garantie van volledige regel-voor-regel verificatie.** Live Mollie, SOAP, accessibility, performance en WordPress Plugin Check ontbreken nog. De eerste beveiligingswijzigingen staan alleen op een draft-PR, niet op `main`.

**Opvallend:** de 5 CSS-bestanden bevatten samen **667 keer `!important`** (respectievelijk 67, 18, 301, 166 en 115). Dit is technische schuld, geen automatisch beveiligingslek. Verwijder uitsluitend overrides met bewijs van gelijkwaardig gerenderd gedrag.

## Root

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `pontifex-oi.php` | 183 | Bootstrap, globale includes, 3 tabellen en cron; schemaversies, lifecycle, rewrite flush en klassegrenzen valideren. |
| `uninstall.php` (phase 3) | 93 | Fase 3: per site uninstall-tabellen, opties, transient/lockstaten en echte cronhook opgeschoond; destructief alléén bij WP-uninstall. Multisite staging-test nog vereist. |
| `composer.json` (phase 3) | 23 | PHP ^8.1 met GPL-2.0-or-later, classmap voor huidige klassebestanden in plaats van onjuiste PSR-4; composer validate/lockcontrole. |
| `composer.lock` (phase 3) | 427 | Lockhash bijgewerkt voor PHP-minimum 8.1; pakketversies onveranderd, Composer-audit in CI. |
| `README.md` (branchupdate) | 83 | Mapoverzicht en lokale versus externe inschrijving verduidelijkt; deploy/runbook uitbreiden. |
| `readme.txt` (branchupdate) | 73 | WP-releasevelden, compatibiliteitsclaims, privacydisclosure, licentie en POT actualiteit controleren. |
| `AUDIT_PONTIFEX_OI_2026-04-26.md` | 14 | Historisch document; niet verwarren met actuele analyse of regressiebewijs. |

## Beheer

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `admin/class-admin.php` (branchupdate) | 426 | Instellingen, menu, admin AJAX en nieuwe beveiligde CSV-handler; admin-post capability/nonce en export browsermatig hertesten. |
| `admin/page-soap-settings.php` | 86 | Instellingenweergave; URL allowlist, credentialsvelden, markup/i18n en permissies controleren. |
| `admin/page-submissions.php` (branchupdate) | 64 | UI-only exportlink naar admin-post; inputtypes, toegankelijkheid, sortering en zoekweergave controleren. |

## PHP integraties

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `includes/api/class-soap-client.php` (branchupdate) | 470 | SOAP-planning en SQL; tracing uitgeschakeld. `sendRegistration()` is gedocumenteerde no-op; webhook gebruikt nog misleidende `soap_sent`. Endpointvalidatie/sandbox open. |
| `includes/class-frontend.php` (phase 3) | 988 | Vóór Mollie: nonce, kandidaten, verplichte kopergegevens, bekende productkeuzes, bedrag en pendingopslag; REST/AJAX-prijs en planning typed; echte checkout nog niet getest. |
| `includes/config/producten-prijzen.php` (branchupdate) | 424 | Vijf catalogusarrays nu expliciet globaal gebonden; CI toonde eerder `catalog=0000`, waardoor een geldige examenprijs `0` werd. Prijsmatrix en i18n aanvullend testen. |
| `includes/cron/fetch-planning-cron.php` | 59 | WP-Cron, planning-reconciliatie en cache; locks, tijdzone, fouten en uitval testen. |
| `includes/frontend-asset-guard.php` | 56 | Conditionele enqueues via shortcode/slugs; dynamische templates en WordPress builders testen. |
| `includes/helpers/ajax-soap-fetch-handler.php` | 55 | Admin-only AJAX met nonce/capability; providerresponses veilig begrenzen en loggen. |
| `includes/helpers/class-mail-helpers.php` (branchupdate) | 168 | Sitewide wp_mail_charset en wp_mail_failed-hooks verwijderd; eigen HTML/UTF-8 headers behouden. SMTP/live-verzending niet getest. |
| `includes/helpers/class-payment-helpers.php` | 476 | Mollie API, prijsberekening, VAT/afronding; mode/secret handling en refund/retry testen. |
| `includes/helpers/class-registrations.php` (phase 3) | 168 | Bestaand idempotent save-gedrag aangevuld met find_order(payment-ID) voor late webhook-retry; UNIQUE-index en legacy-duplicatenmigratie nog open. |
| `includes/helpers/class-registration-validator.php` (phase 3) | 162 | Kandidaatrijen/DOB, verplichte contactvelden, valide e-mail/telefoon, product/taal/materiaal/extras én 1-12 kandidaten; businessvarianten testen. |
| `includes/helpers/submissions-helpers.php` (branchupdate) | 509 | CSV/kaartformatters; spreadsheet-formulebeveiliging toegevoegd, legacy-JSON en verborgen technische velden controleren. |
| `includes/webhooks/webhook-handler.php` (phase 3) | 191 | Fase 3: permanente betaal-ID-stageopties, migratie oude transients, lokale orderrecovery en expliciete local_registration_only; gelijktijdige callbacks/SMTP-ambiguitie blijven open. |

## Publiek

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `public/class-payment-success-shortcode.php` (branchupdate) | 126 | Mollie-geverifieerde betaling; tekst claimt niet langer bevestigde inschrijving, tijdelijke token en statusstatus onderzoeken. |
| `public/planning-list.php` | 241 | Planning en inschrijfformulier, GET-data; escapers, valid HTML, keyboardnavigatie en dubbele formulieren controleren. |
| `public/registration-form.php` | 445 | Formuliertekst/URL-parameters/prijs; formele inputvalidatie, hardcoded links en WCAG toetsen. |
| `public/emails/owner-notification-email-template.php` | 291 | HTML-email met PII: contextuele escaping, veilige ontvangers en mailclients controleren. |
| `public/emails/payment-success-email-template.php` | 291 | HTML-email: escaping, vertaling, correcte betalingsclaims en alternatieve plain-text mail testen. |

## JavaScript

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `assets/js/candidates.js` | 369 | 5 eventhandlers; kandidaatrijen/indexering, dubbele binding en grens van 12 kandidaten testen. |
| `assets/js/config.js` | 10 | Legacy globalconfig shim; naamgevingscontract en verwijderbaarheid via referentieanalyse. |
| `assets/js/filters.js` | 336 | Dynamische taalopties, AJAX en filters; .html()-injectiegrenzen en paginering controleren. |
| `assets/js/form-validation.js` (branchupdate) | 176 | Formulier-AJAX en klantgegevens; consolelekken verwijderd; server/client-validatie moet gelijkgetrokken worden. |
| `assets/js/main.js` | 102 | Pagina-initialisatie en globale DOM-listeners; dubbel events en scriptdeps onderzoeken. |
| `assets/js/material.js` | 433 | Weekend- en lesmateriaalinteracties met 5 consolelogs en HTML-opbouw; prijspariteit en UI-a11y toetsen. |
| `assets/js/pagination.js` | 92 | Genereert paginatie-HTML; inputescapers, focus/ARIA en null-state toetsen. |
| `assets/js/pontifex-oi-admin.js` (branchupdate) | 474 | Admin HTML-templates en AJAX; inschrijvingsvelden/nonce/fouttekst harder escaped; kaart-XSS/browserproef open. |
| `assets/js/pontifex-oi-custom.js` | 78 | Extra weekend/prijsfallback; overlap met material.js en filters.js aantonen vóór cleanup. |
| `assets/js/pontifex-oi-testmode.js` | 32 | Verwijdert client-required in testmode; nooit serverbeveiliging/validatie versoepelen. |
| `assets/js/price.js` | 507 | Complexe frontend-calculatie en 5 consolelogs; VAT, weekends, meerdere kandidaten vergelijken met server. |
| `assets/js/prices-admin.js` | 207 | Prijsbeheer naast logica in pontifex-oi-admin.js; dubbele UI-states en owners onderzoeken. |
| `assets/js/sidebar.js` (branchupdate) | 459 | Mobile filterstate; onbetrouwbare optiegegevens naar .text()-DOM omgezet; toetsenbord/overlay testen. |
| `assets/js/table.js` (branchupdate) | 262 | Planningkaart/tabel; attributen en tekst geescaped, XSS-fixtures in GitHub CI. |
| `assets/js/utils.js` | 207 | REST-naar-AJAX-fallback en browserlocalStorage; foutcode, race en failover-contract expliciteren. |

## CSS

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `assets/css/pontifex-oi-admin.css` | 473 | 67 !important; admin scope, core UI en responsive cascade visueel testen. |
| `assets/css/pontifex-oi-payment-success.css` | 108 | 18 !important; status/readability en focus op mobiele apparaten controleren. |
| `assets/css/pontifex-oi-planning.css` | 818 | 301 !important; hoge specificiteit/override-schuld; selectoren pas na visual-regressie reduceren. |
| `assets/css/pontifex-oi-registration.css` | 449 | 166 !important; field/focus/error styles en responsieve toegankelijkheid controleren. |
| `assets/css/pontifex-oi-shared.css` | 290 | 115 !important; layout tokens, globale lekkage en laadvolgorde onderzoeken. |

## Overig

| Bestand | Regels | Bronbevinding en volgende controle |
|---|---:|---|
| `languages/pontifex-oi.pot` | 378 | POT dateert van 2025-08-05; regen en vertaalscans na gewijzigde strings. |
| `.github/workflows/wordpress-compatibility.yml` (phase 3) | 215 | WP6.0/PHP8.1 en WP7.1/PHP8.3, Composer-supply-chain, PHP/JS syntax, security-smoke, echte uninstall-test en warning gate; browser/SOAP/Mollie niet gedekt. |
| `tests/js-render-safety-smoke.js` (branchupdate) | 80 | Runtime fixture voor HTML-escaping in planninglijst en cards; test geen complete browser/CSP. |
| `tests/wp-cli-security-smoke.php` (phase 3) | 338 | Regressies voor CSV, adminexport, SOAP diagnostics, REST, prijzen/BTW, kandidaat-/koperdata, webhook-stage-migratie en lokale orderrecovery. |
| `tests/wp-cli-uninstall-smoke.php` (phase 3) | 35 | Geïsoleerde WP-CLI proef die na echte uninstall verwijderde Pontifex-tabellen, opties en cron verifieert. |
| `docs/WORDPRESS-AUDIT-2026-10-15.md` (branchupdate) | — | Prioriteiten, bewezen bugs/contractvragen, faseplanning en verificatiegrenzen staan in hoofdaudit. |

## Belangrijkste openstaande kwaliteitsblokkades

1. **Registratiecontract:** `sendRegistration()` is aantoonbaar een gedocumenteerde no-op; de webhook gebruikt nog `soap_sent`. Vaststellen of lokale registratie of externe bevestiging de producteis is; geen fictieve SOAP-methode implementeren.
2. **Mollie-betrouwbaarheid:** transient-gebaseerde retry/status plus ontbrekende unieke payment-index; eerst migratie-, refund-, dubbele callback- en provideruitvaltests.
3. **Persoonsgegevens:** bewuste bewaartermijn, export/recht op wissen, multisite uninstall, mail, diagnostic masking en rate limiting.
4. **Onderhoudbaarheid:** PHP/WordPress Coding Standards, correct Composer-autoload, servicegrenzen en compact assetbeheer na regressietests.
5. **Releasebewijs:** laatste GitHub-head en CI groen plus echte staging-Mollie/SOAP, browser/A11y-test, Plugin Check, distributie-ZIP met runtime-dependencies en rollback.

Zie [prioriteitenaudit](WORDPRESS-AUDIT-2026-10-15.md) voor bevinding-ID's en de fasering.

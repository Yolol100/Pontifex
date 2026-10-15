# Pontifex OI — WordPress code-audit en moderniseringsplan

**Datum:** 15 oktober 2026  
**Bronbaseline:** `main` op commit `2547a04ff34510e2bff052defeabc4b78dc64151`  
**Plugin:** 1.0.1 · WordPress >=6.0 · PHP >=8.1  
**Status:** gefaseerde broncodebeoordeling; **geen release- of staginggoedkeuring**.

## Werkwijze en beperkingen

De GitHub-repositoryboom is integraal geïnventariseerd (GitHub tree niet afgekapt). De belangrijkste PHP-, JS-, frontend-, admin- en configuratiebestanden zijn via GitHub gelezen; de hieronder genoemde issues zijn aan concrete implementaties gekoppeld. Dit is nog **geen** volledige runtime-, line-by-line WPCS-, accessibility-, PHP-unit- of integratietest. Een lokale clone / PHP-CLI was in deze Chat-sessie niet beschikbaar. De Actions-workflow is uitgevoerd op de fase-1 branch; nieuwe fase-2 controles zijn toegevoegd. Controleer de nieuwste PR-head en bijbehorende jobs vóór een PASS-claim. Externe SOAP- en Mollie-sandboxstromen moeten op staging worden getest.

Vervang deze bevindingen niet door de historische `AUDIT_PONTIFEX_OI_2026-04-26.md`: die meldt onder meer een verouderde bootstrap- en README-status.

## Bronnen en verificatie (zevenstappencontrole)

| Bronklasse | Status | Wat is gecontroleerd |
|---|---|---|
| Actieve projectbronnen | `no_relevant_match` | Projectbronregistry geraadpleegd; geen afzonderlijk Pontifex-manifest gevonden. Zoekopdracht in verbonden Google Drive leverde geen canonieke Pontifex-projectbron op. |
| Specialist-Skills | `checked` | `webactueel-workflow` als controller, `wordpressqualityarchitect` als code-eigenaar en `website-qa-checklist` voor onafhankelijke acceptatiegrenzen. |
| Repository | `checked` | Baseline `2547a04`; fase 1–2 via [PR #7](https://github.com/Yolol100/Pontifex/pull/7) naar `9941470`, fase 3 via [PR #8](https://github.com/Yolol100/Pontifex/pull/8) naar `19d8c14` op [`main`](https://github.com/Yolol100/Pontifex). Laatste post-merge matrix [GitHub Actions #38813793937](https://github.com/Yolol100/Pontifex/actions/runs/38813793937): beide WordPress/PHP-jobs groen. |
| ChatGPT Library | `checked` | Bestaande portfolio-/plugin-inhoud over *Exam Registration Manager* teruggevonden. Het portfolio is context, **geen** SOAP-contract of betalingsbewijs. |
| YouTube-corpus | `no_relevant_match` | WordPress-kanalencatalogus met developerkanalen aangetroffen; geen Pontifex-specifieke verifieerbare captions/comments voor de API-contractvraag gevonden. |
| Actuele officiële bronnen | `checked` | [WordPress-validatie](https://developer.wordpress.org/apis/security/data-validation/), [REST endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/), [escaping](https://developer.wordpress.org/apis/security/escaping/) en [Plugin Review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/) als richtlijn; een bron is geen vervanging voor runtime-testbewijs. |
| Uitvoering en QA | `checked met gaten` | Branchmutaties per bestand teruggelezen; WordPress/PHP GitHub Actions testmatrix met syntaxis, prijs, BTW, CSV, XSS en REST-negatieve gevallen. Zonder SOAP/Mollie/SMTP-staging of browser-/WCAG-resultaten blijft release **No-Go**. |

**Conflict:** de portfolio-omschrijving suggereert een registratie-integratie, terwijl de actuele code `sendRegistration()` bewust als no-op markeert. De werkelijke externe registratieverplichting is onbeslist en blokkeert iedere claim dat Pontifex-inschrijvingen worden afgeleverd.

## Bevestigde bevindingen, op prioriteit

| ID | Ernst | Bestand | Probleem | Aanpak / bewijs |
|---|---|---|---|---|
| P01 | **Contractblokker blijft** | `includes/api/class-soap-client.php`, `includes/webhooks/webhook-handler.php` | SOAP `sendRegistration()` is een gedocumenteerde no-op. De webhook markeerde dit ten onrechte als `soap_sent`. | **Fase 3:** onware SOAP-fase verwijderd en expliciet `local_registration_only` opgeslagen. Externe inschrijving is NIET geïmplementeerd: vereist vendorcontract en stagingbewijs voordat daarvan kan worden uitgegaan. |
| P02 | **Hoog** | `assets/js/form-validation.js` | Complete registratie/`ajaxData` werd in browserconsole gelogd; foutlog kon serverresponse tonen. | **Branchfix uitgevoerd:** verwijder persoonsgegevens uit consolelogs. Browser/privacy-test nodig. |
| P03 | **Hoog** | `assets/js/pontifex-oi-admin.js` | Inschrijvingsvelden en titel werden onescaped in HTML-strings ingevoegd; response werd gelogd. Potentieel stored XSS in beheerscherm. | **Branchfix uitgevoerd:** tekstescapen bij kaartopbouw en ruwe responseconsolelog verwijderen. XSS-fixturetest vereist. |
| P04 | **Hoog, branchfix** | `admin/class-admin.php`, `admin/page-submissions.php`, `includes/helpers/submissions-helpers.php` | Export draaide tijdens admin-page rendering; CSV-formule-injectie mogelijk. | **Aangepast:** afgeschermde `admin_post_*` handler, capability + nonce, CSV-formule-escaping en 5000-recordlimiet. WP-CLI-fixtures toegevoegd; echte browser/admin HTTP-tests nog vereist. |
| P05 | **Hoog, deels verholpen** | `includes/webhooks/webhook-handler.php`, `includes/helpers/class-registrations.php` | Zevendaagse transient-status kon na verloop dubbel werk veroorzaken; ordergegevens kunnen al na twee dagen verdwijnen, en DB kent geen unieke order-ID-index. | **Fase 3:** duurzame niet-geautoloadde WordPress-opties per betaal-ID, bestaande transients migreren, reeds opgeslagen orders na transient-verval terugvinden; regressietests. Open: unieke index/migratie, gelijktijdige callbacks, nog niet opgeslagen betaalorders na 2 dagen, e-mail-ambigue retries en refunds. |
| P06 | **Hoog** | `includes/webhooks/webhook-handler.php`, `includes/helpers/class-payment-helpers.php` | Webhooksecret is optioneel en als queryparameter verwerkt. Zelfs met Mollie API-herverificatie blijft het verstandig initiatie-/originecontrole, replay- en rate-control te verifiëren. | Veilige webhook-identiteitscontrole en secret-rotatie, zonder live URL's te breken. |
| P07 | **Hoog, branchfix** | `assets/js/pontifex-oi-testmode.js`, `includes/helpers/class-registration-validator.php`, `includes/class-frontend.php` | Testmodus verwijdert HTML-`required`; PHP accepteerde lege contactgegevens/ongeldige geboortedata/productkeuzes. | **Fase 3:** PHP valideert verplichte velden, geboortedata, e-mail, telefoon, examen/taal/materiaal/extra opties voordat Mollie wordt aangeroepen, onafhankelijk van client/testmodus. Echte browser-/testmode-regressie nog nodig. |
| P08 | **Middel, grotendeels branchfix** | `includes/class-frontend.php`, `includes/helpers/class-registration-validator.php` | Niet-gevalideerde kandidaat- en planningfilterarrays konden data verschuiven of PHP-fouten opleveren; open prijs-AJAX accepteerde slechte typen. | **Fase 1-3:** typed filtervalidatie, `wp_unslash`, DOB/achternaam/klantcontrole, publieke AJAX-prijsinputs en max 100 batch-items. Negatieve WordPress-fixtures. Nog te testen: externe planningwaarden, betaling op staging en volledige frontend. |
| P09 | **Hoog, branchfix + regressierisico** | `includes/config/producten-prijzen.php`, `includes/helpers/class-payment-helpers.php`, `includes/class-frontend.php` | **Reproduceerbaar in CI:** een geldig REST-prijsverzoek gaf HTTP 200 met `raw_price=0`; ook rechtstreekse prijsberekening gaf nul omdat de vijf productcatalogusarrays buiten `$GLOBALS` vielen wanneer PHP-includes in een lokale scope draaiden. De dubbele PHP/JS-prijslogica en hardcoded weekendprijs blijven aanvullende risico's. | **Aangepast op branch:** catalogusarrays expliciet globaal binden; REST-prijssmoke eist een positief bedrag. De laatste GitHub Actions-run moet dit nog bewijzen; voer daarna prijs/BTW/kandidaat/weekendregressies en echte checkoutproeven uit. |
| P10 | **Middel** | `includes/class-frontend.php`, `includes/frontend-asset-guard.php` | Frontend registreert assets aanvankelijk breed; guard verwijdert hooks afhankelijk van inhoud/slugs. | Assets alleen waar nodig en betrouwbare shortcode-/block-/template-detectie; test dynamische content en page builders. |
| P11 | **Middel, deels branchfix** | `includes/api/class-soap-client.php` | SOAP `trace` stond op 1 terwijl SOAP requests gevoelige gegevens kunnen bevatten; SOAP-endpoint uit instellingen en timeout/response-beleid vereisen nadere verificatie. | **Aangepast:** `trace` staat standaard uit en een runtimecheck controleert dit. Open: endpointrestricties en veilige providerfoutverwerking met SOAP-sandbox. |
| P12 | **Middel, branchfix** | `uninstall.php`, `.github/workflows/wordpress-compatibility.yml` | Multisitepad liet tabellen staan; verkeerde cronhook en onvolledig opgeschoonde options/transients. | **Fase 3:** vaste, uitsluitend tijdens uninstall uitgevoerde site-specifieke cleanup met multisite-loop, drie bekende tabellen, cron, secrets, transient-prefixen en permanente idempotency flags. Geïsoleerde single-site uninstall-test toegevoegd; multisite staging hertest vereist. |
| P13 | **Middel, branchfix** | `composer.json`, `composer.lock`, `pontifex-oi.php` | Composer vereiste PHP ^8.0 en declareerde een PSR-4-root die niet paste bij de legacy klassebestandsnamen. | **Fase 3:** PHP-constraint naar ^8.1, GPL-2.0-or-later opgegeven en Composer-classmap voor de werkelijk aanwezige interne klasselocaties; bijbehorende lockhash in sync. Geen massale bestandsrename; volledige WPCS/PSR-refactor blijft optioneel. |
| P14 | **Middel** | `README.md`, `readme.txt` | README noemt een `templates/` map die niet bestaat. `Tested up to: 7.1` en geclaimde CI-verificatie zijn opnieuw te bewijzen op de huidige head. | Documentatie naar feitelijke paden, tests en publicatievorm bijwerken. |
| P15 | **Middel, deels branchfix** | `.github/workflows/wordpress-compatibility.yml`, `tests/` | De repo had vooral tekst-grep-invarianten, zonder volledige PHPUnit/PHPCS/Plugin Check. | **Fase 1-3:** PHP/JS-syntax, Composer audit, WP activatie op PHP8.1/8.3, CSV/REST/DOB/BTW/webhook-state-tests, JS-XSS en geïsoleerde uninstall. Nog open: WPCS/Plugin Check, HTTP/browser en provider-sandboxtests. |
| P16 | **Laag–middel** | `admin/`, `public/`, `assets/` | Procedurele templates, gemengde conventies, veel consoledebug, gedeeltelijk Engelstalige/Nederlandstalige namen, historische patchcomments, globals en dubbele prijs/admin-scripts. | Eenheid in naamgeving/structuur na contractanalyse; houd shortcode IDs, option keys, database/tablenamen en externe hooks compatibel. |
| P17 | **Middel, branchfix** | `includes/helpers/class-mail-helpers.php` | Een globale `wp_mail_charset`-filter en `wp_mail_failed`-listener beïnvloedden ook e-mailgedrag/logging van andere WordPress-plugins. | **Aangepast:** beide globale hooks verwijderd; eigen Pontifex `wp_mail()`-headers behouden expliciet `Content-Type: text/html; charset=UTF-8`. SMTP-/echte mailflowtest nog vereist. |

| P18 | **Hoog, branchfix** | `assets/js/table.js`, `assets/js/sidebar.js` | Planningwaarden vanuit SOAP/AJAX kwamen via HTML-template-strings onescaped in de tabel en sommige keuzelijsten. | **Aangepast:** gedeelde escaping in tabel/kaarten; sidebaropties via tekstnodes. Browser- en JS-regressietest vereist. |
| P19 | **Middel, branchfix** | `assets/js/pontifex-oi-admin.js` | Beheerscript logde het gelokaliseerde object met nonce en plaatste AJAX-fouttekst onescaped in HTML. | **Aangepast:** nonceconsolelogging verwijderd en fouttekst geescaped. |
| P20 | **Middel, handmatige UX-regressie nodig** | `assets/css/*.css` | 667 regels/voorkomens van `!important` verspreid over vijf CSS-bestanden. Ze wijzen op forse override-/specificiteitsschuld, niet automatisch op een zichtbare bug. | Begin met audit op cascade en overtollige overrides. Verwijder alleen met volledige responsive/browser/keyboard-regressietest. |

## Map- en bestandsinventaris (bronreview)

- **Root:** `pontifex-oi.php` bevat versie/header, tabelschema, activatie, cronmigratie en globale includes; `uninstall.php` heeft levenscyclusgaten. `README.md` en `readme.txt` zijn aanwezig; historische audit is niet actueel; `composer.lock` aanwezig.
- **`includes/api/`:** SOAP-planning, met registratie uitsluitend als bewuste no-op (P01); opslag/reconciliation en foutafhandeling vragen externe sandboxverificatie.
- **`includes/cron/`:** dagelijkse planning en opgeschoonde records; tijdzone, conflictvrij plannen, race/lock en schema migraties gericht testen.
- **`includes/helpers/`:** registratie, mail, prijs, CSV/kaartvelden en admin AJAX; bevatten security- en duurzaamheidspunten.
- **`includes/config/`:** product- en prijsmapping; te consolideren na prijsregressietests.
- **`includes/webhooks/`:** Mollie-callback met lock en duurzame stage-opties, legacy-transientmigratie; gelijktijdige callbacks, refund/replay en onzekere mailretries zijn nog niet bewezen.
- **`admin/`:** instellingen, credentials, AJAX, persoonsdataoverzicht en CSV-export; prioriteit escaping en autorisatie.
- **`public/`:** planning, registratie, succes-shortcode, mailtemplates; meerdere hardcoded permalinks; mogelijk misleidende succesbevestiging zolang P01 bestaat.
- **`assets/js/`:** filters, tabel, kandidaten, materiaal, prijzen, frontendvalidatie, admin en testmodus. Modules gebruiken jQuery, globals en HTML-stringrendering; gerichte QA voor DOM-XSS en redundante listeners.
- **`assets/css/`:** vijf gescheiden stylesheets voor shared, admin, planning, registratie en succes; cascade/scope, WCAG en ongebruikte regels nog visueel onderzoeken.
- **`languages/`:** POT aanwezig; vertaalbaarheid en dynamische strings controleren.
- **`.github/workflows/`:** compatibiliteitsmatrix, dependency audit en een aantal privacy-asserties aanwezig, maar onvoldoende voor diepgaande runtime- en securityzekerheid.


## Historiek fase 2 (gemerged via PR #7)

- AJAX-filteropties gebruiken nu dezelfde type/allowlistcontrole als de publieke planningroute. De WP-CLI-suite controleert zowel geldige filterwaarden als ongeldige en onverwachte sleutels.
- De Composer-beveiligingsaudit probeert uitsluitend na een aantoonbare Packagist-time-out maximaal driemaal opnieuw; echte advisories en overige fouten blijven CI-blokkerend.

- Publieke REST-planning en AJAX-planning normaliseren dezelfde filtertypen en geven 400 bij ongeldige waarden; REST-prijsfiltering voorkomt onveilige arrays en onbedoelde exception-details.
- **Geïdentificeerd door een echte negatieve/positieve CI-regressie:** een correcte examprijs werd `0` door vijf ongebonden productcatalogusarrays (`catalog=0000`). De configuratie bindt deze nu expliciet in `$GLOBALS`; acceptatie volgt pas na groene laatste CI.
- SOAP-request/response-tracing staat standaard uit; sitebrede e-mailfilters zijn verwijderd om bijwerkingen bij andere plugins te voorkomen.

- Veilig admin-CSV exportpad gebruikt `admin-post.php`, `manage_options`, `check_admin_referer`, CSV-formulebescherming en limiet van 5000 records.
- Publieke planningtabel en accordion escapen dynamische tekst en attribuutwaarden; sidebar bouwt opties als DOM-tekst.
- Registratiegegevens en beheernonces worden niet meer als complete objecten in debugconsole gelogd.
- Payment-AJAX controleert nonce voor inputverwerking en valideert kandidaatarrays zonder rijverschuiving; WP-CLI cases voor ongeldige structuren toegevoegd.
- Betaald-status op de bedankpagina wordt niet langer gelijkgesteld met een bevestigde exameninschrijving.
- CI aangevuld met `node --check`, `tests/js-render-safety-smoke.js` en `tests/wp-cli-security-smoke.php`. Nieuwe tests zijn alleen succesvol te verklaren wanneer de laatste head-SHA twee groene jobs heeft.
- **Fase-2 historiek:** de toenmalige SOAP-marker was misleidend; fase 3 verwijdert die. De externe registratieverplichting blijft onbekend.

Primaire referenties: [WordPress admin_post](https://developer.wordpress.org/reference/hooks/admin_post_action/), [WordPress nonces](https://developer.wordpress.org/apis/security/nonces/), [Mollie klassieke webhooks](https://docs.mollie.com/reference/webhooks).


## Fase 3 — lifecycle en onderhoudbaarheid (op main sinds PR #8)

- Mollie-webhook verwerkt en bewaart afgeronde stage-status duurzaam als niet-geautoloadde option per gehashte payment-ID, migreert oude status-transients en kan reeds lokaal opgeslagen orderpayloads veilig hervinden.
- SOAP-registratie blijft bewust niet uitgevoerd (vendorcontract ontbreekt); de onware `soap_sent` fase is vervangen door `local_registration_only`.
- Server-side verplichte contactvelden, e-mailadres, telefoon, geboortedatum, examen, taal, materiaal en extra-producten gevalideerd, vóór een Mollie-betaling.
- Betaling start niet wanneer de pending orderdata niet veilig in WordPress kan worden bewaard; bedrag mag niet €0 zijn en tolerantie is 1 eurocent.
- PHP-prijs-AJAX valideert scalar inputs/batchgrootte; geen `debug_info`-response.
- Uninstall werkt op alle gevonden multisite-blogs voor eigen tabellen, options, locks/transients en cron. Dit is bewust destructieve code **alleen bij WP-uninstall**, niet bij deactiveren.
- Composer PHP 8.1, correcte classmap en lockhash. Niet alle WordPress Coding Standards of asset-/CSS-schuld zijn hiermee opgelost.
- Tests bevatten positieve/negatieve registraties, duurzame webhookstatus, herstel uit registratie, prijs, btw, uninstall en bestaande privacy-/XSS-scenario's.

**No-Go voor productie:** niet bevestigd of externe SOAP-registratie nodig is; Mollie-webhook mist nog bewezen concurrerende callback/idempotente mailreconciliatie, historische SQL-dupliceermigratie en late eerste betalingen (pendingorderdata heeft huidige 2-daagse bewaartermijn). Externe betaal-/mail-/SOAP-tests en multisite/browsercontrole ontbreken. **Groene CI is bron/testbewijs, geen vrijgave van deze open blockers.**

## Gewenste structuur (voorstel, niet blind doorvoeren)

```
pontifex-oi/
├── pontifex-oi.php                # kleine, stabiele bootstrap
├── uninstall.php                  # expliciet, volledig dataretentiebeleid
├── composer.json / composer.lock
├── readme.txt / README.md
├── includes/
│   ├── class-plugin.php           # lifecycle + hooks
│   ├── Admin/                      # instellingen, export, permission checks
│   ├── Application/                # registratie + betalingsorkestratie
│   ├── Infrastructure/             # SOAP, Mollie, repositories, cron
│   ├── Http/                       # REST/AJAX/webhook adapters
│   └── Support/                    # validatie, logging, formatteren
├── templates/                     # planning, registratie, e-mails
├── assets/{css,js}/
├── languages/
├── tests/{Unit,Integration}/
└── .github/workflows/             # buiten distributie-ZIP
```

Dit is **geen verplichte WordPress-mappenstructuur**: de huidige `admin/`, `includes/`, `public/` conventie is op zichzelf geldig. Kies PSR-4 alleen als namespaces, classmap en deployment consequent op elkaar aansluiten. Rename uitsluitend samen met alle callers, templates en hooks en na werkende regressietests.

## Fasering en acceptatie

1. **Fase 1: inventarisatie & risicobeperking.** Afgerond op aparte auditbranch, zonder merge naar `main`.
2. **Fase 2: bedrijfslogica & beveiliging.** **Deels uitgevoerd:** CSV-export afgeschermd, frontend- en adminescaping verbeterd, betaalbevestiging onderscheiden van registratie, kandidaat-/REST-validatie en productcatalogusinitialisatie hersteld. Nog open: vendorcontract/registratieverwachting, webhook-idempotency, duurzame transactiestatus, server-side validatie en HTTP-tests. Geen live betalingen zonder sandboxproef.
3. **Fase 3: onderhoudbaarheid.** Scheid verantwoordelijkheid in kleinere modules, corrigeer Composer/autoload, consolideer prijsberekening en assetloading, pas WPCS/PHPCS en escaping consequent toe. Breek geen externe identifiers of databankschema zonder migratie.
4. **Fase 4: tests, release en documentatie.** PHPUnit en integratiefixtures, WordPress 6.0/PHP 8.1 + doelruntime, Mollie testbetalingen/webhook retries, SOAP sandbox, JS DOM-XSS/browser/WCAG, Plugin Check, composer audit, zip-verificatie en rollback. Update README/readme/changelog alleen met geverifieerde claims.

## QA-matrix voor de eerstvolgende herstelronde

- HTML-payload in titel/labels/waarden van inschrijving verschijnt uitsluitend als tekst.
- Frontendformulieren produceren geen persoons-/betalingsdata in browserconsole, ook niet bij foutresponses.
- CSV: onbevoegde/CSRF-verzoeken blokkeren; Excel-injectiefixtures krijgen veilig exportgedrag.
- Mollie: webhook vóór en ná de 7-daagse grens, dubbele gelijktijdige callbacks, pending/paid/refund/chargeback, provider-timeout, ontbrekende ordertransient.
- SOAP: contractbevestigde `sendRegistration`, fout/timeout, retry en aantoonbare externe ontvangst.
- Data: migratie met reeds bestaande `order_id` duplicaten, multisite-uninstall, cron-activatie en opties.

**Geen automatische merge/deploy.** PR-checks op de laatste SHA en staging-evidence zijn vereist vóór release.

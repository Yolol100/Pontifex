=== Pontifex OI ===
Contributors: jouw-wordpress-gebruikersnaam
Tags: pontifex, inschrijven, examens, soap api, planning, registratie, betaling, shortcodes
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 of nieuwer
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pontifex-oi
Domain Path: /languages

Samenvatting (max 150 tekens): 
Meerdere shortcodes voor Pontifex-inschrijving, planning en betaling. Volledig OOP, veilig, uitbreidbaar en toekomstgericht.

== Beschrijving ==
**Pontifex OI** biedt een complete integratie met de Pontifex Open Inschrijvingen SOAP-API. Je kunt actuele examenplanningen tonen, kandidaten laten registreren, en succesvolle betalingen afhandelen – alles via overzichtelijke shortcodes.  
De plugin is volledig OOP, Composer-ready, i18n, AVG-proof en ontworpen voor uitbreidbaarheid: templates en hooks zijn overridebaar. **Let op:** de gebruiker dient zelf enkele instellingen te verzorgen voor volledige werking.

== Installatie ==
1. Upload de map `pontifex-oi` naar `/wp-content/plugins/`.
2. Activeer de plugin via het WordPress-dashboard.
3. Voeg je Pontifex API-gegevens (user_identifier, company_identifier, SHA256-hash) handmatig toe in de code, bijvoorbeeld in je `wp-config.php` of via een filter (settingspagina volgt in een toekomstige update).
4. Voeg de benodigde shortcodes toe aan de gewenste pagina’s (zie hieronder).

== Shortcodes ==
**Deze plugin bevat meerdere shortcodes, elk met een eigen functie. Plaats ze op aparte pagina’s naar wens.**

* `[pontifex_oi_planning]`  
  Toont de actuele examenplanning.  
  _Gebruik:_ Plaats deze shortcode op de pagina waar je kandidaten het aanbod wilt laten zien.

* `[pontifex_oi_registration]`  
  Toont het inschrijfformulier voor een examen.  
  _Gebruik:_ Voeg deze shortcode toe aan een aparte registratiepagina.  
  _Let op:_ De shortcode verwacht standaard een examen-ID als parameter: `[pontifex_oi_registration exam_id="12345"]`

* `[pontifex_oi_payment_success]`  
  Toont een bevestiging na succesvolle betaling.  
  _Gebruik:_ Plaats deze shortcode op de bedanktpagina die wordt weergegeven na succesvolle betaling.

**Let op:** Raadpleeg de documentatie voor extra parameters per shortcode. Gebruik altijd aparte pagina’s voor elke stap in het proces. Zet in je menu of flow duidelijke verwijzingen naar deze pagina’s.

== Handmatige instellingen en vereisten ==
- **API-gegevens:** Moeten nu handmatig in code of via een filter worden toegevoegd.
- **Permalinks:** Zorg voor werkende WordPress-permalinks om shortcodes correct te laten functioneren.
- **Template overrides:** Wil je het uiterlijk aanpassen? Kopieer de template-bestanden naar je thema en bewerk ze daar.
- **Standaard e-mails, hooks en filters:** Zie de broncode of vraag een overzicht aan voor alle beschikbare hooks.

== Veelgestelde Vragen ==
= Moet ik iets handmatig doen na installatie? =
Ja: je moet API-gegevens toevoegen en de shortcodes op aparte pagina’s plaatsen.

= Is de plugin AVG/GDPR-proof? =
Ja, er worden geen persoonsgegevens opgeslagen in je WordPress-site.

= Kan ik de plugin uitbreiden? =
Ja, alle templates en veel functionaliteit zijn overridebaar via thema of eigen plugin.

== Screenshots ==
1. Instellingenvoorbeeld (indien geïmplementeerd).
2. Examenplanning met filters (front-end).
3. Inschrijfformulier (front-end).
4. Betaalbevestiging/bedanktpagina.

== Upgrade Notice ==
= 1.0.0 =
Eerste versie: planning, inschrijving en betaling via afzonderlijke shortcodes. Handmatige instellingen noodzakelijk.

== Changelog ==
= 1.0.0 =
* Eerste release met planning-, registratie- en betaling-shortcodes.
* Volledig OOP, Composer-ready, testbaar en uitbreidbaar.
* Overridebare templates, hooks en internationale ondersteuning.

== Credits ==
* Pontifex Certificatie voor de API
* Bijdrage: jouw-naam-of-team

== Support ==
Hulp of maatwerk nodig? Mail: [jouw-supportmail-of-link]
# Pontifex — provider- en stagingacceptatie (16 oktober 2026)

**Status:** Repositorytests kunnen gedeeltelijk PASS zijn; **extern leverancierbewijs blijft NOT_RUN**. Projecteigenaar/websitebeheer moet de testsite en de contracten autoriseren. Voer geen betalingen, refunds, persoonsgegevens of e-mails uit op productie voor acceptatie.

## Fase 1 — bron, identiteit, rechten en herstel
- Repository: `Yolol100/Pontifex`, implementatie PR #15; gekozen target moet exact de geverifieerde commit en pluginversie tonen.
- Vereist: geïsoleerde WordPress-staging, eigen database, browser, testmailbox, HTTPS webhook URL, test-Mollie API key **alleen in beveiligde staginginstellingen**, staging SOAP user/hash en geautoriseerde WSDL.
- Leg vóór test een databaseback-up en een geslaagde terugzetproef vast. Installeer geen snapshot op een live klantomgeving.
- Raadpleeg `docs/RELEASE-QA.md`; wijs verantwoordelijke aan voor privacy/bewaartermijn, betaalreconciliatie en extern registratiecontract.
- **Geen credentials in GitHub Issues, prompts, logs, workflow-uitvoer of dit document.**

## Fase 2 — Mollie sandbox (exacte operationele acceptatie)
1. Betaal in **Mollie testmode** met één fictieve kandidaat en één geldig product; controleer provider-ID, serverbedrag/btw, unieke DB-rij, retourpagina en beide mailboxen.
2. Herhaal dezelfde webhook met een provider-gecontroleerde betaling: precies één registratie en geen nieuwe mail; stuur twee gelijktijdige callbacks en controleer DB-unique index.
3. Test `pending`, `failed`, `expired`, `canceled`; geen succesmail of lokale betaalde registratie. Test latere betaalstatus na meer dan 48 uur op de testomgeving.
4. Maak een refund en chargeback aan op een **betaalde testbetaling**. Controleer nieuwe `pontifex_reconciliation_<hash>` en beheerwaarschuwing, zonder automatische dubbele mail.
5. Simuleer na vooraf vastgelegde testopzet mailuitval, SMTP-time-out en PHP-crash direct na de eerste `wp_mail`. Automatische dubbelzending is niet toegestaan: handmatige interventie/een provider-emailbewijs is vereist.
6. Verifieer de veilige 30-daagse **technische** pending-retentie en `pontifex_review_<hash>`. Deze termijn is geen juridische norm; de eigenaar moet een passende termijn en herstelproces vastleggen. Betalingen na opschoning vereisen handmatige koppeling.
7. Op bestaande data: inventariseer dubbele `order_id`-waarden, maak back-up, los conflicten met betaalprovidergegevens op; voer pas dan het unieke indexmigratiepad uit. Nooit willekeurig een rij verwijderen.

**Fail closed:** zonder bewijs van mailverzending mag `mail_attempted` niet automatisch worden gewist, want dat kan dubbele berichten veroorzaken. De code registreert slechts een handmatige herstelbehoefte.

## Fase 3 — SOAP-contract en extern registratiegedrag
- De huidige SOAP-client heeft aantoonbaar de planningmethode `getWalkInPlanning`. `sendRegistration()` is een bewuste NO-OP en verzendt niets.
- De publieke Pontifex-site noemt API/MijnPontifex, maar dat is **geen** bewijs voor een bepaalde WSDL-operatie, kandidaatpayload, foutcode of proces voor inschrijving.
- Verkrijg schriftelijk van de leverancier: of registratie buiten de lokale WordPress-opslag verplicht is, welke WSDL/service/operatie de inschrijving verwerkt, inputstructuur, testaccount, provider-registratie-ID en idempotency-/retrycontract.
- Gebruik uitsluitend een geautoriseerde, read-only SOAP-`__getFunctions()` inspectie op de testomgeving, met gevoelige request tracing uit. Pas na contractbevestiging outbound testregistratie toe met fictieve kandidaten.
- Geen automatische `SOAP_VERZONDEN`/succesclaim in e-mails zolang er geen extern registratienummer is.

## Fase 4 — privacy en WordPress admin
- Verifieer onder **Gereedschap → Persoonsgegevens exporteren/verwijderen** de Pontifex-exporter en -eraser op een bevestigd fictief e-mailadres; exporteer geen gegevens van andere kandidaten.
- Eraser staat standaard **fail-closed** voor financiële/examendata. Een bevoegde eigenaar kan voor afzonderlijke betaal-ID's de filter `pontifex_oi_allow_buyer_data_erasure` toestaan nadat bewaartermijnen zijn vastgesteld. Andere kandidaatdata blijft expliciet behouden/gemeld.
- Toets privacyverklaring, verwerking door Mollie/SMTP/Pontifex, bewaartermijnen, recht op correctie, datalek- en incidenteigenaarschap met de verantwoordelijke.
- Test WordPress-rollen en multisite: uitsluitend admin ziet betaalreview, niet-autoriseerde gebruikers zien geen persoonsgegevens; uninstall op een testsite na DB-back-up.

## Fase 5 — vrijgave
| Bewijs | Nu | Vereist voor GO |
|---|---|---|
| GitHub SHA-bound PHP/WordPress smoke | CI controleren | beide jobs groen op exacte PR/main SHA |
| Staging met echte Mollie test-key | NOT_RUN | betaal-, refund-, webhook-/retry- en mailbewijs |
| Staging SOAP & vendorcontract | BLOCKED | geautoriseerde documentatie + testresponse of uitdrukkelijke local-only opdracht |
| Privacybeleid/retentie | BLOCKED | schriftelijke eigenaarbeslissing + export/erase validatie |
| Database migratie/rollback | NOT_RUN | duplicate-inventaris en restoreproef |
| Browser/WCAG/SMTP | NOT_RUN | positieve + negatieve praktijkflows |

**Besluit:** zonder vendor- en stagingbewijzen blijft de productierelease NO-GO. CI en mocktests zijn geen vervanging voor ontvangen betaling, mail of externe inschrijving.

## Officiële referenties
- https://docs.mollie.com/reference/webhooks
- https://docs.mollie.com/docs/triggering-fulfilment
- https://docs.mollie.com/reference/testing
- https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/
- https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/

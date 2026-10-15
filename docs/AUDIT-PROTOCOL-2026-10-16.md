# Pontifex — integraal broncode-auditprotocol (16 oktober 2026)

**Target:** `Yolol100/Pontifex`, startref `main@60df62443332a513d1d79e2ffca8612c9779f7c5`  
**Auditbranch:** `audit/comprehensive-source-review-2026-10-16`  
**Eigenaar:** `wordpressqualityarchitect`, procescontroller `webactueel-workflow`, onafhankelijke vrijgave-/QA-grens `website-qa-checklist`.  
**Doel:** bewijsgebonden inventarisatie **van ieder tracked bestand**, identificeren van aantoonbare problemen per map, bestand en functie, en testen van de risicovolste interfaces. Een automatisch bestandssignaal is géén bewezen functionele fout.

## Herbruikbare auditprompt / uitvoeringscontract

> **DOEL.** Audit de exacte GitHub-main SHA van Pontifex zonder bestanden over te slaan. Controleer bestandsnamen, locaties, dependencies, WordPress- en PHP-standaarden, beveiliging, persoonsgegevens, betalingen, externe koppelingen, JS, CSS, markup, cron, data/migraties, documentatie, code duplicatie en tests.
>
> **CONTEXT.** Het is een WordPress-plugin met betaal-, SOAP-, mail- en registratiepaden. Vorige wijzigingen zijn in PR #7/#8/#9 gemerged, drie release-issues (#10/#11/#12) bestaan nog. Eerdere groene CI telt alleen voor de werkelijk gedekte scenario's, niet als vrijgave.
>
> **FASEN.** (1) vastleggen SHA, tree, alle SHA256's, source ledger en exact functie-/hookregister; (2) per file geautomatiseerde lint-, patroon-, contract-, duplicaat- en dependenciescans; (3) handmatige risicogebonden regel-/functie-inspectie; (4) positieve, negatieve, grens-, abuse-, idempotency-, retry- en lifecycle-tests; (5) onafhankelijke QA, classificatie en bewijs-readback; (6) rapport en prioriteiten, zonder ongeteste fixes naar `main`.
>
> **PROMPTTECHNIEKEN.** `plan-and-solve` voor de fasen; `prompt-chaining` bij bewijs-handoffs; `retrieval-grounding` tegen actuele WordPress/Mollie/Composer-bronnen; `gap-only-optimization` bij bestaande geslaagde checks; `hypothesis-driven-debugging` per defect; `adversarial-validation` bij webhook/REST/AJAX/PII; `independent-verifier` met CI en echte environment-readback. Geen synthetische expertscores of fictieve geslaagde scenario's.
>
> **CLAIMREGELS.** Koppel elke bevinding aan `path:line`, bewijsclass (confirmed / likely / manual test needed / false positive), ernst, impact, herstelplan en vereiste hertest. Bewaar ongewijzigde publieke hooks, shortcodes, REST-vormen, database en providers tenzij migratie-/compatibiliteitsbewijs beschikbaar is. Geen uitgaande Mollie/SOAP/SMTP, databaseverwijderingen of productie-deploy voor een audit. Gebruik aparte testomgeving en herstelpad.
>
> **OUTPUT.** (A) complete file-matrix, (B) onderbouwde fouten en technische schuld, (C) uitgevoerde tests + niet-uitgevoerde tests, (D) concrete prioriteiten/Go-No-Go. Maak geen claim dat syntactische lint een functionele end-to-end-proef is.

## Fasering en controlepoorten

| Fase | Scope | Acceptatiecriterium |
|---|---|---|
| 0. Bronnen/nulmeting | projectregistry, Skills, GitHub tree, Library, YouTube-index, actuele officiële docs | Zevenstappenbronledger; exacte `main`-SHA en volledige `git ls-files`; uitsluiten van gissingen |
| 1. Structuur | alle tracked bestanden, naamgevingsconventies, PHP/JS/CSS-doc- en lockfile-locaties, ignore/release packaging | Elke file exact eenmaal in manifest; hash, regels, type, auteur/gebruik niet verzinnen |
| 2. Code en security | statische per-file-patternscan, hooks, REST/AJAX, SQL, CSRF, escaping, PHP/JS/CSS, classmap, i18n, privacy | Positiegebonden signalen; hoog risico handmatig beoordelen |
| 3. Koppelingen en data | Mollie webhook/prijs/idempotentie/refund, SOAP, SMTP, cron, persoonsgegevens, registratie/retentie, multisite | Heldere scheiding bewezen lokaal gedrag versus niet-geteste leverancier |
| 4. Tests | PHP lint, Node syntax, Composer validate/audit/install, WP-CLI scenario-suite, plugin check/WPCS waar uitvoerbaar | GitHub Actions op audit-head; failure leidt tot aparte root-cause en regressie |
| 5. Onafhankelijke QA | code-expertise + website QA WPQ-P, risico en toestemmingsgrenzen | Iedere claim 'passed/failed/not_run' met link en target; geen release-Go zonder staging |
| 6. Closure | rapport per file, reparatiebacklog, PR/issue links, rebasecheck | Branch-readback en rapport; alleen minimale bewezen fixes na afzonderlijke gate |

## Bronnenledger

| Bronklasse | Status | Provenance en beperking |
|---|---|---|
| Projectbronnen | checked / no relevant project manifest | Actieve Webactueel-projectregistry bevat geen Pontifex-entry; geen extern SOAP-businesscontract aangetroffen |
| Skills | checked | `webactueel-workflow`, `wordpressqualityarchitect`, `website-qa-checklist` met code-, audit- en releasegrenzen |
| Repository | checked | GitHub boom `main@60df624`: **55 tracked blobs, 569187 bytes, tree not truncated**; drie open release-issues |
| ChatGPT Library | checked | Eerdere portfolio/WordPress-kennis gevonden, maar niet autoritatief voor providercontract |
| YouTube | checked / no applicable Pontifex corpus | WordPress-kanaalcatalogus aangetroffen; geen bewezen relevant Pontifex SOAP/Mollie-caption/commentcorpus |
| Internet | checked | WordPress Plugin Handbook (security, privacy, Plugin Check) en Mollie primaire documentatie; binnen concrete bevinding citeerbare URL |
| QA/uitvoering | in progress | CI en file-by-file audit uitvoeren; ontbrekende live Mollie/SOAP/browser/SMTP-tests blijven `not_run` |

## Safety en bewijs

- **Exclusief broncode-audit**: er is geen door ons geverifieerde live Pontifex-installatie of vendor sandbox voor deze opdracht.
- `main` wordt niet herschreven of productief uitgerold op basis van heuristische signalen. Het bestaande pad `backup/main-before-2026-10-15-modernization` is historische **code**-rollback, geen databasebackup.
- `tests/` bevat repo-native checks. Static signalen en coding-style-notities zijn advies totdat de exploit of functionele afwijking is bevestigd.
- Open blockers uit [release QA](RELEASE-QA.md) en GitHub-issues [#10](https://github.com/Yolol100/Pontifex/issues/10), [#11](https://github.com/Yolol100/Pontifex/issues/11), [#12](https://github.com/Yolol100/Pontifex/issues/12) moeten expliciet terugkomen; geen automatische sluiting.

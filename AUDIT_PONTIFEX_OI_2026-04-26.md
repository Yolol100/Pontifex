# Auditrapport Pontifex OI (2026-04-26)

Technische en security-audit op codebasis, met focus op activatie, hooks, compatibiliteit, security, performance en release-readiness.

Belangrijkste bevindingen:
- Kritiek: plugin bootstrap doet altijd `require_once vendor/autoload.php` terwijl in deze repo alleen `vendor.zip` aanwezig is.
- Hoog: webhook-idempotency is alleen transient-gebaseerd (15 min) en kan daarna dubbel verwerken.
- Hoog: frontend laadt alle assets site-wide op iedere pagina.
- Hoog: PII wordt naar PHP error log geschreven.
- Middel: publieke AJAX/REST endpoints zonder rate limiting/nonce voor read-acties.
- Middel: package hygiene/release metadata is niet WordPress.org-conform (`README.md` i.p.v. `readme.txt`, Tested up to verouderd).

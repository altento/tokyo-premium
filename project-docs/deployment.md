# Deployment notes
Updated 2026-11-25

Для переноса репозитория и запуска PHP-версии на другом Windows-компьютере см. [подробную инструкцию продолжения](continue-on-another-computer.md). Она отдельно объясняет, почему SQLite и секреты не входят в Git.

## 2026-11-25 — current release and host status

- The current application source is `d846618c014ef7bbb8b1495e8cecbe674f911b22` (`Finish Tokyo Premium site pass`). The latest `main` commit is `62c0db88cba35b24837b12a5f42facd93a1c90d7` and only updates documentation. Pages workflow [45509721695](https://github.com/altento/tokyo-premium/actions/runs/45509721695) completed successfully for that commit. The published menu shows 50 public dishes: [open the static demo](https://altento.github.io/tokyo-premium/menu.html?audit=20261125).
- Before publication, the PHP/SQLite integration passed 236 assertions. The visual-fidelity suite covered 11 route types in PHP and Pages modes; focused checks covered menu interactions, booking, Omakase, account demo, motion behavior and all 50 public dish details. See [testing-report.md](testing-report.md) for the recorded scope. These local checks do not verify the SprintHost PHP runtime.
- SprintHost already has an older PHP version and an active SQLite database outside `public_html`. The active file is `tokyo-restaurant-public50.sqlite`; the host database is at migration 21, while the published code expects migration 22. The file and directory permissions are restricted, and `.htaccess` blocks direct database downloads. No database file was modified in this audit.
- The SprintHost test domain currently serves HTTP only. The panel reports HTTPS unavailable on the current tariff; its balance is 0 ₽. No upgrade or purchase was made. Do not use real passwords, personal data or payment details on that HTTP site. The PHP release remains pending HTTPS, a pre-deploy database backup and a controlled migration check.
- GitHub Pages is the current public preview. It is static and cannot provide PHP sessions, server authentication, durable SQLite records, real booking submission or payment processing.

## Public preview
- Repository: https://github.com/altento/tokyo-premium
- Public URL: https://altento.github.io/tokyo-premium/
- GitHub Actions workflow: `.github/workflows/pages.yml`
- Each push to `main` starts the static export and Pages deployment.

### Local static preview

For the full PHP/SQLite application on a home Windows computer, use `Start-Tokyo.ps1` and follow [home-hosting.md](home-hosting.md). The launcher enables installed PDO SQLite, keeps data outside the repository, and uses a public-route allowlist router. Private Tailscale Serve access requires both devices to join the same network. Payment callbacks require a public HTTPS route and merchant configuration.

From the repository root, serve the exported `docs/` folder:

```powershell
python -m http.server 8937 --bind 127.0.0.1 --directory docs
```

Open `http://127.0.0.1:8937/`. This is the browser-only Pages build; it does not run PHP or SQLite. Stop the server with Ctrl+C.

The workflow installs PHP CLI + SQLite, starts the existing PHP app, runs `tools/export-pages.php`, uploads `docs/`, and deploys it. The export writes an HTTPS `sitemap.xml` from public routes and a matching `robots.txt`; the local-only account and cart pages are marked `noindex` and omitted from the sitemap. The workflow does not package the SQLite database.

### Local admin and account fixtures

The repository intentionally does not contain a populated SQLite file or reusable login password. For a local, disposable copy outside the web root, select its path before running the fixture seeder:

```powershell
$env:RESTAURANT_DB = Join-Path $env:TEMP 'tokyo-demo-fixture.sqlite'
php tools/seed-demo-data.php --apply
```

This creates one fictional account, 12 sample orders, 8 bookings, 10 contact messages, and 6 pending reviews, together with account notifications and loyalty examples. The password is generated randomly and printed once by the local command; store it only in a local password manager. The operation is atomic and idempotent. It does not send mail or charge a payment method. Do not run it against a production database unless you intentionally want these demonstration records there.

## Tokyo Premium 2.0 export

The exporter writes `offers.html`, active `collection-{slug}.html` pages, `set-builder.html` and active variant pages, `perfect-evening.html`, and `gift-certificates.html` alongside the original routes. It rewrites PHP-only links, copies local optimized assets, omits admin routes and locked Secret Menu detail routes, removes stale generated dish pages from prior builds, and checks that generated pages contain no PHP-only links. `tokyo-secret-demo.js` contains a public client-only preview list for the static Secret Menu interaction; it is not an access-control boundary. The database is never copied into `docs/`.

## Runtime boundaries
GitHub Pages cannot run PHP. Supported static actions store demo values only in this browser's `localStorage`, clearly marked as estimates or previews. They do not submit an order, booking, review or contact message to the restaurant. PHP hosting is required for SQLite, sessions, user authentication, the admin panel, server-priced orders, campaign rules, the loyalty ledger and online payments. A FreedomPay KZ hosted-checkout adapter has been added but remains disabled until a merchant account, secret key and public HTTPS PHP host are configured. No payment credentials, SMTP provider or live seating service are configured; no secrets belong in the static repository.

## Updating
Push to `main` and wait for the Pages workflow to finish. Inspect the workflow summary, then open the public URL in a fresh browser session and check at least the home, menu, dish, booking, cart, contacts, and profile pages.

## 2026-11-23 — latest verified publication

- Latest application commit: [`e780478`](https://github.com/altento/tokyo-premium/commit/e7804788ec3714fd67eb1226f58bebaf88b9dc8c); Pages workflow [45200002370](https://github.com/altento/tokyo-premium/actions/runs/45200002370) passed. Focused Playwright visual-fidelity checks passed locally and on the public home route at 1440 px and 390 px. Public measurements are 2617 px and 4708 px respectively, with matching viewport widths, four recommendation cards, no page errors and no failed requests. Public captures: [`visual-qa/home-final-cadence-2026-11-23/public/`](visual-qa/home-final-cadence-2026-11-23/public/). This verifies Pages only, not the PHP/SQLite runtime.
- Previous homepage commit: [`831a826`](https://github.com/altento/tokyo-premium/commit/831a8261f6fabf7fbaf737f118503d21d805e560); Pages workflow [45196587167](https://github.com/altento/tokyo-premium/actions/runs/45196587167) passed. The prior desktop capture is in [`visual-qa/home-density-followup-2026-11-23/public/`](visual-qa/home-density-followup-2026-11-23/public/).
- Latest application commit: [`fcd3525`](https://github.com/altento/tokyo-premium/commit/fcd3525452e84a17723bbd20608d2eaaa56aff9e); Pages workflow [45193974873](https://github.com/altento/tokyo-premium/actions/runs/45193974873) passed build and deployment. The public booking route passed the focused Playwright test at desktop and mobile widths; screenshots are in [`visual-qa/booking-floorplan-legibility-2026-11-23/public/`](visual-qa/booking-floorplan-legibility-2026-11-23/public/). This is static Pages verification, not PHP server verification.
- Earlier application commit: [`3e0fdce`](https://github.com/altento/tokyo-premium/commit/3e0fdce09f09f4b5c7ff1bef53a0dbd49a3f31a6); Pages workflow [45191130338](https://github.com/altento/tokyo-premium/actions/runs/45191130338) passed build and deployment. The public `/about.html` route passed the focused browser check at 390 px and 1440 px. Public captures and metrics: [`visual-qa/philosophy-density-final-pass-2026-11-23/public/`](visual-qa/philosophy-density-final-pass-2026-11-23/public/). This is static Pages verification, not PHP server verification.
- Documentation and public captures were pushed in [`795b2a7`](https://github.com/altento/tokyo-premium/commit/795b2a7f0e7bbc413c10fbbffa6ec5e5bd41d139); workflow [45191653120](https://github.com/altento/tokyo-premium/actions/runs/45191653120) passed. This commit does not change application code.
- Contact strip order was updated in [`062fc3b`](https://github.com/altento/tokyo-premium/commit/062fc3bf58a290874cd45db682bfd3817457d92c); Pages workflow [45187820484](https://github.com/altento/tokyo-premium/actions/runs/45187820484) succeeded. The public `/contacts.html` route passed the focused browser check at 390 px and 1440 px; captures are in [`visual-qa/contacts-strip-order-2026-11-23/public/`](visual-qa/contacts-strip-order-2026-11-23/public/).

- Earlier application commit [`1329fa7`](https://github.com/altento/tokyo-premium/commit/1329fa7dcb3ed4cd23f9fe15a7ed3e2c0ac63f40) adds latest FreedomPay-attempt visibility to the PHP admin; its isolated PHP/SQLite regression passed locally. The PHP admin itself is not hosted by GitHub Pages.
- Latest visual Pages change: [`749dddc`](https://github.com/altento/tokyo-premium/commit/749dddc13656256e33b7ad68bdeb7f4fcac4c35c), workflow [run 45165291772](https://github.com/altento/tokyo-premium/actions/runs/45165291772). The zone-photo preload regression passed locally and on the published site; desktop/mobile captures are in [`visual-qa/booking-photo-transition-2026-11-23/public/`](visual-qa/booking-photo-transition-2026-11-23/public/).
- Earlier focused checks below remain valid for their respective pages: philosophy workflow [run 45157250560](https://github.com/altento/tokyo-premium/actions/runs/45157250560) and evidence follow-up [run 45157729562](https://github.com/altento/tokyo-premium/actions/runs/45157729562).
- The public philosophy route passed focused Playwright checks at 390 px and 1440 px, including all four red rules, loaded imagery, visible headings and no horizontal overflow. Screenshots and measurements: [`visual-qa/philosophy-compact-2026-11-23-static/public/`](visual-qa/philosophy-compact-2026-11-23-static/public/). This does not replace a full-site visual review.
- The live booking page passed the focused Playwright regression at desktop and mobile widths: semantic field order, all 42 preference markers, zone/photo synchronization, reset, single visible selection status, viewport fit and no browser errors. Screenshots: `visual-qa/booking-form-pairing-2026-11-23/public/`.
- The previously verified homepage curation is still live: cards use IDs `6, 1, 12, 19`; the public 390 px viewport had no horizontal overflow or page errors. Evidence: `visual-qa/home-editorial-curation-2026-11-23/public/`.
- Local home-hosting checks passed with PHP 8.4.25 / SQLite 3.53.4. `Start-Tokyo.ps1 -CheckOnly` loaded PDO SQLite; the allowlist router served `/` and food assets (200) and denied `schema.sql` and the database-file URL (404). This validates local startup prerequisites and route isolation, not a deployed PHP host or Tailscale access from another network.
- Full 11-page visual parity and production PHP hosting remain unverified. Gift previews remain unpaid and non-redeemable; no birthday email/coupon or production loyalty points are issued. SMTP and payment credentials, and a public PHP host, remain external runtime requirements.

## 2026-11-19 — previous verified publication

- Published implementation commit: `fca4f112f52a178f2508c4ab3228e541c29aa069`.
- Workflow for that release: [run 44423394180](https://github.com/altento/tokyo-premium/actions/runs/44423394180) — PHP/SQLite integrity and migration checks, PHP syntax, endpoint smoke, static export, and deployment succeeded. Browser/visual suites were skipped by the fast push profile.
- Published `robots.txt` and `sitemap.xml` returned HTTP 200. The sitemap parsed as XML and lists 35 public routes, excluding the browser-local cart/account and restricted Secret Menu dish routes. Fresh local Pages captures of all 11 reference page types remain paired with their approved PNGs in `project-docs/visual-qa/full-static-2026-11-18/`; the review documents material differences and remaining work. This is not a pixel-perfect comparison or PHP-host verification.
- This verifies the published static demo. PHP-only admin features remain unavailable on Pages; browser storage is not durable/server-synced, and the new payment adapter is not configured. No live payment or table availability is enabled.

The latest verification record is in [testing-report.md](testing-report.md). Full-page static screenshots exist for all 11 page types, with a paired visual review in [the comparison report](visual-qa/full-static-2026-11-18/comparison.md). PHP web hosting, payment, live seating, and outbound restaurant email remain unconfigured.

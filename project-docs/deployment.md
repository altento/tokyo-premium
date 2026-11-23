# Deployment notes
Updated 2026-11-19

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

## Tokyo Premium 2.0 export

The exporter writes `offers.html`, active `collection-{slug}.html` pages, `set-builder.html` and active variant pages, `perfect-evening.html`, and `gift-certificates.html` alongside the original routes. It rewrites PHP-only links, copies local optimized assets, omits admin routes and locked Secret Menu detail routes, removes stale generated dish pages from prior builds, and checks that generated pages contain no PHP-only links. `tokyo-secret-demo.js` contains a public client-only preview list for the static Secret Menu interaction; it is not an access-control boundary. The database is never copied into `docs/`.

## Runtime boundaries
GitHub Pages cannot run PHP. Supported static actions store demo values only in this browser's `localStorage`, clearly marked as estimates or previews. They do not submit an order, booking, review or contact message to the restaurant. PHP hosting is required for SQLite, sessions, user authentication, the admin panel, server-priced orders, campaign rules, the loyalty ledger and online payments. A FreedomPay KZ hosted-checkout adapter has been added but remains disabled until a merchant account, secret key and public HTTPS PHP host are configured. No payment credentials, SMTP provider or live seating service are configured; no secrets belong in the static repository.

## Updating
Push to `main` and wait for the Pages workflow to finish. Inspect the workflow summary, then open the public URL in a fresh browser session and check at least the home, menu, dish, booking, cart, contacts, and profile pages.

## 2026-11-23 — latest verified publication

- Latest application commit: `038413a` (philosophy page visual rhythm); GitHub Pages workflow [run 45155137552](https://github.com/altento/tokyo-premium/actions/runs/45155137552) completed successfully, including build and deployment. A subsequent documentation-only follow-up records the verification evidence.
- The public philosophy route passed focused Playwright checks at 390 px and 1440 px after the release. Screenshots and measurements: [`visual-qa/philosophy-compact-2026-11-23-static/public/`](visual-qa/philosophy-compact-2026-11-23-static/public/). This does not replace a full-site visual review.
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

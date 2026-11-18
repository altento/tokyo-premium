# Deployment notes
Updated 2026-11-19

## Public preview
- Repository: https://github.com/altento/tokyo-premium
- Public URL: https://altento.github.io/tokyo-premium/
- GitHub Actions workflow: `.github/workflows/pages.yml`
- Each push to `main` starts the static export and Pages deployment.

### Local static preview

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

## Latest verified publication

- Published implementation commit: `42428db0a3f41cf213e271627547a507082db6a4`.
- Workflow for that release: [run 44344798353](https://github.com/altento/tokyo-premium/actions/runs/44344798353) — build and deploy succeeded. The full browser/visual suite was skipped by the fast push profile.
- Published `robots.txt` and `sitemap.xml` returned HTTP 200. The sitemap parsed as XML and lists 35 public routes, excluding the browser-local cart/account and restricted Secret Menu dish routes. Fresh local Pages captures of all 11 reference page types remain paired with their approved PNGs in `project-docs/visual-qa/full-static-2026-11-18/`; the review documents material differences and remaining work. This is not a pixel-perfect comparison or PHP-host verification.
- This verifies the published static demo. PHP-only admin features remain unavailable on Pages; browser storage is not durable/server-synced, and no payment or live table availability is enabled.

The latest verification record is in [testing-report.md](testing-report.md). Full-page static screenshots exist for all 11 page types, with a paired visual review in [the comparison report](visual-qa/full-static-2026-11-18/comparison.md). PHP web hosting, payment, live seating, and outbound restaurant email remain unconfigured.

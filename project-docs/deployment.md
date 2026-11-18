# Deployment notes
Updated 2026-11-18

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

The workflow installs PHP CLI + SQLite, starts the existing PHP app, runs `tools/export-pages.php`, uploads `docs/`, and deploys it. The workflow does not package the SQLite database.

## Tokyo Premium 2.0 export

The exporter writes `offers.html`, active `collection-{slug}.html` pages, `set-builder.html` and active variant pages, `perfect-evening.html`, and `gift-certificates.html` alongside the original routes. It rewrites PHP-only links, copies local optimized assets, omits admin routes and locked Secret Menu detail routes, removes stale generated dish pages from prior builds, and checks that generated pages contain no PHP-only links. `tokyo-secret-demo.js` contains a public client-only preview list for the static Secret Menu interaction; it is not an access-control boundary. The database is never copied into `docs/`.

## Runtime boundaries
GitHub Pages cannot run PHP. Supported static actions store demo values only in this browser's `localStorage`, clearly marked as estimates or previews. They do not submit an order, booking, review or contact message to the restaurant. PHP hosting is required for SQLite, sessions, user authentication, the admin panel, server-priced orders, campaign rules and the loyalty ledger. No payment gateway, SMTP provider or live seating system is configured; no secrets belong in the static repository.

## Updating
Push to `main` and wait for the Pages workflow to finish. Inspect the workflow summary, then open the public URL in a fresh browser session and check at least the home, menu, dish, booking, cart, contacts, and profile pages.

## Latest verified publication

- Main commit: `9efab56d5000a62f03c10e6cc55db3328703e42d`
- Workflow: [run 44234776222](https://github.com/altento/tokyo-premium/actions/runs/44234776222) — build and deploy both succeeded. PHP/SQLite checks, the 11-route/21-detail browser regression, Tokyo 2.0 interactions, responsive/visual-fidelity checks, account demo tests, 37-page static export, and screenshot artifact upload passed.
- Cache-busted public checks returned HTTP 200 for `/account.html`; the page includes the updated account dashboard wrapper, loyalty summary, and account panels. This confirms the static demo release, not the unfinished full pixel comparison against the 11 references.
- This verifies the published static demo. PHP-only admin features remain unavailable on Pages; browser storage is not durable/server-synced, and no payment or live table availability is enabled.

The latest verification record is in [testing-report.md](testing-report.md). A new pixel-by-pixel comparison of all 11 source references against current pages remains outstanding; existing screenshots and older gap notes do not constitute that acceptance claim.

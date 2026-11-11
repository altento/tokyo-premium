# Deployment notes
Updated 2026-11-12

## Public preview
- Repository: https://github.com/altento/tokyo-premium
- Public URL: https://altento.github.io/tokyo-premium/
- GitHub Actions workflow: `.github/workflows/pages.yml`
- Each push to `main` starts the static export and Pages deployment.

The workflow installs PHP CLI + SQLite, starts the existing PHP app, runs `tools/export-pages.php`, uploads `docs/`, and deploys it. The workflow does not package the SQLite database.

## Runtime boundaries
GitHub Pages cannot run PHP. All form submissions on the public site are intercepted into this browser's localStorage. This is suitable only for a school demo. PHP local hosting is required for SQLite, sessions, account authentication and the admin panel. No secrets belong in this static repository.

## Updating
Push to `main` and wait for the Pages workflow to finish. Inspect the workflow summary, then open the public URL in a fresh browser session and check at least the home, menu, dish, booking, cart, contacts, and profile pages.

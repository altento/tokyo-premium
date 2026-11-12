# Project instructions
- Preserve the existing PHP 8+ and SQLite architecture; keep the GitHub Pages output static and generated from `tools/export-pages.php`.
- Treat all restaurant details, prices, menu copy, reviews, names, addresses, contacts and guest records as fictional educational data unless explicitly replaced.
- Keep static-demo forms local to this browser; never imply that an order, booking, review or contact message reached the restaurant.
- Keep PHP forms server-validated, CSRF-protected and parameterized. Do not store secrets or live user data in Git.
- Keep navigation parity between PHP and Pages and check mobile layouts, keyboard operation, and `prefers-reduced-motion` for visual changes.
- Update README and `project-docs/` when routes, data behavior, deployment or limitations change.
- Keep Tokyo Premium 2.0 in the existing PHP/SQLite and static-export architecture. PHP is authoritative for prices, promotion limits, loyalty ledger events and account ownership; GitHub Pages scenarios must be labeled and stored as browser-local demonstrations.
- When cart, promotion, profile, booking or image behavior changes, run `test:tokyo20` and the focused existing browser/cart tests, then update the evidence index and report with actual results.
- Workflow: GitHub Actions exports `docs/` and deploys it to Pages after pushes to `main`.

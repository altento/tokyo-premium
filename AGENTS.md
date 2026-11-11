# Project instructions
- Preserve the existing PHP 8+ and SQLite architecture; keep the GitHub Pages output static and generated from `tools/export-pages.php`.
- Treat all restaurant details, prices, menu copy, reviews, names, addresses, contacts and guest records as fictional educational data unless explicitly replaced.
- Keep static-demo forms local to this browser; never imply that an order, booking, review or contact message reached the restaurant.
- Keep PHP forms server-validated, CSRF-protected and parameterized. Do not store secrets or live user data in Git.
- Keep navigation parity between PHP and Pages and check mobile layouts, keyboard operation, and `prefers-reduced-motion` for visual changes.
- Update README and `project-docs/` when routes, data behavior, deployment or limitations change.
- Workflow: GitHub Actions exports `docs/` and deploys it to Pages after pushes to `main`.

# Mobile cart checkout follow-up — 2026-11-16

The optional order comment is now inside a native `<details>` disclosure in both the PHP cart and the GitHub Pages cart, reducing the initial mobile checkout length without hiding required fields. The textarea remains associated with `checkout-form` through its `form` attribute. The narrow cart hero uses less top/bottom padding, and the CSS source and Pages copy are byte-identical.

`php -l pages.php`, `git diff --check`, and a focused structural check of both templates passed. No browser screenshot or full cart regression was run in this accelerated pass; the Playwright browser profile was unavailable earlier in the session. PHP rendering is also unverified because local PHP lacks `pdo_sqlite`.
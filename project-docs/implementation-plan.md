# Implementation record
Updated 2026-11-12

## Existing foundation retained
The site remains a PHP 8+ application backed by SQLite. The shared bootstrap builds navigation, metadata, database access, security headers, sessions and form tokens. GitHub Actions exports a browser-only site from this same app for Pages.

## Completed scope
1. Completed the public route set: home, menu, dish detail, Omakase, philosophy, gallery, booking, reviews, contacts, cart, and account.
2. Linked menu dishes to individual details and connected all pages through shared navigation.
3. Added gallery filters/lightbox, preferred table and zone selection, cart line editing, pickup/delivery choice, and browser-local profile sections.
4. Added a SQLite contact-message inbox and booking seating preferences, including upgrades for existing SQLite files.
5. Improved admin visibility for booking seating selections and contact message status management.
6. Exported PHP routes to standalone Pages documents and rewrote internal links to static HTML destinations.
7. Documented local PHP setup, admin setup, Pages behavior and Module 4 criteria in the README.

## Technical decisions
GitHub Pages cannot execute PHP or SQLite. The public demo intentionally stores submissions and account preferences in the browser only. It has no authentication, email delivery, live availability, or payment flow. The full local PHP app provides database-backed requests and admin functions. All business content is fictional educational material.

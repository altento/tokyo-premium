# QA report
Date: 2026-11-12  
Published revision: `215b84123ed501332191c7a33e60738af6d58454`  
Workflow: [GitHub Actions run 43090875984](https://github.com/altento/tokyo-premium/actions/runs/43090875984)

## Build and backend
- PHP syntax check passed for repository PHP files.
- GitHub Actions started the existing PHP+SQLite app, generated the Pages artifact, and published it successfully.
- Integration smoke checks passed against a temporary SQLite database for booking, order, contact message, and review POSTs.
- The booking smoke request persisted a zone and preferred table; the order used pickup and a database-priced dish.
- Unauthenticated `/admin.php` returned the login page and did not expose the menu administration panel.
- Static export validation confirmed every generated document has a title and has no remaining PHP-only links.

## Browser checks on the public Pages build
- Checked all 11 page types: home, menu, one dish detail, Omakase, philosophy, gallery, booking, reviews, contacts, cart, and account. Each loaded a distinct title and primary heading.
- No browser console errors on the inspected home/interactive pages. On home, 8 images were present and no loaded image had a failed natural size.
- Responsive document width stayed within the viewport at 390, 768, and 1440 CSS pixels; no horizontal page overflow was observed.
- Menu search returned the Ramen item; ascending price sort ordered the cheapest dishes first.
- Cart add, quantity increment, total calculation, removal, and empty-state behavior passed. The test item was removed; cart returned to 0.
- Booking table selection updated the selected state and helper text.
- Gallery category filters changed the visible set; the lightbox opened and closed.
- Mobile navigation expanded with its `aria-expanded` state; profile section buttons switched panels.
- Desktop header spacing was corrected and verified: wordmark, navigation, and booking action occupy separate non-overlapping horizontal bounds at 1440 px.

## Accessibility and performance notes
- Inputs have visible labels; interactive controls expose names and state in the browser accessibility tree.
- Navigation, filter controls, cart buttons, lightbox close control, booking table selection, profile panels, and status regions were inspected during interaction checks.
- The stylesheets include a `prefers-reduced-motion: reduce` override; motion is limited to reveal/hover/lightbox transitions. No autoplay media is used.
- Responsive image `srcset`, lazy loading for non-hero images, and deferred JS are used. A full Lighthouse/Core Web Vitals lab run was not available in this browser session, so no numeric CWV score is claimed.

## Scope limit
GitHub Pages is static. Its forms and account state stay in localStorage in the current browser and do not submit to the PHP backend. The CI POST checks exercise the local PHP+SQLite version. No real restaurant, live availability, payment, email delivery, or personal user record was involved in QA.

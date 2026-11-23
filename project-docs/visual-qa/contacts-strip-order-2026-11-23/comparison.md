# Contacts page — transport strip order

Compared the refreshed static page with `design-references/09-contacts.png`. The reference places transport, parking and access information after the contact form and before the route/visit banner. The current page had these details after that banner, which weakened the reading order.

Moved the status strip to the matching position in the PHP template and static export. Its copy remains honest for the fictional educational project: transit data are unset, parking is unconfirmed, and accessibility needs verification. The next section is still labeled as an illustration and does not claim to provide real directions.

The existing contact visual-fidelity regression passed in static mode at 1440 px and 390 px, including DOM section order, image loading, heading bounds, zero horizontal overflow and no console errors. Full-page captures were inspected and saved as `contacts-static-desktop.jpg` and `contacts-static-mobile.jpg`. This is a focused composition correction; the map art and hero crop still differ from reference 09, and no pixel-parity claim is made. PHP browser rendering and hosted publication have not yet been run for this follow-up.

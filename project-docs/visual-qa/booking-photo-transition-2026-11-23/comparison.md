# Booking zone photo transition — 2026-11-23

The live Pages booking test exposed a blank photo frame when the mobile zone picker changed to Sushi Bar: the browser screenshot was taken before a direct `img.src` replacement had finished loading. The image itself exists on Pages (the cache-busted asset responded HTTP 200), so this was a loading-state flaw rather than a missing file.

The floorplan now preloads the selected zone image before swapping it into the visible element. The previous loaded frame remains visible and gently dims during the transition; a request token prevents a slower old selection from replacing a newer one. The image exposes `aria-busy` while loading, returns to its full opacity when ready, and its transition is disabled under reduced motion. On a failed load the prior image stays in place and the caption explains the fallback.

The focused Pages-mode floorplan regression delayed the Sushi Bar image request by 350 ms, asserted that the previous loaded frame remained visible during that delay, then waited for the correct new image to load before capturing. Existing table preference, reset, zone and mobile picker assertions passed. The full mobile screenshot was inspected. This check covers the static Pages route; local PHP browser rendering remains limited because this Windows PHP CLI has no PDO SQLite module.

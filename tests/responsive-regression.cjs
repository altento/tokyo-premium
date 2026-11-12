const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const evidence = path.join(root, 'project-docs', 'visual-qa');
const widths = [320, 360, 375, 390, 430, 768, 1024, 1440, 1920];
const routes = [
  ['01-home', '/'], ['02-menu', '/menu.php'], ['03-dish', '/pages.php?page=dish&id=3'],
  ['04-omakase', '/pages.php?page=omakase'], ['05-philosophy', '/about.php'],
  ['06-space', '/pages.php?page=gallery'], ['07-booking', '/pages.php?page=booking'],
  ['08-reviews', '/reviews.php'], ['09-contacts', '/pages.php?page=contacts'],
  ['10-cart', '/pages.php?page=cart'], ['11-account', '/account.php']
];
const check = (value, message) => assert.ok(value, message);

async function run(options = {}) {
  const { chromium } = options.chromium ? { chromium: options.chromium } : require('playwright');
  const base = options.base || process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8097';
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, timezoneId: 'Asia/Qyzylorda' });
    const page = await context.newPage();
    page.setDefaultTimeout(7000);
    const navigateReady = async url => {
      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      await page.waitForFunction(() => [...document.styleSheets].some(sheet => sheet.href?.endsWith('/premium.css')));
      await page.evaluate(() => document.fonts.ready);
      return response;
    };

    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      for (const [name, route] of routes) {
        const response = await navigateReady(base + route);
        check(response?.status() === 200, `${name} loads at ${width}px`);
        const geometry = await page.evaluate(() => {
          const selectors = [
            'h1', '.editorial-hero .editorial-sub', '.editorial-hero-copy>p',
            '.editorial-hero .school-note', '.home-hero-lead', '.booking-intro h2'
          ];
          const nodes = [...new Set(selectors.flatMap(selector => [...document.querySelectorAll(selector)]))]
            .filter(node => {
              const style = getComputedStyle(node);
              return style.display !== 'none' && style.visibility !== 'hidden' && node.getClientRects().length;
            });
          const clipped = [];
          for (const node of nodes) {
            const range = document.createRange();
            range.selectNodeContents(node);
            const textRects = [...range.getClientRects()].filter(rect => rect.width || rect.height);
            const self = node.getBoundingClientRect();
            for (const rect of textRects) {
              if (rect.left < -1 || rect.right > innerWidth + 1) clipped.push({selector: node.tagName + (node.className ? '.' + String(node.className).split(' ').join('.') : ''), side:'viewport', text:node.textContent.trim().slice(0,60),left:rect.left,right:rect.right});
              if (rect.left < self.left - 1 || rect.right > self.right + 1 || rect.top < self.top - 1 || rect.bottom > self.bottom + 1) clipped.push({selector:node.tagName + '.self',side:'element',text:node.textContent.trim().slice(0,60),left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,boxLeft:self.left,boxRight:self.right,boxTop:self.top,boxBottom:self.bottom});
              for (let parent=node.parentElement; parent && parent !== document.documentElement; parent=parent.parentElement) {
                const css=getComputedStyle(parent), overflowX=css.overflowX, overflowY=css.overflowY;
                const box=parent.getBoundingClientRect(), left=box.left+parent.clientLeft, right=left+parent.clientWidth, top=box.top+parent.clientTop, bottom=top+parent.clientHeight;
                const clipsX=['hidden','clip','auto','scroll'].includes(overflowX) && (rect.left < left - 1 || rect.right > right + 1);
                const clipsY=['hidden','clip','auto','scroll'].includes(overflowY) && (rect.top < top - 1 || rect.bottom > bottom + 1);
                if (clipsX || clipsY) clipped.push({selector:node.tagName,side:'ancestor',text:node.textContent.trim().slice(0,60),left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,clipLeft:left,clipRight:right,clipTop:top,clipBottom:bottom,overflowX,overflowY});
              }
            }
          }
          const header=document.querySelector('.site-header');
          const cart=header?.querySelector('.header-cart-link');
          const cartRect=cart?.getBoundingClientRect();
          const cartStyle=cart && getComputedStyle(cart);
          const visibleCart=cart && cartStyle.display!=='none' && cartStyle.visibility!=='hidden' && cart.getClientRects().length>0;
          const toggle=header?.querySelector('.mobile-menu');
          const cta=header?.querySelector('.header-cta');
          const overlap=(a,b)=>a&&b&&a.left<b.right-1&&a.right>b.left+1&&a.top<b.bottom-1&&a.bottom>b.top+1;
          return {
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
            clipped,
            h1Count: document.querySelectorAll('h1').length,
            headerPosition: header ? getComputedStyle(header).position : '',
            cartCount:header?.querySelectorAll('.header-cart-link').length||0,
            visibleCart:!!visibleCart,
            cartRect:cartRect&&{left:cartRect.left,right:cartRect.right,top:cartRect.top,bottom:cartRect.bottom,width:cartRect.width,height:cartRect.height},
            cartFixed:cartStyle?.position==='fixed',
            visibleToggles:[...document.querySelectorAll('.cart-toggle')].filter(el=>{const s=getComputedStyle(el);return s.display!=='none'&&s.visibility!=='hidden'&&el.getClientRects().length}).length,
            menuRect:toggle?.getBoundingClientRect().toJSON(),
            ctaRect:cta?.getBoundingClientRect().toJSON(),
            cartOverlapsMenu:overlap(cartRect,toggle?.getBoundingClientRect()),
            cartOverlapsBooking:overlap(cartRect,cta?.getBoundingClientRect()),
            cartMinHitTarget:!!cartRect&&cartRect.width>=44&&cartRect.height>=44
          };
        });
        check(!geometry.overflow, `${name} horizontal overflow at ${width}px`);
        check(geometry.h1Count > 0, `${name} missing h1 at ${width}px`);
        check(geometry.clipped.length === 0, `${name} text clipped at ${width}px: ${JSON.stringify(geometry.clipped.slice(0,3))}`);
        check(geometry.cartCount === 1, `${name} must have one header cart link at ${width}px`);
        check(geometry.visibleCart, `${name} header cart link is hidden at ${width}px`);
        check(!geometry.cartFixed, `${name} cart action must not float over content at ${width}px`);
        check(geometry.visibleToggles === 0, `${name} duplicate floating cart control at ${width}px`);
        check(geometry.cartMinHitTarget, `${name} cart tap target is under 44px at ${width}px`);
        check(!geometry.cartOverlapsMenu, `${name} cart overlaps mobile menu control at ${width}px`);
        check(!geometry.cartOverlapsBooking, `${name} cart overlaps booking CTA at ${width}px`);
        if (name === '07-booking') {
          const bookingHeading = await page.locator('.editorial-hero h1').evaluate(node => {
            const range = document.createRange();
            range.selectNodeContents(node);
            return { lines: range.getClientRects().length, hyphens: getComputedStyle(node).hyphens };
          });
          check(bookingHeading.lines === 1, `booking title stays on one line at ${width}px (${bookingHeading.lines} lines)`);
          check(bookingHeading.hyphens === 'none', `booking title does not split a word with a hyphen at ${width}px`);
        }
        if (name === '03-dish' && width >= 900) {
          const gallery = await page.locator('.detail-visual').boundingBox();
          check(gallery && gallery.width >= width * .35, `dish hero image keeps editorial desktop width at ${width}px (got ${gallery?.width || 0})`);
        }
        if (width <= 900) {
          const menu=page.locator('.mobile-menu');
          await menu.focus(); await page.keyboard.press('Enter');
          check(await menu.getAttribute('aria-expanded')==='true', `${name} menu opens at ${width}px`);
          check(await page.locator('.main-nav.open .mobile-nav-utilities a[href*="cart"]').count()===0, `${name} duplicate mobile cart item at ${width}px`);
          check(await page.locator('.header-cart-link:visible').count()===1, `${name} cart stays available while menu is open at ${width}px`);
          check(await page.locator('.cart-toggle:visible').count()===0, `${name} floating order control stays absent while menu is open at ${width}px`);
          if (name==='07-booking' && width===390) await page.screenshot({path:path.join(evidence,'07-mobile-menu-open-390.jpg'),type:'jpeg',quality:80,fullPage:false});
          await page.keyboard.press('Escape');
          check(await menu.getAttribute('aria-expanded')==='false', `${name} menu closes with Escape at ${width}px`);
        }
        if (name==='07-booking' && [320,390].includes(width)) {
          await page.screenshot({path:path.join(evidence,`07-booking-heading-${width}.jpg`),type:'jpeg',quality:80,fullPage:false});
        }
      }

      await navigateReady(base+'/');
      await page.evaluate(()=>window.scrollTo(0,Math.max(0,document.documentElement.scrollHeight-innerHeight-20)));
      const sticky=await page.evaluate(()=>({top:document.querySelector('.site-header').getBoundingClientRect().top,cart:document.querySelector('.header-cart-link').getBoundingClientRect().top}));
      check(sticky.top >= -1 && sticky.top <= 1, `sticky header leaves viewport at ${width}px (${sticky.top})`);
      check(sticky.cart >= -1 && sticky.cart < 80, `header cart is unavailable after scroll at ${width}px (${sticky.cart})`);
    }

    for (const fixed of ['2027-01-31T20:00:00.000Z','2027-03-01T19:00:00.000Z']) {
      // Deliberately use a browser zone whose calendar day differs from the restaurant
      // zone at this instant. The date bounds must follow Asia/Qyzylorda, not Date's
      // browser-local getters (which previously overrode the shared helper).
      const futureContext=await browser.newContext({viewport:{width:390,height:844},timezoneId:'UTC'});
          await futureContext.addInitScript(`(()=>{const at=${Date.parse(fixed)};const NativeDate=Date;class FrozenDate extends NativeDate{constructor(...args){super(...(args.length?args:[at]))}static now(){return at}}window.Date=FrozenDate})()`);
      const futurePage=await futureContext.newPage();
      const expectedMin=fixed.startsWith('2027-01')?'2027-02-01':'2027-03-02';
      const expectedMax=fixed.startsWith('2027-01')?'2028-02-01':'2028-03-01';
      for (const route of ['/', '/pages.php?page=booking', '/docs/index.html', '/docs/booking.html']) {
        await futurePage.goto(base+route,{waitUntil:'domcontentloaded'});
        await futurePage.waitForFunction(() => [...document.styleSheets].some(sheet => sheet.href?.endsWith('/premium.css')));
        await futurePage.evaluate(() => document.fonts.ready);
        for (const input of await futurePage.locator('.home-booking-form input[type="date"], .booking-form input[type="date"]').all()) {
          check(await input.getAttribute('min')===expectedMin, `booking min date on ${route} at ${fixed}`);
          check(await input.getAttribute('max')===expectedMax, `booking max date on ${route} at ${fixed}`);
          check(await input.getAttribute('data-restaurant-time-zone')==='Asia/Qyzylorda', `restaurant timezone attached on ${route}`);
          await input.fill(expectedMin);
          check(await input.evaluate(el=>el.validity.valid), `today date accepted on ${route}`);
          const before=await input.evaluate(el=>{const d=new Date(el.min+'T00:00:00Z');d.setUTCDate(d.getUTCDate()-1);return d.toISOString().slice(0,10)});
          await input.fill(before);
          check(await input.evaluate(el=>el.validity.rangeUnderflow), `yesterday rejected on ${route}`);
          if (fixed.startsWith('2027-03')) {
            await input.fill('2028-02-29');
            check(await input.evaluate(el=>el.validity.valid), `leap day accepted on ${route}`);
          }
        }
      }
      await futureContext.close();
    }
    for (const file of ['index.html', 'booking.html']) {
      const html = fs.readFileSync(path.join(root, 'docs', file), 'utf8');
      const dateForm = html.match(/<form\b[^>]*\bclass="[^"]*\b(?:home-booking-form|booking-form)\b[^"]*"[^>]*>[\s\S]*?<\/form>/)?.[0] || '';
      check(dateForm.length > 0, `static ${file} booking form exists`);
      check(!/\s(?:min|max)="\d{4}-\d{2}-\d{2}"/.test(dateForm), `static ${file} does not ship stale export-time date bounds`);
    }
    await page.setViewportSize({width:320,height:420});
    await navigateReady(base+'/');
    const shortMenu=page.locator('.mobile-menu');
    await shortMenu.focus(); await page.keyboard.press('Enter');
    const menuScroll=page.locator('#site-navigation');
    check(await menuScroll.evaluate(el=>el.scrollHeight>el.clientHeight), 'mobile navigation becomes scrollable on a short screen');
    check(await page.evaluate(()=>getComputedStyle(document.body).overflow==='hidden'), 'page scrolling is locked while mobile navigation is open');
    await page.locator('#site-navigation .mobile-nav-utilities a[href*="account"]').scrollIntoViewIfNeeded();
    check(await menuScroll.evaluate(el=>el.scrollTop>0), 'mobile navigation scrolls internally to the last utility link');
    check(await page.locator('#site-navigation .mobile-nav-utilities a[href*="account"]').isVisible(), 'last utility link remains reachable in a short mobile menu');
    check(await page.evaluate(()=>window.scrollY===0), 'mobile menu scroll does not move the page behind it');
    await page.screenshot({path:path.join(evidence,'07-mobile-menu-scroll-320.jpg'),type:'jpeg',quality:80,fullPage:false});
    await page.keyboard.press('Escape');
    check(await shortMenu.getAttribute('aria-expanded')==='false', 'mobile navigation closes after scrolling');
    await page.setViewportSize({width:390,height:844});
    await navigateReady(base+'/');
    await page.screenshot({path:path.join(evidence,'07-mobile-header-cart-390.jpg'),type:'jpeg',quality:80,fullPage:false});
    await page.locator('.header-cart-link').click();
    check(new URL(page.url()).searchParams.get('page')==='cart', 'header cart link opens the full cart page');
    console.log(`PASS responsive regression: ${routes.length} page types × ${widths.length} widths; heading clipping, cart access, sticky header, keyboard menu and restaurant-timezone booking dates across browser time zones.`);
  } finally { await browser.close(); }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode=1; });

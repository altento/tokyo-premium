const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8139/docs';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/notifications-2026-11-19');
(async()=>{
  fs.mkdirSync(evidence,{recursive:true});
  const browser=await chromium.launch();
  try{
    const page=await browser.newPage({viewport:{width:1440,height:960}});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(base+'/account.html');
    await page.evaluate(()=>{
      localStorage.clear();localStorage.setItem('tokyo-demo-account-seed-dismissed','1');
      localStorage.setItem('tokyo-demo-bookings',JSON.stringify([{name:'Legacy guest',date:'2030-01-10',time:'19:00',zone:'Основной зал',guests:2,demo:true}]));
      localStorage.setItem('tokyo-demo-read-notifications',JSON.stringify(['booking:2030-01-10|19:00|Legacy guest|Основной зал|0']));
    });
    await page.reload();await page.locator('[data-account-tab="notifications"]').click();
    assert.equal(await page.locator('#demo-notifications .is-read').count(),1,'legacy read receipt migrates');
    const stableId=await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-bookings'))[0].demoId);
    assert.ok(stableId);
    await page.evaluate(()=>{
      const rows=JSON.parse(localStorage.getItem('tokyo-demo-bookings'));
      rows.unshift({name:'New guest',date:'2030-01-11',time:'18:00',zone:'Основной зал',guests:2,demo:true,createdAt:new Date().toISOString()});
      localStorage.setItem('tokyo-demo-bookings',JSON.stringify(rows));
    });
    await page.reload();await page.locator('[data-account-tab="notifications"]').click();
    assert.equal(await page.locator('#demo-notifications .is-read').count(),1,'prepending a booking preserves old read state');
    assert.equal(await page.locator('#demo-notifications .is-unread').count(),1);
    assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-bookings'))[1].demoId),stableId);
    await page.locator('[data-demo-notification-read]').click();
    await page.evaluate(()=>{const rows=JSON.parse(localStorage.getItem('tokyo-demo-bookings'));rows.reverse();localStorage.setItem('tokyo-demo-bookings',JSON.stringify(rows))});
    await page.reload();await page.locator('[data-account-tab="notifications"]').click();
    assert.equal(await page.locator('#demo-notifications .is-read').count(),2,'reordering cannot restore unread state');
    for(const width of [1440,390]){
      await page.setViewportSize({width,height:960});
      await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});
      await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));
      await page.screenshot({path:path.join(evidence,`notifications-${width}.jpg`),fullPage:true,quality:78});
      await style.evaluate(e=>e.remove());
    }
    await page.evaluate(()=>localStorage.setItem('tokyo-demo-read-notifications','{"invalid":true}'));
    await page.reload();await page.locator('[data-account-tab="notifications"]').click();
    await page.locator('[data-demo-notification-read]').first().click();
    assert.equal(await page.locator('#demo-notifications .is-read').count(),1,'malformed read-state shape does not break controls');
    await page.evaluate(()=>{localStorage.setItem('tokyo-demo-orders','{}');localStorage.setItem('tokyo-demo-bookings','null')});
    await page.reload();assert.equal(await page.locator('#demo-notifications .account-notification-card').count(),0);
    await page.goto(base+'/booking.html');
    await page.locator('#full-guest-name').fill('Local QA guest');
    await page.locator('#full-guest-phone').fill('+77010000000');
    await page.locator('#full-guest-email').fill('qa@example.test');
    await page.locator('#full-guest-date').fill(await page.locator('#full-guest-date').evaluate(input=>{const day=new Date(input.min+'T00:00:00Z');day.setUTCDate(day.getUTCDate()+1);return day.toISOString().slice(0,10)}));
    await page.locator('#full-booking-form').evaluate(form=>form.requestSubmit());
    await page.waitForFunction(()=>JSON.parse(localStorage.getItem('tokyo-demo-bookings')||'[]')[0]?.demoId);
    assert.ok(await page.evaluate(()=>Number.isFinite(Date.parse(JSON.parse(localStorage.getItem('tokyo-demo-bookings'))[0].createdAt))),'new local bookings have creation metadata before account visit');
    assert.deepEqual(errors,[]);
    console.log('PASS: durable notification IDs, legacy receipts, prepend/reorder/reload, malformed storage, local booking creation and mobile account');
  }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});

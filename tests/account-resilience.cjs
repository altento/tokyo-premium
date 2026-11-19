const { chromium } = require('playwright');
const assert = require('node:assert/strict'),fs = require('node:fs'),path = require('node:path');
const base = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8167/docs';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.resolve('project-docs/visual-qa/account-resilience-2026-11-19');
const pause = page => page.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
async function capture(page,name){await page.evaluate(async()=>{await Promise.all([...document.images].filter(i=>i.getAttribute('src')).map(i=>{i.loading='eager';return i.decode()}));await document.fonts.ready});const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));await page.screenshot({path:path.join(evidence,name),fullPage:true,quality:76});await style.evaluate(e=>e.remove())}
(async()=>{
 fs.mkdirSync(evidence,{recursive:true});const browser=await chromium.launch();
 try{
  const context=await browser.newContext({viewport:{width:1440,height:960},reducedMotion:'reduce'}),page=await context.newPage();const errors=[];context.on('page',p=>p.on('pageerror',e=>errors.push(e.message)));page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/account.html');
  const tab=async key=>{await page.locator(`[data-account-tab="${key}"]`).click();assert.equal(await page.locator('[data-account-panel]:visible').count(),1,`only ${key} panel is visible`)};
  const profile=page.locator('[data-local-form="profile"]'),address=page.locator('[data-local-form="address"]');
  await tab('profile');await page.locator('#demo-name').fill('Несохранённый черновик');
  await page.evaluate(()=>document.dispatchEvent(new Event('tokyo-favorites-updated')));
  assert.equal(await page.locator('#demo-name').inputValue(),'Несохранённый черновик');
  const peer=await context.newPage();await peer.goto(base+'/account.html');await peer.locator('[data-account-tab="profile"]').click();await peer.locator('#demo-name').fill('Профиль другой вкладки');await peer.locator('[data-local-form="profile"] button').click();
  await profile.locator('.form-status.error').waitFor();assert.equal(await page.locator('#demo-name').inputValue(),'Несохранённый черновик');
  assert.equal(await page.locator('#demo-profile-contact-name').innerText(),'Профиль другой вкладки');
  await capture(page,'profile-unsaved-desktop.jpg');
  await profile.locator('button').click();assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-profile')).name),'Несохранённый черновик');
  await peer.waitForFunction(()=>document.querySelector('#demo-name').value==='Несохранённый черновик');

  await page.locator('#demo-name').fill('Не потерять при ошибке');
  await page.evaluate(()=>{window.qaSet=Storage.prototype.setItem;Storage.prototype.setItem=function(k,v){if(k==='tokyo-demo-profile')throw new DOMException('QA quota','QuotaExceededError');return window.qaSet.call(this,k,v)}});
  await profile.locator('button').click();assert.match(await profile.locator('.form-status.error').innerText(),/Не удалось сохранить/);assert.equal(await page.locator('#demo-name').inputValue(),'Не потерять при ошибке');
  await page.evaluate(()=>{Storage.prototype.setItem=window.qaSet});
  await tab('notifications');const unread=await page.locator('.account-notification-card.is-unread').count();
  await page.evaluate(()=>{Storage.prototype.setItem=function(k,v){if(k==='tokyo-demo-read-notifications')throw new DOMException('QA quota','QuotaExceededError');return window.qaSet.call(this,k,v)}});
  await page.locator('[data-demo-notification-read]').first().click();assert.match(await page.locator('[data-notification-error]').innerText(),/Не удалось сохранить/);assert.equal(await page.locator('.account-notification-card.is-unread').count(),unread);
  await page.evaluate(()=>{Storage.prototype.setItem=window.qaSet});
  await tab('addresses');const original=await page.evaluate(()=>localStorage.getItem('tokyo-demo-addresses'));
  await page.locator('#demo-address').fill('     ');await address.locator('button').click();assert.match(await address.locator('.form-status.error').innerText(),/от 5 до 180/);assert.equal(await page.evaluate(()=>localStorage.getItem('tokyo-demo-addresses')),original);
  await page.locator('#demo-address').fill('Учебный адрес, дом 10');await address.locator('button').click();assert.equal(await page.locator('#demo-address').inputValue(),'');
  await page.locator('#demo-address').fill('Учебный адрес, дом 10');await address.locator('button').click();assert.match(await address.locator('.form-status.error').innerText(),/уже сохранён/);
  await page.evaluate(()=>{localStorage.setItem('tokyo-demo-addresses',JSON.stringify(Array.from({length:10},(_,i)=>'Учебный адрес номер '+i)));document.dispatchEvent(new Event('tokyo-demo-data-updated'))});
  await page.locator('#demo-address').fill('Одиннадцатый учебный адрес');await address.locator('button').click();assert.match(await address.locator('.form-status.error').innerText(),/не более 10/);assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-addresses')).length),10);
  await page.evaluate(()=>{localStorage.setItem('tokyo-demo-addresses',JSON.stringify(['Новый адрес другой вкладки',...JSON.parse(localStorage.getItem('tokyo-demo-addresses'))]))});
  await page.locator('[data-address-remove="0"]').click();
  const afterDelete=await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-addresses')));assert.equal(afterDelete[0],'Новый адрес другой вкладки');assert.equal(afterDelete.includes('Учебный адрес номер 0'),false);
  await peer.waitForFunction(()=>document.querySelectorAll('#demo-addresses article').length===10);
  await page.setViewportSize({width:390,height:844});await pause(page);await capture(page,'addresses-mobile.jpg');
  for(const raw of ['null','[]','{"name":{},"phone":[]}','{broken']){
   await page.evaluate(raw=>localStorage.setItem('tokyo-demo-profile',raw),raw);await page.reload();await tab('profile');assert.equal(await page.locator('#demo-name').inputValue(),'');
  }
  await page.locator('#demo-name').fill('Последний черновик');await tab('settings');
  await page.evaluate(()=>{window.qaRemove=Storage.prototype.removeItem;Storage.prototype.removeItem=function(k){if(k==='tokyo-demo-profile')throw new DOMException('QA denied','SecurityError');return window.qaRemove.call(this,k)}});
  await page.locator('#demo-clear-data').click();assert.match(await page.locator('#demo-settings-status').innerText(),/Не все данные/);
  await page.evaluate(()=>{Storage.prototype.removeItem=window.qaRemove});await page.locator('#demo-clear-data').click();assert.match(await page.locator('#demo-settings-status').innerText(),/очищены/);await tab('profile');assert.equal(await page.locator('#demo-name').inputValue(),'');
  for(const width of [320,390,768,1024,1440,1920]){await page.setViewportSize({width,height:960});await pause(page);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`account width ${width}`)}
  assert.deepEqual(errors,[]);const report={status:'passed',checks:['dirty profile survives favorite refresh','real cross-tab profile sync/conflict warning','quota failure preserves input','empty and duplicate addresses rejected','capacity preserves oldest address','stale address row removes intended value','malformed profile does not crash','failed clear disclosed; successful clear resets draft'],widths:[320,390,768,1024,1440,1920],pageErrors:errors};fs.writeFileSync(path.join(evidence,'browser.json'),JSON.stringify(report,null,2)+'\n');console.log('PASS: account drafts, cross-tab sync, malformed storage, write failures, address validation/capacity and six widths.');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});

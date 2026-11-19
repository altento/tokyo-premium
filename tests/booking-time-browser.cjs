const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const evidence=process.env.TOKYO_EVIDENCE_DIR||path.resolve('project-docs/visual-qa/booking-time-2026-11-19');
const modes=[{name:'php',base:process.env.TOKYO_BASE_URL||'http://127.0.0.1:8170',routes:['/','/pages.php?page=booking']},{name:'static',base:process.env.TOKYO_STATIC_BASE_URL||'http://127.0.0.1:8171/docs',routes:['/index.html','/booking.html']}];
const valid=field=>field.evaluate(e=>e.checkValidity());
async function capture(page,name){await page.evaluate(async()=>{await Promise.all([...document.images].filter(i=>i.getAttribute('src')).map(i=>{i.loading='eager';return i.decode()}));await document.fonts.ready});const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));await page.screenshot({path:path.join(evidence,name),fullPage:true,quality:75});await style.evaluate(e=>e.remove())}
(async()=>{fs.mkdirSync(evidence,{recursive:true});const browser=await chromium.launch();const errors=[],report=[];try{
 for(const mode of modes)for(const route of mode.routes){
  const page=await browser.newPage({reducedMotion:'reduce',timezoneId:'America/Los_Angeles',viewport:{width:1440,height:960}});page.on('pageerror',e=>errors.push(e.message));
  await page.clock.setFixedTime(new Date('2027-01-19T10:00:00Z'));await page.goto(mode.base+route);
  const form=page.locator('form[data-api="booking"]'),date=form.locator('[name="date"]'),time=form.locator('[name="time"]');
  assert.equal(await date.getAttribute('min'),'2027-01-19');
  await date.fill('2027-01-18');assert.equal(await valid(date),false);
  await date.fill('2027-01-20');assert.equal(await valid(date),true,'corrected future date clears the old custom validity');
  await time.selectOption('12:00');assert.equal(await valid(time),true,'tomorrow morning is allowed');
  await date.fill('2027-01-19');assert.equal(await time.locator('option[value="12:00"]').isDisabled(),true);assert.equal(await valid(time),false);
  await time.selectOption('17:00');assert.equal(await valid(time),true);
  await page.clock.setFixedTime(new Date('2027-01-20T10:00:00Z'));await page.evaluate(()=>window.dispatchEvent(new Event('pageshow')));assert.equal(await valid(date),false);
  await date.fill('2027-01-21');assert.equal(await valid(date),true,'a tab crossing midnight recovers after selecting a future date');
  await page.clock.setFixedTime(new Date('2028-02-28T20:15:00Z'));await page.evaluate(()=>window.TokyoPremiumBookingDates.update());assert.equal(await date.getAttribute('min'),'2028-02-29');assert.equal(await date.getAttribute('max'),'2029-02-28');
  await date.fill('2028-02-29');await time.selectOption('12:00');assert.equal(await valid(time),true);
  await page.clock.setFixedTime(new Date('2027-01-19T17:10:00Z'));await date.fill('2027-01-19');assert.equal(await valid(time),false);assert.match(await form.locator('[data-booking-time-hint]').innerText(),/другую дату/);
  if(mode.name==='static'&&route.includes('booking')){
   await capture(page,'no-future-times-desktop.jpg');
   for(const width of [320,390,768,1024,1440,1920]){await page.setViewportSize({width,height:900});await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`booking ${width}`);assert.ok(await form.locator('[data-booking-time-hint]').evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1}))}
   await page.setViewportSize({width:390,height:844});await page.reload();await date.fill('2027-01-19');await capture(page,'no-future-times-mobile.jpg');
  }
  await date.fill('2027-01-20');await time.selectOption('12:00');assert.equal(await valid(time),true);
  await form.evaluate(f=>f.reset());await page.evaluate(()=>new Promise(r=>queueMicrotask(r)));assert.equal(await time.evaluate(e=>e.validity.customError),false,'reset clears dependent time validation');
  if(mode.name==='static'){
   await page.clock.setFixedTime(new Date('2027-01-19T10:00:00Z'));await date.fill('2027-01-19');await time.selectOption('17:00');
   if(route.includes('index')){await form.locator('button[type="submit"]').click();assert.equal(await form.locator('[data-home-booking-contact-fields]').isVisible(),true)}
   for(const[name,value]of [['name','Учебный гость'],['phone','+77010000000'],['email','qa@example.test']])await form.locator(`[name="${name}"]`).fill(value);
   await page.clock.setFixedTime(new Date('2027-01-19T12:00:00Z'));
   await form.evaluate(f=>f.requestSubmit());assert.equal(await valid(time),false,'implicit submit rechecks a slot that elapsed while editing');assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-bookings')||'[]').length),0);
   await time.selectOption('18:00');await form.evaluate(f=>f.requestSubmit());
   await form.locator('.form-status.success').waitFor({timeout:7000});
   const saved=await page.evaluate(()=>JSON.parse(localStorage.getItem('tokyo-demo-bookings'))[0]);assert.equal(saved.time,'18:00');assert.equal(saved.date,'2027-01-19');assert.match(await form.locator('.form-status').innerText(),/не подтверждена/);
  }
  report.push({mode:mode.name,route,result:'passed'});await page.close();
 }
 const fallback=await browser.newPage({reducedMotion:'reduce',viewport:{width:390,height:844}});fallback.on('pageerror',e=>errors.push(e.message));
 await fallback.route('**/assets/seat-plan.svg',route=>route.fulfill({status:503,body:'Temporary fixture failure'}));await fallback.goto(modes[1].base+'/booking.html');await fallback.locator('.tp-floorplan-fallback').waitFor();
 assert.equal(await fallback.locator('.tp-floorplan-fallback [data-tp-table]').count(),0,'failed map does not invent an obsolete table layout');
 await fallback.locator('.tp-floorplan-fallback [data-tp-zone-select="Летняя терраса"]').click();assert.equal(await fallback.locator('#booking-zone').inputValue(),'Летняя терраса');assert.equal(await fallback.locator('[name="table"]').inputValue(),'');assert.equal(await fallback.locator('[data-tp-zone-photo]').isHidden(),true);
 assert.match(await fallback.locator('[data-tp-floorplan-selection]').innerText(),/зона выбрана/);await capture(fallback,'map-unavailable-mobile.jpg');await fallback.close();
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(evidence,'browser.json'),JSON.stringify({status:'passed',routes:report,checks:['corrected dates','local midnight rollover','leap day and 365-day limit','restaurant vs browser zone','past and fully elapsed hours','submit-time revalidation','reset and valid demo save','unavailable map uses zone-only fallback'],widths:[320,390,768,1024,1440,1920],pageErrors:errors},null,2)+'\n');console.log('PASS: PHP/static home and booking date/time boundaries, rollover, leap day, submit, reset, honest map fallback and six widths.');
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exitCode=1});

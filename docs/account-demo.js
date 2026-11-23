if(document.querySelector('.account-demo-page')&&!document.querySelector('link[data-account-demo-styles],link[href$="account-demo.css"]')){
 const accountStyles=document.createElement('link');
 accountStyles.rel='stylesheet';
 accountStyles.href=new URL('account-demo.css',document.baseURI).href;
 accountStyles.dataset.accountDemoStyles='';
 document.head.append(accountStyles);
}
document.addEventListener('DOMContentLoaded',()=>{
 const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const read=key=>{try{const value=JSON.parse(localStorage.getItem(key)||'[]');return Array.isArray(value)?value:[]}catch{return[]}};
 const readRows=key=>read(key).filter(row=>row&&typeof row==='object'&&!Array.isArray(row));
 const writeStatus=(form,msg,error=false)=>{const s=$('.form-status',form);if(s){s.textContent=msg;s.className='form-status '+(error?'error':'success')}};
 const readAddresses=()=>read('tokyo-demo-addresses').filter(value=>typeof value==='string'&&value.trim()).map(value=>value.trim());
 const monthDays=[31,29,31,30,31,30,31,31,30,31,30,31];
 const validBirthday=(month,day)=>{const m=Number(month),d=Number(day);return Number.isInteger(m)&&m>=1&&m<=12&&Number.isInteger(d)&&d>=1&&d<=monthDays[m-1]};
 const syncBirthdayDays=(form,monthValue,dayValue='')=>{const select=form?.elements?.birth_day;if(!select)return;const max=monthDays[Number(monthValue)-1]||0;select.replaceChildren(new Option('Выберите день',''));for(let day=1;day<=max;day++)select.add(new Option(String(day),String(day)));select.disabled=max===0;const wanted=Number(dayValue||'');select.value=Number.isInteger(wanted)&&wanted>=1&&wanted<=max?String(wanted):''};
 const readProfile=()=>{try{const value=JSON.parse(localStorage.getItem('tokyo-demo-profile')||'{}');if(!value||typeof value!=='object'||Array.isArray(value))return{};const month=String(value.birthMonth??''),day=String(value.birthDay??''),hasBirthday=validBirthday(month,day);return{name:typeof value.name==='string'?value.name:'',phone:typeof value.phone==='string'?value.phone:'',birthMonth:hasBirthday?String(Number(month)):'',birthDay:hasBirthday?String(Number(day)):'',birthdayConsent:hasBirthday&&value.birthdayConsent===true}}catch{return{}}};
 let profileDraftDirty=false,profileHydrated=false;
 $('[data-local-form="profile"]')?.addEventListener('input',event=>{profileDraftDirty=true;const status=$('.form-status',event.currentTarget);if(status){status.textContent='Есть несохранённые изменения. Нажмите «Сохранить профиль».';status.className='form-status'}});
 $('[data-local-form="profile"]')?.elements?.birth_month?.addEventListener('change',event=>syncBirthdayDays(event.currentTarget.form,event.currentTarget.value,event.currentTarget.form.elements.birth_day.value));
 const staticMode=Boolean($('.account-demo-page'));
 const menuHref=staticMode?'menu.html':'menu.php';
 const bookingHref=staticMode?'booking.html':'pages.php?page=booking';
 const notificationRows=(key,kind,readIds)=>{
  const rows=readRows(key);let changed=false;
  const bookingKey=row=>[row.date,row.time,row.name,row.zone].join('|');
  rows.forEach((row,index)=>{
   if(row.demoId||(kind==='order'&&(row.orderId||row.createdAt)))return;
   const prefix=kind==='booking'?'booking:'+bookingKey(row)+'|':null;
   const oldId=kind==='booking'?prefix+index:'order:'+index;
   const uniqueLegacyBooking=prefix&&rows.filter(other=>bookingKey(other)===bookingKey(row)).length===1;
   const wasRead=readIds.has(oldId)||(uniqueLegacyBooking&&[...readIds].some(id=>id.startsWith(prefix)&&/^\d+$/.test(id.slice(prefix.length))));
   row.demoId=window.crypto?.randomUUID?.()||'demo-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2);
   if(wasRead)readIds.add(kind+':'+row.demoId);
   changed=true;
  });
  if(changed){try{localStorage.setItem(key,JSON.stringify(rows));localStorage.setItem('tokyo-demo-read-notifications',JSON.stringify([...readIds]))}catch{/* Reading history still works if browser storage is unavailable. */}}
  return rows;
 };
 const demoNotifications=()=>{
  const readState=read('tokyo-demo-read-notifications');
  const readIds=new Set(Array.isArray(readState)?readState.filter(value=>typeof value==='string'):[]);
  const orders=notificationRows('tokyo-demo-orders','order',readIds).map(row=>({id:'order:'+String(row.demoId||row.orderId||row.createdAt),kind:'order',created:Date.parse(row.createdAt)||0,title:'Демо-заказ сохранён',message:'Заказ '+String(row.orderId||'из истории')+' сохранён только в этом браузере. Он не оплачен и не отправлен ресторану.',date:String(row.date||'')}));
  const bookings=notificationRows('tokyo-demo-bookings','booking',readIds).map(row=>({id:'booking:'+String(row.demoId),kind:'booking',created:Date.parse(row.createdAt)||0,title:'Демо-запрос на бронь сохранён',message:'Запрос существует только в этом браузере; ресторан его не получил, стол не подтверждён.',date:[row.date,row.time].filter(Boolean).join(' · ')}));
  return [...orders,...bookings].map(row=>({...row,read:readIds.has(row.id)})).sort((a,b)=>b.created-a.created);
 };
 function render(){
  const orders=$('#demo-orders'),bookings=$('#demo-bookings');
  if(orders){
   const rows=readRows('tokyo-demo-orders');
   const dishes=window.DISHES||[];
   orders.innerHTML=rows.length?rows.map(row=>{
    const items=Array.isArray(row.items)?row.items.filter(item=>item&&typeof item==='object').slice(0,30):[];
    const customSets=Array.isArray(row.customSets)?row.customSets.filter(set=>set&&typeof set==='object').slice(0,3):[];
    const dishLines=items.map(item=>'<li>'+esc(item.name)+' × '+Number(item.qty||0)+' · '+Number(item.unitPrice||0).toLocaleString('ru-RU')+' ₸ = '+Number(item.lineTotal||0).toLocaleString('ru-RU')+' ₸</li>').join('');
    const setLines=customSets.map(set=>{
     const components=(Array.isArray(set.items)?set.items:[]).map(item=>{const dish=dishes.find(value=>Number(value.id)===Number(item.id));return esc(dish?.name||item.name||'Блюдо')+' × '+Number(item.qty||0)}).join(' · ');
     return '<li><strong>'+esc(set.name||('Сет '+(set.variant||set.setType||'')))+' × '+Number(set.qty||1)+'</strong> · '+Number(set.unitPrice||0).toLocaleString('ru-RU')+' ₸ / сет · '+Number(set.lineTotal||0).toLocaleString('ru-RU')+' ₸ · ориентировочная цена'+(components?'<br><small>'+components+'</small>':'')+'</li>';
    }).join('');
    const allLines=dishLines+setLines;
    const repeatSets=customSets.map(set=>({variant:set.variant||set.setType||'signature',setType:set.variant||set.setType||'signature',name:set.name||'Авторский сет',qty:Number(set.qty||1),items:Array.isArray(set.items)?set.items.map(item=>({id:Number(item.id),qty:Number(item.qty)})):[]}));
    const repeatData=JSON.stringify({items,customSets:repeatSets});
    const lineCount=items.length+customSets.length;
    const demoBadge=row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'<span class="account-demo-local-badge">Только этот браузер</span>';
    const firstDishId=Number(items.find(item=>Number.isSafeInteger(Number(item.id))&&Number(item.id)>0)?.id||customSets.flatMap(set=>Array.isArray(set.items)?set.items:[]).find(item=>Number.isSafeInteger(Number(item.id))&&Number(item.id)>0)?.id);
    const previewDish=dishes.find(dish=>Number(dish.id)===firstDishId&&typeof dish.image_url==='string'&&dish.image_url.length>0);
    const preview=previewDish?'<img class="account-order-preview" src="'+esc(previewDish.image_url)+'" alt="'+esc(previewDish.name)+'" loading="lazy" decoding="async">':'';
    const amount=Number(row.subtotal??row.total??0);
    const amountLabel=(Number.isFinite(amount)&&amount>=0?amount:0).toLocaleString('ru-RU')+' ₸';
    return '<article class="demo-order account-demo-order">'+preview+'<div class="demo-order-content"><div class="account-demo-order-head"><div class="account-demo-order-title">'+demoBadge+'<small>'+esc(row.orderId||'Демо-заказ')+'</small><strong>'+esc(row.date)+'</strong></div><div class="account-demo-order-total"><span>Итого · демо</span><strong>'+amountLabel+'</strong></div></div><p class="account-demo-order-meta">'+esc(row.name||'Гость')+' · '+Number(row.count||0)+' позиций · '+esc(row.fulfillment==='pickup'?'Самовывоз':'Демо-доставка')+'</p>'+(allLines?'<details class="demo-order-details"><summary>Состав заказа · '+lineCount+' позиций</summary><ul class="demo-order-items">'+allLines+'</ul></details>':'<p class="menu-note">Состав старого заказа недоступен.</p>')+(lineCount?'<button class="button secondary account-demo-repeat" type="button" data-repeat-order="'+esc(repeatData)+'">Собрать заказ снова</button>':'')+'</div></article>';
   }).join(''):'<div class="account-empty-state"><p>Здесь появятся локальные учебные заказы после демонстрационного оформления. Сейчас история пуста.</p><a class="button secondary" href="'+menuHref+'">Открыть меню ↗</a></div>'
  }
  if(bookings){const rows=readRows('tokyo-demo-bookings');bookings.innerHTML=rows.length?rows.map(row=>'<article><div>'+(row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'')+'<strong>'+esc(row.date)+' · '+esc(row.time)+'</strong><p>'+esc(row.name)+' · гостей: '+esc(row.guests)+' · '+esc(row.zone||'Зона не выбрана')+(row.format?' · формат Omakase «'+esc(row.format)+'»':'')+(row.table?' · стол '+esc(row.table):'')+'</p></div></article>').join(''):'<div class="account-empty-state"><p>Запросов на бронирование пока нет. Выберите дату и предпочтительную зону; демоверсия не резервирует стол.</p><a class="button secondary" href="'+bookingHref+'">Выбрать дату и зону ↗</a></div>'}
  const notificationList=$('#demo-notifications');
  const notifications=demoNotifications();
  if(notificationList){notificationList.innerHTML=notifications.length?notifications.map(item=>'<article class="account-notification-card '+(item.read?'is-read':'is-unread')+'"><div class="account-notification-copy"><div class="account-notification-heading"><h3>'+esc(item.title)+'</h3><span class="account-notification-state">'+(item.read?'Прочитано':'Новое · демо')+'</span></div><p>'+esc(item.message)+'</p><time>'+esc(item.date)+'</time></div><div class="account-notification-actions"><a class="account-inline-link" href="'+(item.kind==='booking'?'booking.html':'cart.html')+'">'+(item.kind==='booking'?'К бронированию ↗':'К корзине ↗')+'</a>'+(item.read?'':'<button class="button secondary" type="button" data-demo-notification-read="'+esc(item.id)+'">Отметить прочитанным</button>')+'</div></article>').join(''):'<div class="account-empty-state"><p>Уведомления появятся после сохранения заказа или запроса на бронирование в этом браузере.</p></div>'}
  const favs=$('#demo-favorites'),phpFavs=$('#account-browser-favorites-list'),overviewFavs=$('#demo-favorites-preview'),empty=$('#demo-favorites-empty'),phpEmpty=$('#account-browser-favorites-empty'),overviewEmpty=$('#demo-favorites-preview-empty');
  if(favs||phpFavs||overviewFavs){
   const ids=window.TokyoCart?.getFavorites()||[],dishes=window.DISHES||[];
   const saved=ids.map(id=>dishes.find(d=>Number(d.id)===Number(id))).filter(Boolean);
   const cardRows=saved.map(d=>'<article class="dish-card"><a class="editorial-product-image" href="'+(staticMode?'dish-'+Number(d.id)+'.html':'pages.php?page=dish&amp;id='+Number(d.id))+'"><img src="'+esc(d.image_url)+'" alt="" loading="lazy"></a><div class="dish-meta"><small>'+esc(d.category_name)+' · '+esc(d.weight)+'</small><button class="favorite-btn" type="button" data-favorite="'+Number(d.id)+'" aria-label="Убрать '+esc(d.name)+' из избранного">♥</button><h3>'+esc(d.name)+'</h3><p>'+esc(d.description)+'</p><div class="dish-bottom"><span class="dish-price">'+Number(d.price).toLocaleString('ru-RU')+' ₸</span><button class="add-to-cart" type="button" data-id="'+Number(d.id)+'">Добавить +</button></div></div></article>');
   const cards=cardRows.join('');
   const emptyFavorites='Пока нет сохранённых блюд. Отметьте понравившееся в меню — оно появится здесь. <a class="text-link" href="'+menuHref+'">Посмотреть меню ↗</a>';
   if(favs){favs.innerHTML=cards;if(empty){empty.hidden=saved.length>0;if(!saved.length)empty.innerHTML=emptyFavorites}}
   if(phpFavs){phpFavs.innerHTML=cards;if(phpEmpty){phpEmpty.hidden=saved.length>0;const phpFavoritesEmpty='Пока нет сохранённых блюд. Отметьте понравившееся в меню — оно появится здесь. <a class="text-link" href="menu.php">Посмотреть меню ↗</a>';if(!saved.length)phpEmpty.innerHTML=phpFavoritesEmpty}}
   if(overviewFavs){const limit=Number(overviewFavs.dataset.accountFavoritesLimit)||3;overviewFavs.innerHTML=cardRows.slice(0,limit).join('');if(overviewEmpty){overviewEmpty.hidden=saved.length>0;if(!saved.length)overviewEmpty.innerHTML=emptyFavorites}}
   const count=$('#account-favorite-count');if(count)count.textContent=String(saved.length);
  }
  const addressRows=readAddresses(),addressList=$('#demo-addresses');if(addressList){addressList.innerHTML=addressRows.length?addressRows.map((v,i)=>'<article><div><strong>Адрес '+(i+1)+' · только этот браузер</strong><p>'+esc(v)+'</p></div><button class="text-button" type="button" data-address-remove="'+i+'" data-address-value="'+esc(v)+'">Удалить</button></article>').join(''):'<p class="menu-note">Адресов пока нет.</p>'}
  const addressPreview=$('#demo-addresses-preview'),addressPreviewEmpty=$('#demo-addresses-preview-empty');if(addressPreview){addressPreview.innerHTML=addressRows.slice(0,2).map((value,index)=>'<article><span class="account-address-index">0'+(index+1)+'</span><div><strong>Адрес '+(index+1)+' · только этот браузер</strong><p>'+esc(value)+'</p></div></article>').join('');if(addressPreviewEmpty)addressPreviewEmpty.hidden=addressRows.length>0}
  const pform=$('[data-local-form="profile"]'),displayName=$('#demo-display-name'),profileName=$('#demo-profile-contact-name'),profilePhone=$('#demo-profile-contact-phone'),profileAvatar=$('.account-demo-avatar');
  if(pform||displayName||profileName||profilePhone||profileAvatar){const profile=readProfile();if(pform&&(!profileHydrated||!profileDraftDirty)){pform.elements.name.value=profile.name||'';pform.elements.phone.value=profile.phone||'';pform.elements.birth_month.value=profile.birthMonth||'';syncBirthdayDays(pform,profile.birthMonth,profile.birthDay);pform.elements.birthday_offer_consent.checked=profile.birthdayConsent;profileHydrated=true}if(displayName)displayName.textContent=profile.name?('Здравствуйте, '+profile.name+'.'):'История, избранное и привилегии в одном месте.';if(profileName)profileName.textContent=profile.name||'Имя не указано';if(profilePhone)profilePhone.textContent=profile.phone||'Телефон не указан';if(profileAvatar){const initials=(profile.name||'').trim().split(/\s+/).filter(Boolean).slice(0,2).map(part=>part.match(/\p{L}/u)?.[0]||'').join('').toLocaleUpperCase('ru-RU');if(initials)profileAvatar.dataset.initials=initials;else delete profileAvatar.dataset.initials}}
  const statNodes=$$('[data-account-stat]');
  if(statNodes.length){
   const ordersCount=read('tokyo-demo-orders').length,bookingsCount=read('tokyo-demo-bookings').length,favoritesCount=window.TokyoCart?.getFavorites().length||0,addressesCount=readAddresses().length;
   const unreadNotifications=notifications.filter(item=>!item.read).length;
   const stats={orders:ordersCount,bookings:bookingsCount,favorites:favoritesCount,notifications:unreadNotifications};
   statNodes.forEach(node=>{node.textContent=String(stats[node.dataset.accountStat]??0)});
   const tabCounts={history:ordersCount+bookingsCount,notifications:unreadNotifications,favorites:favoritesCount,addresses:addressesCount};
   $$('[data-account-tab]').forEach(button=>{
    const key=button.dataset.accountTab;if(!Object.prototype.hasOwnProperty.call(tabCounts,key))return;
    let badge=$('.account-demo-tab-count',button);
    if(!badge){badge=document.createElement('span');badge.className='account-demo-tab-count';badge.setAttribute('aria-hidden','true');button.append(badge)}
    badge.textContent=String(tabCounts[key]);
   });
  }
 }
 $('#demo-notifications')?.addEventListener('click',event=>{
  const button=event.target.closest('[data-demo-notification-read]');if(!button)return;
  const id=button.dataset.demoNotificationRead;
  const saved=read('tokyo-demo-read-notifications').filter(value=>typeof value==='string');
  if(!saved.includes(id))saved.push(id);
  try{localStorage.setItem('tokyo-demo-read-notifications',JSON.stringify(saved));render()}
  catch{const host=$('#demo-notifications');let status=$('[data-notification-error]',host);if(!status){status=document.createElement('p');status.dataset.notificationError='';status.className='form-status error';status.setAttribute('role','status');host.append(status)}status.textContent='Не удалось сохранить прочитанность. Хранилище браузера недоступно.'}
 });
 $$('[data-account-tab]').forEach(button=>button.addEventListener('click',()=>{$$('[data-account-tab]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-current',b===button?'page':'false')});$$('[data-account-panel]').forEach(panel=>panel.hidden=panel.dataset.accountPanel!==button.dataset.accountTab)}));
 $$('[data-account-quick-target]').forEach(button=>button.addEventListener('click',()=>document.querySelector('.account-demo-nav [data-account-tab="'+button.dataset.accountQuickTarget+'"]')?.click()));
 $('[data-account-demo-seed]')?.addEventListener('click',()=>{
  const status=$('[data-account-demo-seed-status]'),catalog=Array.isArray(window.DISHES)?window.DISHES:[];
  const examples=[
   {orderId:'DEMO-TP-8724',date:'Пример · 12 ноября, 19:42',fulfillment:'delivery',items:[[3,1],[6,2]]},
   {orderId:'DEMO-TP-8651',date:'Пример · 8 ноября, 14:20',fulfillment:'pickup',items:[[12,1],[15,1]]},
   {orderId:'DEMO-TP-8607',date:'Пример · 5 ноября, 20:11',fulfillment:'pickup',items:[[19,2]]}
  ];
  try{
   const savedOrders=read('tokyo-demo-orders');
   const current=Array.isArray(savedOrders)?savedOrders.filter(row=>row&&typeof row==='object'):[];
   const existing=new Set(current.map(row=>row?.orderId).filter(Boolean));
   const additions=[];
   for(const example of examples){
    if(existing.has(example.orderId))continue;
    const items=example.items.map(([id,qty])=>{const dish=catalog.find(value=>Number(value.id)===id);if(!dish)return null;const unitPrice=Number(dish.price);if(!Number.isSafeInteger(unitPrice)||unitPrice<0)return null;return{id,name:String(dish.name),qty,unitPrice,lineTotal:unitPrice*qty}});
    if(items.some(item=>!item)){if(status)status.textContent='Учебный пример сейчас недоступен: не удалось сверить позицию с каталогом.';return}
    const total=items.reduce((sum,item)=>sum+item.lineTotal,0);
    additions.push({orderId:example.orderId,date:example.date,name:'Демо-гость',count:items.reduce((sum,item)=>sum+item.qty,0),subtotal:total,total,fulfillment:example.fulfillment,items,demo:true});
   }
   let addressesAdded=0;
   if(localStorage.getItem('tokyo-demo-addresses')===null){
    const demoAddresses=['Учебный пример · Астана, квартал Ханами, дом 17','Учебный пример · Астана, улица Камелии, дом 8'];
    localStorage.setItem('tokyo-demo-addresses',JSON.stringify(demoAddresses));addressesAdded=demoAddresses.length;
   }
   localStorage.removeItem('tokyo-demo-account-seed-dismissed');
   localStorage.setItem('tokyo-demo-account-seeded','1');
   localStorage.setItem('tokyo-demo-orders',JSON.stringify([...current,...additions]));
   const savedBookings=read('tokyo-demo-bookings');
   const bookings=Array.isArray(savedBookings)?savedBookings.filter(row=>row&&typeof row==='object'):[];
   const bookingAdded=!bookings.some(row=>row.demoId==='DEMO-TP-BOOKING');
   if(bookingAdded)bookings.unshift({demoId:'DEMO-TP-BOOKING',date:'Учебный пример · 20 ноября',time:'19:00',name:'Демо-гость',guests:2,zone:'Основной зал',format:'Сезонный',table:'10',demo:true});
   localStorage.setItem('tokyo-demo-bookings',JSON.stringify(bookings));
   const favorites=window.TokyoCart?.getFavorites?.()||[];
   window.TokyoCart?.setFavorites?.([...new Set([...favorites,3,6,19])]);
   render();document.dispatchEvent(new Event('tokyo-demo-data-updated'));
   if(status){const changed=[];if(additions.length)changed.push('добавлено учебных заказов: '+additions.length);if(bookingAdded)changed.push('добавлен учебный запрос на бронь');if(addressesAdded)changed.push('добавлено учебных адресов: '+addressesAdded);status.textContent=changed.length?'В '+changed.join(', ')+'. Блюда избранного взяты из каталога. Всё сохранено только в этом браузере; очистить можно в настройках.':'Учебный пример уже заполнен. Данные остаются только в этом браузере.'}
   const seedPanel=$('.account-demo-seed');if(seedPanel)seedPanel.hidden=true
  }catch{if(status)status.textContent='Не удалось сохранить учебный пример в локальном хранилище браузера.'}
 });
 $('#demo-orders')?.addEventListener('click',event=>{
  const button=event.target.closest('[data-repeat-order]');if(!button)return;
  const status=$('#demo-repeat-status')||(()=>{const node=document.createElement('p');node.id='demo-repeat-status';node.className='form-status';node.setAttribute('aria-live','polite');$('#demo-orders').append(node);return node})();
  let saved;try{saved=JSON.parse(button.dataset.repeatOrder||'[]')}catch{}
  const payload=Array.isArray(saved)?{items:saved,customSets:[]}:(saved&&typeof saved==='object'?saved:null);
  if(!payload||!Array.isArray(payload.items)||!Array.isArray(payload.customSets)){status.textContent='Не удалось прочитать состав заказа.';status.className='form-status error';return}
  const cart=window.TokyoCart;
  status.replaceChildren();if(!cart?.addOrder?.(payload)){status.textContent='Не удалось восстановить заказ целиком: проверьте доступность блюд и лимиты корзины.';status.className='form-status error';return}
  status.append(document.createTextNode('Состав добавлен в корзину. Проверьте его перед оформлением. '));const link=document.createElement('a');link.href='cart.html';link.textContent='Открыть корзину ↗';status.append(link);status.className='form-status success'
 });
 document.addEventListener('submit',event=>{
  const form=event.target;if(!form.matches('[data-local-form]'))return;event.preventDefault();const values=Object.fromEntries(new FormData(form).entries());
  try{
   if(form.dataset.localForm==='profile'){
    const month=String(values.birth_month||'').trim(),day=String(values.birth_day||'').trim(),consent=values.birthday_offer_consent==='1';
    if((month||day)&&!validBirthday(month,day)){writeStatus(form,'Выберите корректные месяц и день рождения или очистите оба поля.',true);return}
    if(consent&&!month){writeStatus(form,'Для отдельного согласия сначала укажите месяц и день рождения.',true);return}
    localStorage.setItem('tokyo-demo-profile',JSON.stringify({name:String(values.name||'').trim(),phone:String(values.phone||'').trim(),birthMonth:month?String(Number(month)):'',birthDay:day?String(Number(day)):'',birthdayConsent:consent}));profileDraftDirty=false;
    const savedMessage=month?(consent?'День рождения и отдельное согласие сохранены только в этом браузере. Статическая версия не создаёт купон и не начисляет бонусы.':'День рождения сохранён только в этом браузере; согласие на персональные предложения не дано.'):'Профиль сохранён только в этом браузере.';
    writeStatus(form,savedMessage);render();
   }else if(form.dataset.localForm==='address'){
    const value=String(values.address||'').trim(),list=readAddresses();
    if(value.length<5||value.length>180){writeStatus(form,'Введите адрес от 5 до 180 символов.',true);return}
    if(list.includes(value)){writeStatus(form,'Этот адрес уже сохранён.',true);return}
    if(list.length>=10){writeStatus(form,'Можно сохранить не более 10 адресов. Удалите ненужный перед добавлением нового.',true);return}
    list.unshift(value);localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list));form.reset();writeStatus(form,'Адрес сохранён только в этом браузере.');render();
   }
  }catch{writeStatus(form,'Не удалось сохранить данные в этом браузере. Введённый текст оставлен в форме.',true)}
 });
 $('#demo-addresses')?.addEventListener('click',event=>{
  const b=event.target.closest('[data-address-remove]');if(!b)return;const list=readAddresses(),index=list.indexOf(b.dataset.addressValue);if(index<0){render();return}
  try{list.splice(index,1);localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list));render()}catch{const form=$('[data-local-form="address"]');if(form)writeStatus(form,'Не удалось удалить адрес из хранилища браузера.',true)}
 });
 $('#demo-clear-data')?.addEventListener('click',()=>{
  const status=$('#demo-settings-status');
  try{window.TokyoCart?.clear();window.TokyoCart?.setFavorites([]);['tokyo-demo-orders','tokyo-demo-bookings','tokyo-demo-reviews','tokyo-demo-contacts','tokyo-demo-profile','tokyo-demo-addresses','tokyo-demo-account-seeded','tokyo-demo-read-notifications'].forEach(key=>localStorage.removeItem(key));localStorage.setItem('tokyo-demo-account-seed-dismissed','1');profileDraftDirty=false;profileHydrated=false;const seedPanel=$('.account-demo-seed');if(seedPanel)seedPanel.hidden=false;if(status){status.textContent='Локальные данные этого сайта очищены.';status.className='form-status success'}}
  catch{if(status){status.textContent='Не все данные удалось очистить. Хранилище браузера недоступно; повторите попытку.';status.className='form-status error'}}
  render();document.dispatchEvent(new Event('tokyo-demo-data-updated'));
 });
 document.addEventListener('tokyo-demo-data-updated',render);document.addEventListener('tokyo-favorites-updated',render);
 const accountDataKeys=['tokyo-demo-orders','tokyo-demo-bookings','tokyo-demo-reviews','tokyo-demo-contacts','tokyo-demo-profile','tokyo-demo-addresses'];
 window.addEventListener('storage',event=>{
  if(event.key===null||accountDataKeys.includes(event.key)||event.key==='tokyo-demo-read-notifications'){
   if((event.key===null||event.key==='tokyo-demo-profile')&&profileDraftDirty){const form=$('[data-local-form="profile"]');if(form)writeStatus(form,'Сохранённый профиль изменился в другой вкладке. Ваш черновик оставлен в форме и пока не сохранён.',true)}
   render();
  }
 });
 const hasExistingLocalData=()=>{try{const stored=accountDataKeys.some(key=>{const raw=localStorage.getItem(key);if(!raw)return false;let value;try{value=JSON.parse(raw)}catch{return true}return Array.isArray(value)?value.length>0:!!(value&&typeof value==='object'&&Object.keys(value).length)});const cart=window.TokyoCart;return stored||Boolean(cart?.get?.().length||cart?.getCustomSets?.().length||cart?.getFavorites?.().length)}catch{return true}};
 let dismissed=false;try{dismissed=localStorage.getItem('tokyo-demo-account-seed-dismissed')==='1'}catch{dismissed=true}
 if(staticMode&&!dismissed&&!hasExistingLocalData()) $('[data-account-demo-seed]')?.click();
 else {try{if(localStorage.getItem('tokyo-demo-account-seeded')==='1'&&(read('tokyo-demo-orders').length||read('tokyo-demo-bookings').length)){const seedPanel=$('.account-demo-seed');if(seedPanel)seedPanel.hidden=true}}catch{}render()}
});

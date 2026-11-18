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
 const read=key=>{try{return JSON.parse(localStorage.getItem(key)||'[]')}catch{return[]}};
 const readRows=key=>{const value=read(key);return Array.isArray(value)?value.filter(row=>row&&typeof row==='object'):[]};
 const writeStatus=(form,msg)=>{const s=$('.form-status',form);if(s){s.textContent=msg;s.className='form-status success'}};
 const staticMode=Boolean($('.account-demo-page'));
 const menuHref=staticMode?'menu.html':'menu.php';
 const bookingHref=staticMode?'booking.html':'pages.php?page=booking';
 const demoNotifications=()=>{
  const orders=readRows('tokyo-demo-orders').map((row,index)=>({id:'order:'+String(row.orderId||row.createdAt||index),kind:'order',title:'Демо-заказ сохранён',message:'Заказ '+String(row.orderId||'из истории')+' сохранён только в этом браузере. Он не оплачен и не отправлен ресторану.',date:String(row.date||'')}));
  const bookings=readRows('tokyo-demo-bookings').map((row,index)=>({id:'booking:'+String(row.demoId||[row.date,row.time,row.name,row.zone,index].join('|')),kind:'booking',title:'Демо-запрос на бронь сохранён',message:'Запрос существует только в этом браузере; ресторан его не получил, стол не подтверждён.',date:[row.date,row.time].filter(Boolean).join(' · ')}));
  const readState=read('tokyo-demo-read-notifications');
  const readIds=new Set(Array.isArray(readState)?readState.filter(value=>typeof value==='string'):[]);
  return [...orders,...bookings].map(row=>({...row,read:readIds.has(row.id)})).sort((a,b)=>b.id.localeCompare(a.id));
 };
 function render(){
  const orders=$('#demo-orders'),bookings=$('#demo-bookings');
  if(orders){
   const rows=read('tokyo-demo-orders').filter(row=>row&&typeof row==='object');
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
    const demoBadge=row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'<span class="account-demo-record-badge">Только этот браузер</span>';
    const firstDishId=Number(items.find(item=>Number.isSafeInteger(Number(item.id))&&Number(item.id)>0)?.id||customSets.flatMap(set=>Array.isArray(set.items)?set.items:[]).find(item=>Number.isSafeInteger(Number(item.id))&&Number(item.id)>0)?.id);
    const previewDish=dishes.find(dish=>Number(dish.id)===firstDishId&&typeof dish.image_url==='string'&&dish.image_url.length>0);
    const preview=previewDish?'<img class="account-order-preview" src="'+esc(previewDish.image_url)+'" alt="'+esc(previewDish.name)+'" loading="lazy" decoding="async">':'';
    const amount=Number(row.subtotal??row.total??0);
    const amountLabel=(Number.isFinite(amount)&&amount>=0?amount:0).toLocaleString('ru-RU')+' ₸';
    return '<article class="demo-order account-demo-order">'+preview+'<div class="demo-order-content"><div class="account-demo-order-head"><div class="account-demo-order-title">'+demoBadge+'<small>'+esc(row.orderId||'Демо-заказ')+'</small><strong>'+esc(row.date)+'</strong></div><div class="account-demo-order-total"><span>Итого · демо</span><strong>'+amountLabel+'</strong></div></div><p class="account-demo-order-meta">'+esc(row.name||'Гость')+' · '+Number(row.count||0)+' позиций · '+esc(row.fulfillment==='pickup'?'Самовывоз':'Демо-доставка')+'</p>'+(allLines?'<details class="demo-order-details"><summary>Состав заказа · '+lineCount+' позиций</summary><ul class="demo-order-items">'+allLines+'</ul></details>':'<p class="menu-note">Состав старого заказа недоступен.</p>')+(lineCount?'<button class="button secondary account-demo-repeat" type="button" data-repeat-order="'+esc(repeatData)+'">Собрать заказ снова</button>':'')+'</div></article>';
   }).join(''):'<div class="account-empty-state"><p>Здесь появятся локальные учебные заказы после демонстрационного оформления. Сейчас история пуста.</p><a class="button secondary" href="'+menuHref+'">Открыть меню ↗</a></div>'
  }
  if(bookings){const rows=read('tokyo-demo-bookings');bookings.innerHTML=rows.length?rows.map(row=>'<article><div>'+(row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'')+'<strong>'+esc(row.date)+' · '+esc(row.time)+'</strong><p>'+esc(row.name)+' · гостей: '+esc(row.guests)+' · '+esc(row.zone||'Зона не выбрана')+(row.format?' · формат Omakase «'+esc(row.format)+'»':'')+(row.table?' · стол '+esc(row.table):'')+'</p></div></article>').join(''):'<div class="account-empty-state"><p>Запросов на бронирование пока нет. Выберите дату и предпочтительную зону; демоверсия не резервирует стол.</p><a class="button secondary" href="'+bookingHref+'">Выбрать дату и зону ↗</a></div>'}
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
  const addressRows=read('tokyo-demo-addresses'),addressList=$('#demo-addresses');if(addressList){addressList.innerHTML=addressRows.length?addressRows.map((v,i)=>'<article><div><strong>Адрес '+(i+1)+' · только этот браузер</strong><p>'+esc(v)+'</p></div><button class="text-button" type="button" data-address-remove="'+i+'">Удалить</button></article>').join(''):'<p class="menu-note">Адресов пока нет.</p>'}
  const addressPreview=$('#demo-addresses-preview'),addressPreviewEmpty=$('#demo-addresses-preview-empty');if(addressPreview){addressPreview.innerHTML=addressRows.slice(0,2).map((value,index)=>'<article><span class="account-address-index">0'+(index+1)+'</span><div><strong>Адрес '+(index+1)+' · только этот браузер</strong><p>'+esc(value)+'</p></div></article>').join('');if(addressPreviewEmpty)addressPreviewEmpty.hidden=addressRows.length>0}
  const pform=$('[data-local-form="profile"]'),displayName=$('#demo-display-name');
  if(pform||displayName){const profile=(()=>{try{return JSON.parse(localStorage.getItem('tokyo-demo-profile')||'{}')}catch{return{}}})();if(pform){pform.elements.name.value=profile.name||'';pform.elements.phone.value=profile.phone||''}if(displayName)displayName.textContent=profile.name?('Здравствуйте, '+profile.name+'.'):'История, избранное и привилегии в одном месте.'}
  const statNodes=$$('[data-account-stat]');
  if(statNodes.length){
   const ordersCount=read('tokyo-demo-orders').length,bookingsCount=read('tokyo-demo-bookings').length,favoritesCount=window.TokyoCart?.getFavorites().length||0,addressesCount=read('tokyo-demo-addresses').length;
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
  localStorage.setItem('tokyo-demo-read-notifications',JSON.stringify(saved));
  render();
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
 document.addEventListener('submit',event=>{const form=event.target;if(!form.matches('[data-local-form]'))return;event.preventDefault();const values=Object.fromEntries(new FormData(form).entries());if(form.dataset.localForm==='profile'){localStorage.setItem('tokyo-demo-profile',JSON.stringify(values));writeStatus(form,'Профиль сохранён только в этом браузере.')}else if(form.dataset.localForm==='address'){const list=read('tokyo-demo-addresses');list.unshift(values.address.trim());localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list.slice(0,10)));form.reset();writeStatus(form,'Адрес сохранён только в этом браузере.');render()}});
 $('#demo-addresses')?.addEventListener('click',event=>{const b=event.target.closest('[data-address-remove]');if(!b)return;const list=read('tokyo-demo-addresses');list.splice(Number(b.dataset.addressRemove),1);localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list));render()});
 $('#demo-clear-data')?.addEventListener('click',()=>{window.TokyoCart?.clear();window.TokyoCart?.setFavorites([]);['tokyo-demo-orders','tokyo-demo-bookings','tokyo-demo-reviews','tokyo-demo-contacts','tokyo-demo-profile','tokyo-demo-addresses','tokyo-demo-account-seeded','tokyo-demo-read-notifications'].forEach(key=>localStorage.removeItem(key));localStorage.setItem('tokyo-demo-account-seed-dismissed','1');const seedPanel=$('.account-demo-seed');if(seedPanel)seedPanel.hidden=false;const s=$('#demo-settings-status');if(s)s.textContent='Локальные данные этого сайта очищены.';render();document.dispatchEvent(new Event('tokyo-demo-data-updated'))});
 document.addEventListener('tokyo-demo-data-updated',render);document.addEventListener('tokyo-favorites-updated',render);
 const accountDataKeys=['tokyo-demo-orders','tokyo-demo-bookings','tokyo-demo-reviews','tokyo-demo-contacts','tokyo-demo-profile','tokyo-demo-addresses'];
 const hasExistingLocalData=()=>{try{const stored=accountDataKeys.some(key=>{const raw=localStorage.getItem(key);if(!raw)return false;let value;try{value=JSON.parse(raw)}catch{return true}return Array.isArray(value)?value.length>0:!!(value&&typeof value==='object'&&Object.keys(value).length)});const cart=window.TokyoCart;return stored||Boolean(cart?.get?.().length||cart?.getCustomSets?.().length||cart?.getFavorites?.().length)}catch{return true}};
 let dismissed=false;try{dismissed=localStorage.getItem('tokyo-demo-account-seed-dismissed')==='1'}catch{dismissed=true}
 if(staticMode&&!dismissed&&!hasExistingLocalData()) $('[data-account-demo-seed]')?.click();
 else {try{if(localStorage.getItem('tokyo-demo-account-seeded')==='1'&&(read('tokyo-demo-orders').length||read('tokyo-demo-bookings').length)){const seedPanel=$('.account-demo-seed');if(seedPanel)seedPanel.hidden=true}}catch{}render()}
});

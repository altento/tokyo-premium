document.addEventListener('DOMContentLoaded',()=>{
 const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const read=key=>{try{return JSON.parse(localStorage.getItem(key)||'[]')}catch{return[]}};
 const writeStatus=(form,msg)=>{const s=$('.form-status',form);if(s){s.textContent=msg;s.className='form-status success'}};
 const staticMode=Boolean($('.account-demo-page'));
 const menuHref=staticMode?'menu.html':'menu.php';
 const bookingHref=staticMode?'booking.html':'pages.php?page=booking';
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
    const demoBadge=row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'';
    return '<article class="demo-order"><div>'+demoBadge+'<small>'+esc(row.orderId||'Демо-заказ')+'</small><strong>'+esc(row.date)+'</strong><p>'+esc(row.name)+' · '+Number(row.count||0)+' позиций · '+Number(row.subtotal??row.total??0).toLocaleString('ru-RU')+' ₸</p><p>'+esc(row.fulfillment==='pickup'?'Самовывоз':'Демо-доставка')+'</p>'+(allLines?'<ul class="demo-order-items">'+allLines+'</ul>':'<p class="menu-note">Состав старого заказа недоступен.</p>')+(lineCount?'<button class="button secondary" type="button" data-repeat-order="'+esc(repeatData)+'">Собрать заказ снова</button>':'')+'</div></article>';
   }).join(''):'<div class="account-empty-state"><p>Здесь появятся локальные учебные заказы после демонстрационного оформления. Сейчас история пуста.</p><a class="button secondary" href="'+menuHref+'">Открыть меню ↗</a></div>'
  }
  if(bookings){const rows=read('tokyo-demo-bookings');bookings.innerHTML=rows.length?rows.map(row=>'<article><div>'+(row.demo===true?'<span class="account-demo-record-badge">Учебный пример · локально</span>':'')+'<strong>'+esc(row.date)+' · '+esc(row.time)+'</strong><p>'+esc(row.name)+' · гостей: '+esc(row.guests)+' · '+esc(row.zone||'Зона не выбрана')+(row.format?' · формат Omakase «'+esc(row.format)+'»':'')+(row.table?' · стол '+esc(row.table):'')+'</p></div></article>').join(''):'<div class="account-empty-state"><p>Запросов на бронирование пока нет. Выберите дату и предпочтительную зону; демоверсия не резервирует стол.</p><a class="button secondary" href="'+bookingHref+'">Выбрать дату и зону ↗</a></div>'}
  const favs=$('#demo-favorites'),phpFavs=$('#account-browser-favorites-list'),empty=$('#demo-favorites-empty'),phpEmpty=$('#account-browser-favorites-empty');
  if(favs||phpFavs){
   const ids=window.TokyoCart?.getFavorites()||[],dishes=window.DISHES||[];
   const saved=ids.map(id=>dishes.find(d=>Number(d.id)===Number(id))).filter(Boolean);
   const cards=saved.map(d=>'<article class="dish-card"><a class="editorial-product-image" href="'+(staticMode?'dish-'+Number(d.id)+'.html':'pages.php?page=dish&amp;id='+Number(d.id))+'"><img src="'+esc(d.image_url)+'" alt="" loading="lazy"></a><div class="dish-meta"><small>'+esc(d.category_name)+' · '+esc(d.weight)+'</small><button class="favorite-btn" type="button" data-favorite="'+Number(d.id)+'" aria-label="Убрать '+esc(d.name)+' из избранного">♥</button><h3>'+esc(d.name)+'</h3><p>'+esc(d.description)+'</p><div class="dish-bottom"><span class="dish-price">'+Number(d.price).toLocaleString('ru-RU')+' ₸</span><button class="add-to-cart" type="button" data-id="'+Number(d.id)+'">Добавить +</button></div></div></article>').join('');
   const emptyFavorites='Пока нет сохранённых блюд. Отметьте понравившееся в меню — оно появится здесь. <a class="text-link" href="'+menuHref+'">Посмотреть меню ↗</a>';
   if(favs){favs.innerHTML=cards;if(empty){empty.hidden=saved.length>0;if(!saved.length)empty.innerHTML=emptyFavorites}}
   if(phpFavs){phpFavs.innerHTML=cards;if(phpEmpty){phpEmpty.hidden=saved.length>0;const phpFavoritesEmpty='Пока нет сохранённых блюд. Отметьте понравившееся в меню — оно появится здесь. <a class="text-link" href="menu.php">Посмотреть меню ↗</a>';if(!saved.length)phpEmpty.innerHTML=phpFavoritesEmpty}}
   const count=$('#account-favorite-count');if(count)count.textContent=String(saved.length);
  }
  const addresses=$('#demo-addresses');if(addresses){const rows=read('tokyo-demo-addresses');addresses.innerHTML=rows.length?rows.map((v,i)=>'<article><div><strong>Адрес '+(i+1)+'</strong><p>'+esc(v)+'</p></div><button class="text-button" type="button" data-address-remove="'+i+'">Удалить</button></article>').join(''):'<p class="menu-note">Адресов пока нет.</p>'}
  const pform=$('[data-local-form="profile"]'),displayName=$('#demo-display-name');
  if(pform||displayName){const profile=(()=>{try{return JSON.parse(localStorage.getItem('tokyo-demo-profile')||'{}')}catch{return{}}})();if(pform){pform.elements.name.value=profile.name||'';pform.elements.phone.value=profile.phone||''}if(displayName)displayName.textContent=profile.name||'Ваш профиль'}
  const statNodes=$$('[data-account-stat]');
  if(statNodes.length){
   const ordersCount=read('tokyo-demo-orders').length,bookingsCount=read('tokyo-demo-bookings').length,favoritesCount=window.TokyoCart?.getFavorites().length||0,addressesCount=read('tokyo-demo-addresses').length;
   const stats={orders:ordersCount,bookings:bookingsCount,favorites:favoritesCount};
   statNodes.forEach(node=>{node.textContent=String(stats[node.dataset.accountStat]??0)});
   const tabCounts={history:ordersCount+bookingsCount,favorites:favoritesCount,addresses:addressesCount};
   $$('[data-account-tab]').forEach(button=>{
    const key=button.dataset.accountTab;if(!Object.prototype.hasOwnProperty.call(tabCounts,key))return;
    let badge=$('.account-demo-tab-count',button);
    if(!badge){badge=document.createElement('span');badge.className='account-demo-tab-count';badge.setAttribute('aria-hidden','true');button.append(badge)}
    badge.textContent=String(tabCounts[key]);
   });
  }
 }
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
   localStorage.setItem('tokyo-demo-orders',JSON.stringify([...current,...additions]));
   const savedBookings=read('tokyo-demo-bookings');
   const bookings=Array.isArray(savedBookings)?savedBookings.filter(row=>row&&typeof row==='object'):[];
   const bookingAdded=!bookings.some(row=>row.demoId==='DEMO-TP-BOOKING');
   if(bookingAdded)bookings.unshift({demoId:'DEMO-TP-BOOKING',date:'Учебный пример · 20 ноября',time:'19:00',name:'Демо-гость',guests:2,zone:'Основной зал',format:'Сезонный',table:'10',demo:true});
   localStorage.setItem('tokyo-demo-bookings',JSON.stringify(bookings));
   const favorites=window.TokyoCart?.getFavorites?.()||[];
   window.TokyoCart?.setFavorites?.([...new Set([...favorites,3,6,19])]);
   render();document.dispatchEvent(new Event('tokyo-demo-data-updated'));
   if(status){const changed=[];if(additions.length)changed.push('добавлено учебных заказов: '+additions.length);if(bookingAdded)changed.push('добавлен учебный запрос на бронь');status.textContent=changed.length?'В '+changed.join(', ')+'. Блюда избранного взяты из каталога. Всё сохранено только в этом браузере; очистить можно в настройках.':'Учебный пример уже заполнен. Данные остаются только в этом браузере.'}
  }catch{if(status)status.textContent='Не удалось сохранить учебный пример в локальном хранилище браузера.'}
 });
 $('#demo-orders')?.addEventListener('click',event=>{
  const button=event.target.closest('[data-repeat-order]');if(!button)return;
  const status=$('#demo-repeat-status')||(()=>{const node=document.createElement('p');node.id='demo-repeat-status';node.className='form-status';node.setAttribute('aria-live','polite');$('#demo-orders').append(node);return node})();
  let saved;try{saved=JSON.parse(button.dataset.repeatOrder||'[]')}catch{}
  const payload=Array.isArray(saved)?{items:saved,customSets:[]}:(saved&&typeof saved==='object'?saved:null);
  if(!payload||!Array.isArray(payload.items)||!Array.isArray(payload.customSets)){status.textContent='Не удалось прочитать состав заказа.';status.className='form-status error';return}
  const cart=window.TokyoCart;let added=0;
  for(const item of payload.items){if(!item||typeof item!=='object'||!Number.isSafeInteger(Number(item.id))||!Number.isSafeInteger(Number(item.qty))||Number(item.qty)<1)continue;if(cart?.add(Number(item.id),Number(item.qty)))added++}
  for(const set of payload.customSets){if(!set||typeof set!=='object'||!Array.isArray(set.items)||!set.items.length)continue;if(cart?.addCustomSet({setType:set.variant||set.setType||'signature',name:set.name||'Авторский сет',items:set.items,qty:set.qty}))added++}
  status.replaceChildren();if(!added){status.textContent='Позиции из этого заказа больше недоступны или превышен лимит сетов.';status.className='form-status error';return}
  status.append(document.createTextNode('Состав добавлен в корзину. Проверьте его перед оформлением. '));const link=document.createElement('a');link.href='cart.html';link.textContent='Открыть корзину ↗';status.append(link);status.className='form-status success'
 });
 document.addEventListener('submit',event=>{const form=event.target;if(!form.matches('[data-local-form]'))return;event.preventDefault();const values=Object.fromEntries(new FormData(form).entries());if(form.dataset.localForm==='profile'){localStorage.setItem('tokyo-demo-profile',JSON.stringify(values));writeStatus(form,'Профиль сохранён только в этом браузере.')}else if(form.dataset.localForm==='address'){const list=read('tokyo-demo-addresses');list.unshift(values.address.trim());localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list.slice(0,10)));form.reset();writeStatus(form,'Адрес сохранён только в этом браузере.');render()}});
 $('#demo-addresses')?.addEventListener('click',event=>{const b=event.target.closest('[data-address-remove]');if(!b)return;const list=read('tokyo-demo-addresses');list.splice(Number(b.dataset.addressRemove),1);localStorage.setItem('tokyo-demo-addresses',JSON.stringify(list));render()});
 $('#demo-clear-data')?.addEventListener('click',()=>{window.TokyoCart?.clear();window.TokyoCart?.setFavorites([]);['tokyo-demo-orders','tokyo-demo-bookings','tokyo-demo-reviews','tokyo-demo-contacts','tokyo-demo-profile','tokyo-demo-addresses'].forEach(key=>localStorage.removeItem(key));const s=$('#demo-settings-status');if(s)s.textContent='Локальные данные этого сайта очищены.';render();document.dispatchEvent(new Event('tokyo-demo-data-updated'))});
 document.addEventListener('tokyo-demo-data-updated',render);document.addEventListener('tokyo-favorites-updated',render);render();
});

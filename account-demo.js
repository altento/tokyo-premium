document.addEventListener('DOMContentLoaded',()=>{
 const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const read=key=>{try{return JSON.parse(localStorage.getItem(key)||'[]')}catch{return[]}};
 const writeStatus=(form,msg)=>{const s=$('.form-status',form);if(s){s.textContent=msg;s.className='form-status success'}};
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
    return '<article class="demo-order"><div><small>'+esc(row.orderId||'Демо-заказ')+'</small><strong>'+esc(row.date)+'</strong><p>'+esc(row.name)+' · '+Number(row.count||0)+' позиций · '+Number(row.subtotal??row.total??0).toLocaleString('ru-RU')+' ₸</p><p>'+esc(row.fulfillment==='pickup'?'Самовывоз':'Демо-доставка')+'</p>'+(allLines?'<ul class="demo-order-items">'+allLines+'</ul>':'<p class="menu-note">Состав старого заказа недоступен.</p>')+(lineCount?'<button class="button secondary" type="button" data-repeat-order="'+esc(repeatData)+'">Собрать заказ снова</button>':'')+'</div></article>';
   }).join(''):'<p class="menu-note">Пока заказов нет.</p>'
  }
  if(bookings){const rows=read('tokyo-demo-bookings');bookings.innerHTML=rows.length?rows.map(row=>'<article><div><strong>'+esc(row.date)+' · '+esc(row.time)+'</strong><p>'+esc(row.name)+' · гостей: '+esc(row.guests)+' · '+esc(row.zone||'Зона не выбрана')+(row.format?' · формат Omakase «'+esc(row.format)+'»':'')+(row.table?' · стол '+esc(row.table):'')+'</p></div></article>').join(''):'<p class="menu-note">Пока запросов на бронь нет.</p>'}
  const favs=$('#demo-favorites'),empty=$('#demo-favorites-empty');
  if(favs){const ids=window.TokyoCart?.getFavorites()||[],dishes=window.DISHES||[];const saved=ids.map(id=>dishes.find(d=>Number(d.id)===Number(id))).filter(Boolean);favs.innerHTML=saved.map(d=>'<article class="dish-card"><a class="editorial-product-image" href="dish-'+Number(d.id)+'.html"><img src="'+esc(d.image_url)+'" alt="" loading="lazy"></a><div class="dish-meta"><small>'+esc(d.category_name)+' · '+esc(d.weight)+'</small><button class="favorite-btn" type="button" data-favorite="'+Number(d.id)+'" aria-label="Убрать '+esc(d.name)+' из избранного">♥</button><h3>'+esc(d.name)+'</h3><p>'+esc(d.description)+'</p><div class="dish-bottom"><span class="dish-price">'+Number(d.price).toLocaleString('ru-RU')+' ₸</span><button class="add-to-cart" type="button" data-id="'+Number(d.id)+'">Добавить +</button></div></div></article>').join('');if(empty)empty.hidden=saved.length>0}
  const addresses=$('#demo-addresses');if(addresses){const rows=read('tokyo-demo-addresses');addresses.innerHTML=rows.length?rows.map((v,i)=>'<article><div><strong>Адрес '+(i+1)+'</strong><p>'+esc(v)+'</p></div><button class="text-button" type="button" data-address-remove="'+i+'">Удалить</button></article>').join(''):'<p class="menu-note">Адресов пока нет.</p>'}
  const profile=(()=>{try{return JSON.parse(localStorage.getItem('tokyo-demo-profile')||'{}')}catch{return{}}})();const pform=$('[data-local-form="profile"]');if(pform){pform.elements.name.value=profile.name||'';pform.elements.phone.value=profile.phone||''}const displayName=$('#demo-display-name');if(displayName)displayName.textContent=profile.name||'Ваш профиль';const stats={orders:read('tokyo-demo-orders').length,bookings:read('tokyo-demo-bookings').length,favorites:(window.TokyoCart?.getFavorites()||[]).length};$$('[data-account-stat]').forEach(node=>{node.textContent=String(stats[node.dataset.accountStat]??0)});
 }
 $$('[data-account-tab]').forEach(button=>button.addEventListener('click',()=>{$$('[data-account-tab]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-current',b===button?'page':'false')});$$('[data-account-panel]').forEach(panel=>panel.hidden=panel.dataset.accountPanel!==button.dataset.accountTab)}));
 $$('[data-account-quick-target]').forEach(button=>button.addEventListener('click',()=>document.querySelector('.account-demo-nav [data-account-tab="'+button.dataset.accountQuickTarget+'"]')?.click()));
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

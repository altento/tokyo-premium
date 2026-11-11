document.addEventListener('submit',event=>{
 const form=event.target;if(!(form instanceof HTMLFormElement)||!form.matches('form[data-api]'))return;
 event.preventDefault();event.stopImmediatePropagation();
 const status=form.querySelector('.form-status');const data=Object.fromEntries(new FormData(form).entries());const type=form.dataset.api;
 const key=type==='order'?'tokyo-demo-orders':type==='booking'?'tokyo-demo-bookings':type==='review'?'tokyo-demo-reviews':'tokyo-demo-contacts';
 let rows=[];try{rows=JSON.parse(localStorage.getItem(key)||'[]')}catch{}
 if(type==='order'){
  let items=[];try{items=JSON.parse(localStorage.getItem('tokyo-cart')||'[]')}catch{}
  const dishes=window.DISHES||[];const lines=items.map(item=>{const dish=dishes.find(d=>Number(d.id)===Number(item.id));return dish?{name:dish.name,qty:Number(item.qty),price:Number(dish.price)}:null}).filter(Boolean);
  const total=lines.reduce((sum,line)=>sum+line.price*line.qty,0);
  if(!lines.length||!total){status.textContent='Добавьте блюда в заказ перед подтверждением.';status.className='form-status error';return}
  rows.unshift({name:data.name,date:new Date().toLocaleString('ru-RU'),total,count:lines.reduce((s,i)=>s+i.qty,0),fulfillment:data.fulfillment||'delivery'});
  localStorage.setItem('tokyo-cart','[]');document.dispatchEvent(new Event('tokyo-cart-updated'));
 }else if(type==='booking'){
  rows.unshift({name:data.name,date:data.date,time:data.time,guests:data.guests,zone:data.zone,table:data.table});
 }else if(type==='review'){
  if(String(data.body||'').trim().length<20){status.textContent='Добавьте отзыв не короче 20 символов.';status.className='form-status error';return}
  rows.unshift({name:data.name,rating:data.rating,text:data.body,date:new Date().toLocaleDateString('ru-RU')});
 }else if(type==='contact'){
  rows.unshift({name:data.name,email:data.email,subject:data.subject,message:data.message,date:new Date().toLocaleString('ru-RU')});
 }
 localStorage.setItem(key,JSON.stringify(rows.slice(0,30)));
 status.textContent=type==='review'?'Спасибо. Отзыв сохранён в этом браузере и не публикуется.':type==='contact'?'Сообщение сохранено только в этом браузере. Оно не отправлено ресторану.':'Готово: демо-запись сохранена только в этом браузере. Это не подтверждённая бронь и не отправленный заказ.';
 status.className='form-status success';form.reset();document.dispatchEvent(new Event('tokyo-demo-data-updated'));
});
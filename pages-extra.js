document.addEventListener('DOMContentLoaded',()=>{
  const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  // Gallery filters include the zone cards and the editorial grid.
  $$('[data-gallery]').forEach(button=>button.addEventListener('click',()=>{
    $$('[data-gallery]').forEach(x=>x.classList.toggle('active',x===button));
    const filter=button.dataset.gallery;
    $$('[data-gallery-filter]').forEach(tile=>{tile.hidden=filter!=='all'&&tile.dataset.galleryFilter!==filter});
  }));
  const dialog=$('.image-lightbox'), lightboxImage=$('img',dialog||document);
  $$('[data-lightbox]').forEach(button=>button.addEventListener('click',()=>{
    if(!dialog||!lightboxImage)return;
    lightboxImage.src=button.dataset.lightbox;lightboxImage.alt=button.getAttribute('aria-label')||'Иллюстративная фотография';
    if(dialog.showModal)dialog.showModal();else dialog.setAttribute('open','');
  }));
  $('.lightbox-close')?.addEventListener('click',()=>dialog?.close());
  dialog?.addEventListener('click',event=>{if(event.target===dialog)dialog.close()});
  const tableInput=$('[name="table"]'), tableStatus=$('.table-selection');
  $$('[data-table-select]').forEach(button=>button.addEventListener('click',()=>{
    $$('[data-table-select]').forEach(item=>{item.setAttribute('aria-pressed',String(item===button));item.classList.toggle('selected',item===button)});
    if(tableInput)tableInput.value=button.dataset.tableSelect;
    if(tableStatus)tableStatus.textContent='Выбран предпочтительный стол №'+button.dataset.tableSelect+'. Доступность подтвердит ресторан.';
  }));
  const getCart=()=>{try{return JSON.parse(localStorage.getItem('tokyo-cart')||'[]')}catch{return[]}};
  const setCart=cart=>{localStorage.setItem('tokyo-cart',JSON.stringify(cart));document.dispatchEvent(new Event('tokyo-cart-updated'))};
  const esc=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function renderCart(){
    const container=$('#cart-page-items');if(!container)return;
    const dishes=window.DISHES||[],cart=getCart().filter(item=>dishes.some(d=>Number(d.id)===Number(item.id)));
    if(cart.length!==getCart().length)setCart(cart);
    let total=0;container.innerHTML='';
    cart.forEach(item=>{
      const dish=dishes.find(d=>Number(d.id)===Number(item.id));if(!dish)return;
      const line=Number(dish.price)*Number(item.qty);total+=line;
      const row=document.createElement('article');row.className='cart-page-item';
      row.innerHTML='<img src="'+esc(dish.image_url)+'" alt="" loading="lazy"><div><small>'+esc(dish.category_name)+' · '+esc(dish.weight)+'</small><h3>'+esc(dish.name)+'</h3><p>'+esc(dish.description)+'</p><strong>'+Number(dish.price).toLocaleString('ru-RU')+' ₸ / шт.</strong></div><div class="cart-page-controls"><button type="button" data-cart-step="-1" data-id="'+dish.id+'" aria-label="Уменьшить количество '+esc(dish.name)+'">−</button><span>'+Number(item.qty)+'</span><button type="button" data-cart-step="1" data-id="'+dish.id+'" aria-label="Увеличить количество '+esc(dish.name)+'">+</button><button type="button" class="text-button" data-cart-remove="'+dish.id+'">Удалить</button></div><strong class="line-price">'+line.toLocaleString('ru-RU')+' ₸</strong>';container.append(row)
    });
    const empty=$('#cart-page-empty');if(empty)empty.hidden=cart.length>0;
    const totalNode=$('#cart-page-total');if(totalNode)totalNode.textContent=total.toLocaleString('ru-RU')+' ₸';
    const itemsField=$('#checkout-form [name="items"]');if(itemsField)itemsField.value=JSON.stringify(cart);
    const submit=$('#checkout-form button[type="submit"]');if(submit)submit.disabled=cart.length===0;
  }
  $('#cart-page-items')?.addEventListener('click',event=>{
    const button=event.target.closest('[data-cart-step],[data-cart-remove]');if(!button)return;
    let cart=getCart(),id=Number(button.dataset.id||button.dataset.cartRemove),item=cart.find(x=>Number(x.id)===id);
    if(button.hasAttribute('data-cart-remove'))cart=cart.filter(x=>Number(x.id)!==id);
    else if(item){item.qty=Math.min(20,Math.max(0,Number(item.qty)+Number(button.dataset.cartStep)));if(!item.qty)cart=cart.filter(x=>Number(x.id)!==id)}
    setCart(cart);renderCart();
  });
  $('#cart-clear')?.addEventListener('click',()=>{setCart([]);renderCart()});
  document.addEventListener('tokyo-cart-updated',renderCart);window.addEventListener('storage',renderCart);renderCart();
});
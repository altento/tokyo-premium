document.addEventListener('DOMContentLoaded',()=>{
  const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  // Filters affect the gallery only; room cards remain visible as navigation choices.
  const galleryGrid=$('#gallery-grid');
  $$('[data-gallery]').forEach(button=>button.addEventListener('click',()=>{
    $$('[data-gallery]').forEach(x=>x.classList.toggle('active',x===button));
    const filter=button.dataset.gallery;
    $$('[data-gallery-filter]',galleryGrid||document).forEach(tile=>{tile.hidden=filter!=='all'&&tile.dataset.galleryFilter!==filter});
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
  const fulfillment=$('#fulfillment'), addressField=$('#checkout-address'), addressWrap=$('#checkout-address-field');
  const checkoutForm=$('#checkout-form');
  function syncFulfillment(){
    if(!fulfillment||!addressField)return;
    const delivery=fulfillment.value!=='pickup';
    addressField.required=delivery;
    addressField.disabled=!delivery;
    addressField.setCustomValidity('');
    addressField.removeAttribute('aria-invalid');
    if(addressWrap)addressWrap.hidden=!delivery;
    if(!delivery)addressField.value='';
  }
  function resetCheckout(form=checkoutForm){
    if(!form||form!==checkoutForm)return;
    form.reset();
    // reset() restores the select's default but does not dispatch `change`.
    // Recompute every dependent field immediately after the browser reset.
    syncFulfillment();
    form.classList.remove('was-validated');
    form.querySelectorAll('[aria-invalid="true"]').forEach(field=>field.removeAttribute('aria-invalid'));
    const submit=form.querySelector('button[type="submit"]');
    if(submit)submit.disabled=form.dataset.api==='order'&&!(window.TokyoCart?.get().length||window.TokyoCart?.getCustomSets().length);
  }
  function clearCheckoutStatus(){
    const status=checkoutForm?.querySelector('.form-status');
    if(status){status.textContent='';status.className='form-status';}
  }
  fulfillment?.addEventListener('change',()=>{syncFulfillment();clearCheckoutStatus()});
  checkoutForm?.addEventListener('input',clearCheckoutStatus);
  checkoutForm?.addEventListener('change',clearCheckoutStatus);
  addressField?.addEventListener('input',clearCheckoutStatus);
  document.addEventListener('tokyo-cart-updated',clearCheckoutStatus);
  checkoutForm?.addEventListener('reset',()=>queueMicrotask(syncFulfillment));
  window.TokyoCheckout=Object.freeze({syncFulfillment,reset:resetCheckout});
  syncFulfillment();
  const esc=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const recommendationContainer=$('.cart-recommendations');
  const allRecommendations=recommendationContainer?$$('.dish-card',recommendationContainer):[];
  function renderCart(){
    const container=$('#cart-page-items');if(!container)return;
    const store=window.TokyoCart;if(!store)return;
    const dishes=store.catalog(),cart=store.get(),customSets=store.customSetSnapshot();
    const cartIds=new Set(cart.map(item=>Number(item.id)));
    customSets.forEach(set=>set.items.forEach(item=>cartIds.add(Number(item.id))));
    if(recommendationContainer){
      const suggestions=allRecommendations.filter(card=>{const id=Number(card.querySelector('.add-to-cart')?.dataset.id);return id>0&&!cartIds.has(id)}).slice(0,3);
      recommendationContainer.replaceChildren(...suggestions);
    }
    container.innerHTML='';
    cart.forEach(item=>{
      const dish=dishes.find(d=>Number(d.id)===Number(item.id));if(!dish)return;
      const line=Number(dish.price)*Number(item.qty);
      const row=document.createElement('article');row.className='cart-page-item';
      row.innerHTML='<img src="'+esc(dish.image_url)+'" alt="" loading="lazy"><div class="cart-page-copy"><small>'+esc(dish.category_name)+' · '+esc(dish.weight)+'</small><h3>'+esc(dish.name)+'</h3><p>'+esc(dish.description)+'</p><strong>'+Number(dish.price).toLocaleString('ru-RU')+' ₸ / шт.</strong></div><div class="cart-page-side"><div class="cart-page-controls"><button type="button" data-cart-step="-1" data-id="'+dish.id+'" aria-label="Уменьшить количество '+esc(dish.name)+'">−</button><span>'+Number(item.qty)+'</span><button type="button" data-cart-step="1" data-id="'+dish.id+'" aria-label="Увеличить количество '+esc(dish.name)+'">+</button><button type="button" class="text-button" data-cart-remove="'+dish.id+'">Удалить</button></div><strong class="line-price">'+line.toLocaleString('ru-RU')+' ₸</strong></div>';container.append(row)
    });
    customSets.forEach(set=>{
      const composition=set.items.map(item=>{const dish=dishes.find(row=>Number(row.id)===Number(item.id));return (dish?.name||'Блюдо')+' × '+Number(item.qty)}).join(' · ');
      const setPhotos=set.items.map(item=>dishes.find(dish=>Number(dish.id)===Number(item.id))).filter(dish=>dish?.image_url).slice(0,3);
      const photoMarkup=setPhotos.map((dish,index)=>'<img src="'+esc(dish.image_url)+'" alt="" loading="lazy" decoding="async" class="cart-set-photo cart-set-photo-'+(index+1)+'">').join('');
      const row=document.createElement('article');row.className='cart-page-item cart-page-custom-set';
      row.innerHTML='<div class="cart-set-photos" role="img" aria-label="Состав сета: '+esc(set.items.map(item=>dishes.find(dish=>Number(dish.id)===Number(item.id))?.name||'блюдо').slice(0,3).join(', '))+'">'+photoMarkup+'</div><div class="cart-page-copy"><small>Конструктор · '+esc(set.variant||set.setType)+' · цена ориентировочная</small><h3>'+esc(set.name)+'</h3><p>'+esc(composition)+'</p><strong>'+Number(set.unitPrice).toLocaleString('ru-RU')+' ₸ / сет · оценка</strong></div><div class="cart-page-side"><div class="cart-page-controls"><button type="button" data-custom-set-step="-1" data-custom-set-key="'+esc(set.key)+'" aria-label="Уменьшить количество сета '+esc(set.name)+'">−</button><span>'+Number(set.qty)+'</span><button type="button" data-custom-set-step="1" data-custom-set-key="'+esc(set.key)+'" aria-label="Увеличить количество сета '+esc(set.name)+'">+</button><button type="button" class="text-button" data-custom-set-remove="'+esc(set.key)+'">Удалить</button></div><strong class="line-price">'+Number(set.lineTotal).toLocaleString('ru-RU')+' ₸ · оценка</strong></div>';container.append(row);
    });
    const hasLines=cart.length>0||customSets.length>0;
    const empty=$('#cart-page-empty');if(empty)empty.hidden=hasLines;
    const totalNode=$('#cart-page-total');if(totalNode)totalNode.textContent=store.total().toLocaleString('ru-RU')+' ₸'+(customSets.length?' · оценка':'');
    const payload=store.orderPayload();
    const form=$('#checkout-form');
    const secretCodeField=form?.querySelector('[name="secret_code"]');
    if(secretCodeField){try{secretCodeField.value=localStorage.getItem('tokyo-secret-menu-code')||''}catch(_){secretCodeField.value=''}}
    const itemsField=form?.querySelector('[name="items"]');if(itemsField)itemsField.value=JSON.stringify(payload.items);
    let setsField=form?.querySelector('[name="custom_sets"]');if(form&&!setsField){setsField=document.createElement('input');setsField.type='hidden';setsField.name='custom_sets';form.append(setsField)}if(setsField)setsField.value=JSON.stringify(payload.custom_sets);
    const submit=form?.querySelector('button[type="submit"]');if(submit)submit.disabled=!hasLines;
  }
  $('#cart-page-items')?.addEventListener('click',event=>{
    const button=event.target.closest('[data-cart-step],[data-cart-remove],[data-custom-set-step],[data-custom-set-remove]');if(!button)return;
    const store=window.TokyoCart;if(!store)return;
    const setKey=button.dataset.customSetKey||button.dataset.customSetRemove;
    if(setKey){if(button.hasAttribute('data-custom-set-remove'))store.removeCustomSet(setKey);else store.adjustCustomSet(setKey,Number(button.dataset.customSetStep));return}
    const id=Number(button.dataset.id||button.dataset.cartRemove);
    if(button.hasAttribute('data-cart-remove'))store.remove(id);
    else store.adjust(id,Number(button.dataset.cartStep));
  });
  $('#cart-clear')?.addEventListener('click',()=>window.TokyoCart?.clear());
  document.addEventListener('tokyo-cart-updated',renderCart);window.addEventListener('storage',renderCart);renderCart();
});

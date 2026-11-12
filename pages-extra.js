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
  const fulfillment=$('#fulfillment'), addressField=$('#checkout-address'), addressWrap=$('#checkout-address-field');
  function syncFulfillment(){if(!fulfillment||!addressField)return;const delivery=fulfillment.value!=='pickup';addressField.required=delivery;addressField.disabled=!delivery;if(addressWrap)addressWrap.hidden=!delivery;if(!delivery)addressField.value=''}
  fulfillment?.addEventListener('change',syncFulfillment);syncFulfillment();
  const esc=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function renderCart(){
    const container=$('#cart-page-items');if(!container)return;
    const store=window.TokyoCart;if(!store)return;
    const dishes=store.catalog(),cart=store.get();
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
    const store=window.TokyoCart,id=Number(button.dataset.id||button.dataset.cartRemove);if(!store)return;
    if(button.hasAttribute('data-cart-remove'))store.remove(id);
    else store.adjust(id,Number(button.dataset.cartStep));
  });
  $('#cart-clear')?.addEventListener('click',()=>window.TokyoCart?.clear());
  document.addEventListener('tokyo-cart-updated',renderCart);window.addEventListener('storage',renderCart);renderCart();
});

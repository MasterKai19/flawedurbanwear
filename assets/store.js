document.querySelectorAll('[data-image]').forEach(button=>button.addEventListener('click',()=>{const img=document.getElementById('main-image');img.src=button.dataset.image;img.alt=button.dataset.alt;document.querySelectorAll('[data-image]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',b===button?'true':'false')})}));
const area=document.getElementById('delivery-area');if(area){const update=()=>{const fee=area.selectedOptions[0].dataset.fee;const subtotal=Number(document.getElementById('checkout-subtotal').dataset.subtotal);const known=fee!==undefined&&fee!=='';document.getElementById('delivery-amount').textContent=known?'৳'+Number(fee).toLocaleString():area.value?'To be confirmed':'Select area';document.getElementById('checkout-total').textContent=known?'৳'+(subtotal+Number(fee)).toLocaleString():'Pending delivery';};area.addEventListener('change',update);update();document.getElementById('checkout-form').addEventListener('submit',()=>{const button=document.getElementById('place-order');button.disabled=true;button.textContent='SAVING YOUR ORDER…';});}
// Compress admin uploads before sending them, including on hosts without GD.
document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form=>{
 let ready=false,busy=false;
 const message=document.createElement('p');message.className='hint';message.setAttribute('role','status');form.append(message);
 form.addEventListener('submit',async event=>{
  if(ready)return;event.preventDefault();if(busy)return;busy=true;
  const button=event.submitter;const inputs=[...form.querySelectorAll('input[type="file"]')];
  if(button)button.disabled=true;message.textContent='Preparing photos…';
  try{
   const prepared=[];
   for(const input of inputs){
    const files=[];
    for(const file of input.files){
     if(!['image/jpeg','image/png','image/webp'].includes(file.type))throw new Error('Choose a JPEG, PNG or WebP image.');
     if(file.size>20*1024*1024)throw new Error('Each original photo must be under 20 MB.');
     const bitmap=await createImageBitmap(file);let output=file;
     try{
      const scale=Math.min(1,1600/Math.max(bitmap.width,bitmap.height));
      const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));
      canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);
      const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/webp',.8));
      if(blob&&blob.size<file.size){const extension=blob.type==='image/webp'?'webp':'png';output=new File([blob],file.name.replace(/\.[^.]+$/,'')+'.'+extension,{type:blob.type});}
      canvas.width=canvas.height=1;
     }finally{bitmap.close();}
     files.push(output);
    }
    prepared.push([input,files]);
   }
   const total=prepared.flatMap(([,files])=>files).reduce((n,f)=>n+f.size,0);
   if(total>3*1024*1024)throw new Error('Photos are still over 3 MB combined after compression. Save fewer photos at a time.');
   for(const [input,files] of prepared){const transfer=new DataTransfer();files.forEach(f=>transfer.items.add(f));input.files=transfer.files;}
   ready=true;message.textContent='Photos prepared. Saving product…';if(button)button.disabled=false;form.requestSubmit(button||undefined);
  }catch(error){message.textContent=error.message||'Could not prepare photos. Please try smaller JPEG or WebP files.';}
  finally{busy=false;if(button)button.disabled=false;}
 });
});
const passwordToggle=document.querySelector('.password-toggle');
if(passwordToggle)passwordToggle.addEventListener('click',()=>{const input=document.getElementById(passwordToggle.getAttribute('aria-controls'));const show=input.type==='password';input.type=show?'text':'password';passwordToggle.textContent=show?'Hide password':'Show password';passwordToggle.setAttribute('aria-pressed',String(show));});
// Native SVG cursors need no mousemove handlers or animation loops.
const photoHover=matchMedia('(hover:hover) and (pointer:fine)');
document.querySelectorAll('.product-card .card-back').forEach(back=>{
 const card=back.closest('.product-card');
 const loadBack=()=>{if(back.dataset.loading)return;back.dataset.loading='1';back.addEventListener('load',()=>back.parentElement.classList.add('back-ready'),{once:true});back.addEventListener('error',()=>{back.parentElement.classList.remove('back-ready');back.removeAttribute('src');delete back.dataset.loading;},{once:true});back.src=back.dataset.backSrc;};
 card.addEventListener('pointerenter',event=>{if(photoHover.matches&&event.pointerType!=='touch')loadBack();});
 card.addEventListener('focus',()=>{if(card.matches(':focus-visible'))loadBack();});
});

const photoEditor=document.querySelector('[data-photo-editor]');
if(photoEditor){
 const refreshPhotos=()=>Array.from(photoEditor.children).forEach((tile,i,all)=>{
  tile.querySelector('[data-photo-role]').textContent=i===0?'Cover':i===1?'Hover':'Gallery';
  tile.querySelector('[data-photo-move="-1"]').disabled=i===0;
  tile.querySelector('[data-photo-move="1"]').disabled=i===all.length-1;
 });
 photoEditor.addEventListener('click',e=>{
  const button=e.target.closest('button');if(!button)return;const tile=button.closest('.photo-tile');
  if(button.hasAttribute('data-photo-remove'))tile.remove();
  else if(button.dataset.photoMove==='-1'&&tile.previousElementSibling)photoEditor.insertBefore(tile,tile.previousElementSibling);
  else if(button.dataset.photoMove==='1'&&tile.nextElementSibling)photoEditor.insertBefore(tile.nextElementSibling,tile);
  refreshPhotos();
 });refreshPhotos();
}
const productSearch=document.querySelector('[data-product-search]');
const productFilter=document.querySelector('[data-product-filter]');
const filterProducts=()=>document.querySelectorAll('[data-product-list] .bag-row').forEach(row=>{
 row.hidden=!row.textContent.toLowerCase().includes(productSearch.value.toLowerCase())||(productFilter.value!=='all'&&row.dataset.status!==productFilter.value);
});
productSearch?.addEventListener('input',filterProducts);productFilter?.addEventListener('change',filterProducts);
// A server-backed bag drawer: prices, stock and quantities remain authoritative.
const bagDrawer=document.getElementById('bag-drawer');
if(bagDrawer&&typeof bagDrawer.showModal==='function'){
 const content=bagDrawer.querySelector('[data-bag-content]');let busy=false;let trigger=null;
 const openBag=(source)=>{trigger=source||document.activeElement;if(!bagDrawer.open){bagDrawer.showModal();document.body.classList.add('bag-is-open');}};
 const showError=(message)=>{let node=content.querySelector('[role="status"]');if(!node){node=document.createElement('p');node.setAttribute('role','status');content.prepend(node);}node.textContent=message;};
 const request=async(data)=>{
  if(busy)return;busy=true;bagDrawer.setAttribute('aria-busy','true');
  content.querySelectorAll('[data-bag-quantity],[data-bag-remove]').forEach(b=>b.disabled=true);
  try{
   const response=await fetch(bagDrawer.dataset.cartUrl,{method:data?'POST':'GET',headers:{Accept:'application/json'},credentials:'same-origin',body:data||undefined});
   const result=await response.json();
   if(result.html){content.innerHTML=result.html;document.querySelectorAll('.bag-button span').forEach(n=>n.textContent=String(result.count));content.querySelector('[data-close-bag]')?.focus();}
   else throw new Error(result.error||'Could not load your bag.');
  }catch(error){showError(error.message==='Failed to fetch'?'Connection interrupted. Open your bag again to check its contents before retrying.':error.message);}
  finally{busy=false;bagDrawer.removeAttribute('aria-busy');}
 };
 document.querySelectorAll('[data-open-bag]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();openBag(link);request();}));
 document.querySelectorAll('[data-add-to-bag]').forEach(form=>form.addEventListener('submit',async event=>{
  if(event.submitter?.value==='checkout')return;
  event.preventDefault();if(busy)return;const button=event.submitter;const data=new FormData(form);data.set('destination','cart');
  if(button)button.disabled=true;openBag(button);await request(data);if(button)button.disabled=false;
 }));
 bagDrawer.addEventListener('click',event=>{
  if(event.target===bagDrawer){const box=bagDrawer.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)bagDrawer.close();return;}
  const button=event.target.closest('button');if(!button)return;
  if(button.hasAttribute('data-close-bag')){bagDrawer.close();return;}
  if(busy)return;const token=content.querySelector('[data-bag-csrf]')?.value;
  if(button.hasAttribute('data-bag-remove')||button.hasAttribute('data-bag-quantity')){
   const data=new FormData();data.set('csrf',token||'');
   if(button.hasAttribute('data-bag-remove')){data.set('action','remove');data.set('key',button.dataset.bagRemove);}
   else{data.set('action','update');data.set('qty['+button.dataset.bagKey+']',button.dataset.bagQuantity);}
   request(data);
  }
 });
 bagDrawer.addEventListener('close',()=>{document.body.classList.remove('bag-is-open');trigger?.focus();});
}

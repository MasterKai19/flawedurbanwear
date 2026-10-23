document.querySelectorAll('[data-image]').forEach(button=>button.addEventListener('click',()=>{const img=document.getElementById('main-image');img.src=button.dataset.image;img.alt=button.dataset.alt;document.querySelectorAll('[data-image]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',b===button?'true':'false')})}));
const area=document.getElementById('delivery-area');if(area){const update=()=>{const fee=area.selectedOptions[0].dataset.fee;const subtotal=Number(document.getElementById('checkout-subtotal').dataset.subtotal);const known=fee!==undefined&&fee!=='';document.getElementById('delivery-amount').textContent=known?'৳'+Number(fee).toLocaleString():area.value?'To be confirmed':'Select area';document.getElementById('checkout-total').textContent=known?'৳'+(subtotal+Number(fee)).toLocaleString():'Pending delivery';};area.addEventListener('change',update);update();document.getElementById('checkout-form').addEventListener('submit',()=>{const button=document.getElementById('place-order');button.disabled=true;button.textContent='SAVING YOUR ORDER…';});}
document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form=>form.addEventListener('submit',event=>{const bytes=[...form.querySelectorAll('input[type="file"]')].flatMap(input=>[...input.files]).reduce((sum,file)=>sum+file.size,0);if(bytes>3*1024*1024){event.preventDefault();alert('Please keep the combined photo upload under 3 MB.');}}));
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

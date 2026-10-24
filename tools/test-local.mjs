import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
const root=resolve(import.meta.dirname,'..');
const php='D:/SOFTWARE/xamp/php/php.exe',mysql='D:/SOFTWARE/xamp/mysql/bin/mysql.exe';
const database='flawed_vercel_test_'+Date.now();
const sql=q=>execFileSync(mysql,['--default-character-set=utf8mb4','--host=127.0.0.1','--port=13317','--user=root','--batch','--skip-column-names','-e',q],{encoding:'utf8'}).trim();
sql(`CREATE DATABASE ${database} CHARACTER SET utf8mb4`);
const query=q=>sql(`USE ${database};${q}`);
const setupKey='local-test-setup-key-only-1234567890123456789';
const env={...process.env,DB_HOST:'127.0.0.1',DB_PORT:'13317',DB_NAME:database,DB_USER:'root',DB_PASSWORD:'',OWNER_EMAIL:'test-owner@example.test',OWNER_PASSWORD:'Only-Test-Password-42',CRON_SECRET:'local-test-cron-secret-only-1234567890123456'};
delete env.VERCEL;
const servers=[18095,18096].map(port=>spawn(php,['-S',`127.0.0.1:${port}`,'api/index.php'],{cwd:root,env,stdio:['ignore','pipe','pipe'],windowsHide:true}));
let logs='';for(const s of servers)s.stderr.on('data',b=>logs+=b);
const base='http://127.0.0.1:18095/';
function client(){
 const cookies=new Map();
 const request=async(path='',data,port=18095,extraHeaders={})=>{
  const headers={...extraHeaders,cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; ')};
  const form=data instanceof FormData;
  if(data&&!form)headers['Content-Type']='application/x-www-form-urlencoded';
  const r=await fetch(`http://127.0.0.1:${port}/`+path,{redirect:'manual',headers,...(data?{method:'POST',body:form?data:new URLSearchParams(data)}:{})});
  for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}
  const html=await r.text();assert(!/Fatal error|Warning:|temporarily unavailable/i.test(html),html.slice(0,200));
  return {html,status:r.status,location:r.headers.get('location'),setCookies:r.headers.getSetCookie()};
 };
 request.cookies=cookies;return request;
}
const token=h=>{const m=h.match(/name="csrf" value="([^"]+)"/);assert(m,'Missing CSRF');return m[1]};
try{
 for(let n=0;n<30;n++){try{await (await fetch(base+'assets/store.css')).arrayBuffer();break}catch{await new Promise(r=>setTimeout(r,100))}}
 const owner=client(),buyer=client(),other=client();
 let r=await owner('');assert.equal(r.status,200);
 assert.equal(query('SELECT COUNT(*) FROM cloud_sessions'),'0','Anonymous homepage must not create a database session');
 query("UPDATE settings SET value='1' WHERE name='installation_version'");
 r=await owner('');assert.equal(r.status,200);
 assert.equal(query("SELECT value FROM settings WHERE name='installation_version'"),'4','Existing stores migrate safely');
 assert.equal(query("SELECT image FROM products WHERE sku='FU-LIVING'"),'assets/products/fu-living-01.jpg');
 assert.equal(query("SELECT image FROM products WHERE sku='FU-MIRACLE'"),'assets/products/fu-miracle-05.jpg');
 assert.equal(query("SELECT COUNT(*) FROM products WHERE sku='FU-PASSION' AND back_image IS NULL"),'1');
 assert.equal((r.html.match(/class="card-back"/g)||[]).length,12,'All catalogue products have an alternate photo');
 assert.equal(query('SELECT COUNT(*) FROM products'),'12');
 assert.equal(query('SELECT COUNT(*) FROM variants'),'46');
 assert.equal(query('SELECT SUM(stock) FROM variants'),'4');
 assert.equal(query('SELECT COUNT(*) FROM admins'),'1');
 r=await other('setup.php',{email:'intruder@example.test',password:'intruder-password-123',setup_key:setupKey});
 assert.equal(r.status,302);assert.equal(r.location,'/admin/login.php');
 assert.equal(query('SELECT COUNT(*) FROM admins'),'1');
 assert.equal((await other('admin/index.php')).status,302);
 for(const path of ['server/app/config.php','server/database/products.csv','app/config.php','.env','tools/test-local.mjs','api/php.ini'])assert.equal((await other(path)).status,404,path);
 assert.equal((await other('cron/notifications')).status,401);
 for(const [cat,count] of [['',12],['Hoodies',6],['T-shirts',4],['Lowers',2]]){
  r=await buyer('index.php?category='+encodeURIComponent(cat));assert.equal((r.html.match(/class="product-card"/g)||[]).length,count);
 }
 r=await owner('admin/login.php');r=await owner('admin/login.php',{csrf:token(r.html),email:'test-owner@example.test',password:'Only-Test-Password-42'});
 assert.equal(r.status,302);
 r=await owner('admin/index.php',undefined,18096);assert.equal(r.status,200,'Session must survive a different function instance');
 r=await owner('admin/settings.php');r=await owner('admin/settings.php',{csrf:token(r.html),dhaka:'60',outside:'120',estimate_dhaka:'2–3 working days',estimate_outside:'4–5 working days'});assert.equal(r.status,302);
 assert.equal(query("SELECT value FROM settings WHERE name='delivery_estimate_dhaka'"),'2–3 working days');
 const passion=query("SELECT id FROM products WHERE sku='FU-PASSION'"),living=query("SELECT id FROM products WHERE sku='FU-LIVING'");
 r=await buyer('product.php?slug=living-tee');assert.match(r.html,/SOLD OUT/);assert(!r.html.includes('BUY NOW'));
 r=await buyer('cart.php',{csrf:token(r.html),action:'add',product_id:living,size:'L',quantity:'1'});assert.equal(r.status,302);
 r=await buyer('cart.php');assert.match(r.html,/unavailable/);
 assert.equal((await buyer('cart.php',{csrf:'invalid',action:'add',product_id:passion,size:'L',quantity:'1'})).status,403);
 r=await buyer('product.php?slug=fu-passion');
 r=await buyer('cart.php',{csrf:token(r.html),action:'add',product_id:passion,size:'L',quantity:'1'});assert.equal(r.status,302);
 r=await buyer('checkout.php',undefined,18096);assert.match(r.html,/PASSION/);
 const checkoutKey=r.html.match(/name="checkout_key" value="([^"]+)"/)[1];
 const data={csrf:token(r.html),checkout_key:checkoutKey,name:'Test Customer',phone:'01712345678',email:'',address:'Test delivery address Dhaka',area:'dhaka',notes:'Local test only',agree:'1'};
 r=await buyer('checkout.php',data);assert.equal(r.status,302,r.html);
 assert.equal(query(`SELECT stock FROM variants WHERE product_id=${passion} AND size='L'`),'2');
 assert.equal(query('SELECT total FROM orders'),'1110');
 assert.equal(query('SELECT COUNT(*) FROM order_notifications'),'1');
 r=await buyer('checkout.php',data);assert.equal(r.status,302);assert.equal(query('SELECT COUNT(*) FROM orders'),'1','Duplicate checkout must not create another order');
 // Public tracking requires both details and does not reveal private fields.
 const reference=query('SELECT reference FROM orders LIMIT 1');const tracker=client();
 let tracking=await tracker('track.php');const trackToken=token(tracking.html);
 assert.equal((await tracker('track.php',{csrf:'wrong',reference,phone:data.phone})).status,403);
 tracking=await tracker('track.php',{csrf:trackToken,reference,phone:'01900000000'});assert.match(tracking.html,/could not match/);assert(!tracking.html.includes('Current status:'));
 tracking=await tracker('track.php',{csrf:trackToken,reference,phone:'+8801712345678'});assert.match(tracking.html,/Current status:/);assert.match(tracking.html,/Pending/);assert(!tracking.html.includes(data.address));assert(!tracking.html.includes('access_token'));
 for(let n=0;n<9;n++)tracking=await tracker('track.php',{csrf:trackToken,reference:'invalid',phone:data.phone});assert.equal(tracking.status,429,'Tracking lookup must be rate limited');
 r=await buyer('index.php?q=PASSION&stock=available&size=L');assert.equal((r.html.match(/class="product-card"/g)||[]).length,1);
 r=await buyer('index.php?q=PASSION&size=XS');assert.equal((r.html.match(/class="product-card"/g)||[]).length,0);
 r=await buyer('index.php?q=%3Cscript%3E');assert(!r.html.includes('value="<script>"'));assert.match(r.html,/No products match/);
 r=await owner('admin/index.php');assert.match(r.html,/STOCK ALERTS/);
 console.log('PASS: order lookup privacy, phone normalization, CSRF, rate limit, search and in-stock size filters.');
 const oid=query('SELECT id FROM orders LIMIT 1');
 r=await owner('admin/order.php?id='+oid);r=await owner('admin/order.php?id='+oid,{csrf:token(r.html),status:'cancelled',delivery_fee:'60',payment_status:'unpaid'});assert.equal(r.status,302);
 assert.equal(query(`SELECT stock FROM variants WHERE product_id=${passion} AND size='L'`),'3');
 r=await owner('admin/order.php?id='+oid);await owner('admin/order.php?id='+oid,{csrf:token(r.html),status:'cancelled',delivery_fee:'60',payment_status:'unpaid'});
 assert.equal(query(`SELECT stock FROM variants WHERE product_id=${passion} AND size='L'`),'3','Cancellation returns stock once');
 // Replace a product photo through the real admin form: it must persist in SQL.
 r=await owner('admin/products.php?id='+passion);
 const form=new FormData();for(const [k,v] of Object.entries({csrf:token(r.html),id:passion,name:'PASSION | True Oversized Hoodie',slug:'fu-passion',price:'1050',category:'Hoodies',description:'Test description',active:'1',size_guide:'Measurements in cm: L chest 60, length 72. <script>not HTML</script>','sizes[]':'L','stock[L]':'3'}))form.set(k,v);
 form.set('image',new Blob([readFileSync(resolve(root,'assets/logo.png'))],{type:'image/png'}),'photo.png');
 r=await owner('admin/products.php?id='+passion,form);assert.equal(r.status,302,r.html);
 r=await buyer('product.php?slug=fu-passion');assert.match(r.html,/Measurements in cm/);assert.match(r.html,/&lt;script&gt;/);assert(!r.html.includes('<script>not HTML'));assert.match(r.html,/More from Hoodies/);
 assert.equal(query(`SELECT value FROM settings WHERE name='size_guide_${passion}'`),'Measurements in cm: L chest 60, length 72. <script>not HTML</script>');
 const imagePath=query(`SELECT image FROM products WHERE id=${passion}`);assert.match(imagePath,/^image.php/);
 const image=await fetch(base+imagePath);assert.equal(image.headers.get('content-type'),'image/png');assert((await image.arrayBuffer()).byteLength>0);
 assert.equal(query('SELECT COUNT(*) FROM product_images'),'1');
 // Two different sessions race for the last LOST hoodie, using separate servers.
 const lost=query("SELECT id FROM products WHERE sku='FU-LOST'");
 async function prepare(c){let a=await c('product.php?slug=fu-lost');await c('cart.php',{csrf:token(a.html),action:'add',product_id:lost,size:'L',quantity:'1'});a=await c('checkout.php');return {...data,csrf:token(a.html),checkout_key:a.html.match(/name="checkout_key" value="([^"]+)"/)[1]};}
 const b1=client(),b2=client();const d1=await prepare(b1),d2=await prepare(b2);
 const race=await Promise.all([b1('checkout.php',d1,18095),b2('checkout.php',d2,18096)]);
 assert.equal(race.filter(x=>x.status===302).length,1,'Last unit can be sold only once');
 assert.equal(query(`SELECT stock FROM variants WHERE product_id=${lost} AND size='L'`),'0');
 const cron=await fetch(base+'cron/notifications',{headers:{Authorization:'Bearer '+env.CRON_SECRET}});assert.equal(cron.status,200);assert.equal((await cron.json()).status,'not_configured');

 assert.equal(query('SELECT COUNT(*) FROM products'),'12');
 assert.equal(query('SELECT COUNT(*) FROM admins'),'1');

 // Reorder, remove, archive and restore without deleting order history.
 let editor=await owner('admin/products.php?id='+living);
 assert.match(editor.html,/data-photo-editor/);
 const originalPhotos=[...editor.html.matchAll(/name="gallery_order\[\]" value="([^"]+)"/g)].map(m=>m[1].replaceAll('&amp;','&'));
 assert(originalPhotos.length>=2);
 const save=new FormData();for(const [k,v] of Object.entries({csrf:token(editor.html),id:living,name:'LIVING | T-Shirt',slug:'living-tee',price:'500',category:'T-shirts',description:'Test gallery',active:'1',gallery_present:'1','sizes[]':'L','stock[L]':'0'}))save.set(k,v);
 save.append('gallery_order[]',originalPhotos[1]);save.append('gallery_order[]',originalPhotos[0]);
 assert.equal((await owner('admin/products.php?id='+living,save)).status,302);
 assert.equal(query(`SELECT image FROM products WHERE id=${living}`),originalPhotos[1]);
 assert.equal(JSON.parse(query(`SELECT source_images FROM products WHERE id=${living}`)).length,2,'Removed gallery photos stay removed');
 let detail=await owner('product.php?slug=living-tee');assert.match(detail.html,/view 1/);
 editor=await owner('admin/products.php?id='+living);
 const beforeOrders=query('SELECT COUNT(*) FROM order_items');
 assert.equal((await owner('admin/products.php',{csrf:token(editor.html),id:living,action:'archive'})).status,302);
 assert.equal((await buyer('product.php?slug=living-tee')).status,404);
 assert.equal(query('SELECT COUNT(*) FROM order_items'),beforeOrders);
 editor=await owner('admin/products.php?id='+living);
 assert.equal((await owner('admin/products.php',{csrf:token(editor.html),id:living,action:'restore'})).status,302);
 assert.equal((await buyer('product.php?slug=living-tee')).status,200);
 console.log('PASS: existing photos, reordered cover/hover, removed gallery photos, archive/restore preserving orders.');
 const deviceA=client(),deviceB=client();
 for(const device of [deviceA,deviceB]){
  let login=await device('admin/login.php');
  assert.match(login.html,/Show password/);assert.match(login.html,/stay signed in automatically/);
  login=await device('admin/login.php',{csrf:token(login.html),email:'test-owner@example.test',password:'Only-Test-Password-42'});
  assert.equal(login.status,302);assert(device.cookies.has('flawed_admin_remember'));
  assert(login.setCookies.some(c=>c.startsWith('flawed_admin_remember=')&&/HttpOnly/i.test(c)&&/SameSite=Lax/i.test(c)&&/expires=/i.test(c)));
  device.cookies.delete('flawed_session');
  assert.equal((await device('admin/index.php',undefined,18096)).status,200,'Remembered browser survives new session/instance');
 }
 assert.equal(query('SELECT COUNT(*) FROM admin_remember_tokens WHERE admin_id=1'),'3','Owner and two independent remembered devices');
 query('UPDATE admin_remember_tokens SET expires_at=DATE_ADD(NOW(),INTERVAL 1 DAY)');
 await deviceB('admin/index.php');
 assert.equal(query('SELECT COUNT(*) FROM admin_remember_tokens WHERE expires_at>DATE_ADD(NOW(),INTERVAL 300 DAY)'),'1','Active admin visits renew remembered access');
 deviceB.cookies.delete('flawed_admin_remember');await deviceB('admin/index.php');
 assert(deviceB.cookies.get('flawed_admin_remember'),'Existing signed-in browser gets remembered automatically');
 deviceB.cookies.delete('flawed_session');assert.equal((await deviceB('admin/index.php')).status,200);
 const savedA=deviceA.cookies.get('flawed_admin_remember');
 let dashboard=await deviceA('admin/index.php');
 await deviceA('admin/logout.php',{csrf:token(dashboard.html)});
 deviceA.cookies.set('flawed_admin_remember',savedA);deviceA.cookies.delete('flawed_session');
 assert.equal((await deviceA('admin/index.php')).status,302,'Logged-out token cannot restore access');
 deviceB.cookies.delete('flawed_session');assert.equal((await deviceB('admin/index.php')).status,200,'Other device remains signed in');
 const validB=deviceB.cookies.get('flawed_admin_remember');
 deviceB.cookies.set('flawed_admin_remember',validB.slice(0,33)+'0'.repeat(64));deviceB.cookies.delete('flawed_session');
 assert.equal((await deviceB('admin/index.php')).status,302,'Forged token rejected');
 deviceB.cookies.set('flawed_admin_remember',validB);query('UPDATE admin_remember_tokens SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY)');deviceB.cookies.delete('flawed_session');
 assert.equal((await deviceB('admin/index.php')).status,302,'Expired token rejected');

 const drawer=client();const jsonHeaders={Accept:'application/json'};
 let bag=await drawer('cart.php',undefined,18095,jsonHeaders);assert.equal(bag.status,200);assert.equal(JSON.parse(bag.html).count,0);
 const bagToken=JSON.parse(bag.html).html.match(/data-bag-csrf value="([^"]+)"/)[1];
 query(`UPDATE variants SET stock=2 WHERE product_id=${passion} AND size='L'`);
 bag=await drawer('cart.php',{action:'add',csrf:bagToken,product_id:passion,size:'L',quantity:'1'},18095,jsonHeaders);
 assert.equal(bag.status,200);assert.equal(JSON.parse(bag.html).count,1);assert.match(JSON.parse(bag.html).html,/Added to your bag/);
 bag=await drawer('cart.php',{action:'update',csrf:bagToken,['qty['+passion+':L]']:'3'},18096,jsonHeaders);
 assert.equal(bag.status,409);assert.equal(JSON.parse(bag.html).count,1,'Unavailable quantity must not change bag');
 bag=await drawer('cart.php',{action:'update',csrf:bagToken,['qty['+passion+':L]']:'2'},18096,jsonHeaders);
 assert.equal(JSON.parse(bag.html).count,2);
 bag=await drawer('cart.php',{action:'remove',csrf:'wrong',key:passion+':L'},18095,jsonHeaders);assert.equal(bag.status,403);
 bag=await drawer('cart.php',{action:'remove',csrf:bagToken,key:passion+':L'},18095,jsonHeaders);assert.equal(JSON.parse(bag.html).count,0);
 console.log('PASS: JSON bag add/update/remove, cross-instance session, unavailable stock and CSRF rejection.');
 console.log('PASS: remembered login across browser sessions/instances, multiple devices, cookie flags, logout revocation, forged/expired tokens.');
 console.log('PASS: setup, 12 products, 46 sizes, categories, protected source routes, cross-instance login/cart sessions, CSRF, sold-out rejection, server pricing, idempotent checkout, stock reservation/cancellation, SQL photo upload, concurrent last-unit checkout, cron authentication.');
 console.log('Isolated test database: '+database+'. No real emails or orders were sent.');
}catch(e){console.error(logs.slice(-3000));throw e}
finally{for(const s of servers)s.kill();}

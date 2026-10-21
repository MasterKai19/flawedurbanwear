/** Flawed Urbanwear: bound Google Sheet script. Deploy as owner, access Anyone.
 * Script Properties: SPREADSHEET_ID, WEBHOOK_SECRET (64 hex characters recommended).
 * The Sheet itself must remain private. The secret authorizes writes, never reads.
 */
const OWNER_EMAIL = 'urbanwearflawed@gmail.com';
const HEADERS = ['Order reference','Placed at','Customer','Phone','Email','Address','Area','Items','Subtotal BDT','Delivery BDT','Total BDT','Payment method','Payment status at receipt','Order status at receipt','Notes','Owner email sent','Customer email sent','Received at'];
function json_(data) { return ContentService.createTextOutput(JSON.stringify(data)).setMimeType(ContentService.MimeType.JSON); }
function doGet() { return json_({ok:false,error:'POST required'}); }
function safeCell_(value) { if(typeof value==='number')return value; const s=String(value==null?'':value); return (/^[\s]*[=+@-]/.test(s)||/^0\d+$/.test(s))?"'"+s:s; }
function validOrder_(o) {
  if(!o||!/^FL-[A-F0-9]{10}$/.test(o.reference)||!Array.isArray(o.items)||!o.items.length||o.items.length>100)return false;
  if(typeof o.customer_name!=='string'||o.customer_name.length>120||typeof o.email!=='string'||(o.email!==''&&!/^[^\s@,;]+@[^\s@,;]+\.[^\s@,;]+$/.test(o.email))||o.email.length>190)return false;
  if(typeof o.phone!=='string'||o.phone.length>20||typeof o.address!=='string'||o.address.length>1500||typeof o.notes!=='string'||o.notes.length>1000)return false;
  return o.items.every(i=>typeof i.product_name==='string'&&i.product_name.length<=140&&/^[A-Z0-9]{1,10}$/.test(i.size)&&Number.isInteger(Number(i.quantity))&&Number(i.quantity)>0&&Number(i.quantity)<=20&&Number.isInteger(Number(i.unit_price))&&Number(i.unit_price)>0);
}
function sheet_() {
  const id=PropertiesService.getScriptProperties().getProperty('SPREADSHEET_ID');
  if(!id)throw new Error('configuration');
  const book=SpreadsheetApp.openById(id);let sheet=book.getSheetByName('Flawed Orders');
  if(!sheet){sheet=book.insertSheet('Flawed Orders');sheet.appendRow(HEADERS);sheet.setFrozenRows(1);sheet.getRange(1,1,1,HEADERS.length).setFontWeight('bold').setBackground('#171716').setFontColor('#ffffff');sheet.setColumnWidth(6,280);sheet.setColumnWidth(8,320);}
  if(sheet.getRange(1,1).getValue()!==HEADERS[0])throw new Error('configuration');
  return sheet;
}
function authorizeAndPrepare() { sheet_(); MailApp.getRemainingDailyQuota(); }
function doPost(e) {
  let data;try{if(!e.postData||e.postData.contents.length>100000)return json_({ok:false,error:'invalid_order'});data=JSON.parse(e.postData.contents);}catch(_){return json_({ok:false,error:'invalid_order'});}
  const secret=PropertiesService.getScriptProperties().getProperty('WEBHOOK_SECRET');
  if(!secret||secret.length<32)return json_({ok:false,error:'configuration'});
  if(typeof data.secret!=='string'||data.secret!==secret)return json_({ok:false,error:'unauthorized'});
  if(data.event==='connection.test'){
    try{sheet_();MailApp.sendEmail({to:OWNER_EMAIL,subject:'Flawed Urbanwear — connection test',body:'Your store can connect to Google Sheets and send owner notifications. This is a test, not a customer order.',name:'Flawed Urbanwear'});return json_({ok:true,test_email_sent:true});}catch(_){return json_({ok:false,error:'mail_failed'});}
  }
  if(data.event!=='order.created'||!validOrder_(data.order)||data.event_id!==data.order.reference)return json_({ok:false,error:'invalid_order'});
  const o=data.order;const result={ok:false,event_id:o.reference,sheet_logged:false,owner_emailed:false,customer_emailed:false};
  const lock=LockService.getScriptLock();if(!lock.tryLock(15000))return json_(Object.assign(result,{error:'busy'}));
  try{
    const sheet=sheet_();let row=0;
    if(sheet.getLastRow()>1){const found=sheet.getRange(2,1,sheet.getLastRow()-1,1).createTextFinder(o.reference).matchEntireCell(true).useRegularExpression(false).findNext();if(found)row=found.getRow();}
    const items=o.items.map(i=>i.product_name+' / '+i.size+' × '+i.quantity+' — BDT '+Number(i.unit_price)*Number(i.quantity)).join('\n');
    if(!row){
      sheet.appendRow([o.reference,o.created_at,o.customer_name,o.phone,o.email,o.address,o.area,items,Number(o.subtotal),o.delivery_fee===null?'Pending':Number(o.delivery_fee),o.total===null?'Pending delivery':Number(o.total),o.payment_method,o.payment_status,o.status,o.notes,'',o.email?'':'SKIPPED — no email',new Date().toISOString()].map(safeCell_));row=sheet.getLastRow();SpreadsheetApp.flush();
    }
    result.sheet_logged=true;
    result.owner_emailed=String(sheet.getRange(row,16).getValue()).startsWith('SENT');
    result.customer_emailed=String(sheet.getRange(row,17).getValue()).startsWith('SENT');
    const amount='Subtotal: BDT '+o.subtotal+'\nDelivery: '+(o.delivery_fee===null?'To be confirmed':'BDT '+o.delivery_fee)+'\nTotal: '+(o.total===null?'Pending delivery fee':'BDT '+o.total);
    const needed=(result.owner_emailed?0:1)+(o.email&&!result.customer_emailed?1:0);
    if(MailApp.getRemainingDailyQuota()<needed)return json_(Object.assign(result,{error:'mail_quota'}));
    if(!result.owner_emailed){MailApp.sendEmail({to:OWNER_EMAIL,subject:'New Flawed order '+o.reference,body:'A new order request has been received.\n\nReference: '+o.reference+'\nCustomer: '+o.customer_name+'\nPhone: '+o.phone+'\nEmail: '+(o.email||'Not supplied')+'\nAddress: '+o.address+'\nArea: '+o.area+'\n\n'+items+'\n\n'+amount+'\nPayment: Cash on delivery — '+o.payment_status+'\nStatus: '+o.status+'\nNotes: '+o.notes+'\n\nOpen your store admin dashboard to manage the order.',name:'Flawed Urbanwear'});sheet.getRange(row,16).setValue('SENT '+new Date().toISOString());SpreadsheetApp.flush();result.owner_emailed=true;}
    if(o.email&&!result.customer_emailed){MailApp.sendEmail({to:o.email,replyTo:OWNER_EMAIL,subject:'We received your order — '+o.reference,body:'Hi '+o.customer_name+',\n\nThank you for choosing Flawed Urbanwear. We have received your order request.\nReference: '+o.reference+'\n\n'+items+'\n\n'+amount+'\nPayment: Cash on delivery. No online payment has been taken.\nOrder status: '+o.status+'\n\nOur team will confirm availability and delivery before dispatch. This email acknowledges your request; it is not a payment receipt or shipping confirmation.\n\nQuestions? Reply to this email or contact @flawedurbanwear.\n\nFlawed Urbanwear\nStreet culture. Your way.\nEst. 2023',name:'Flawed Urbanwear'});sheet.getRange(row,17).setValue('SENT '+new Date().toISOString());SpreadsheetApp.flush();result.customer_emailed=true;}
    result.ok=true;return json_(result);
  }catch(_){return json_(Object.assign(result,{error:'mail_failed'}));}finally{lock.releaseLock();}
}

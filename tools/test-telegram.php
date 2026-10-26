<?php
require dirname(__DIR__).'/server/app/notifications.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function setting($key){return null;}
function order_discount($id){return 0;}
$status='pending';$leased=false;
$order=['reference'=>'TEST-1','customer_name'=>'PRIVATE NAME','phone'=>'PRIVATE PHONE','email'=>'private@example.com','address'=>'PRIVATE ADDRESS','subtotal'=>500,'delivery_fee'=>null,'total'=>null,'payment_method'=>'cod','payment_status'=>'unpaid'];
$items=[['product_name'=>'Living','size'=>'L','quantity'=>2,'unit_price'=>500]];
function query($sql,$params=[]){
 global $status,$leased,$order,$items;
 $rows=1;$value=null;
 if(str_starts_with($sql,'UPDATE order_notifications SET status=\'sending\'')){$rows=(!$leased&&$status!=='sent')?1:0;if($rows)$leased=true;}
 if(str_contains($sql,"SET status='sent'")){$status='sent';$leased=false;}
 if(str_contains($sql,"SET status='failed'")){$status='failed';$leased=false;}
 if(str_starts_with($sql,'SELECT reference'))$value=$order;
 if(str_starts_with($sql,'SELECT product_name'))$value=$items;
 return new class($rows,$value){function __construct(private $rows,private $value){}function rowCount(){return $this->rows;}function fetch(){return $this->value;}function fetchAll(){return $this->value;}};
}
putenv('TELEGRAM_BOT_TOKEN');putenv('TELEGRAM_CHAT_ID');
check(!notifications_ready(),'disabled without credentials');
putenv('TELEGRAM_BOT_TOKEN=123456:'.str_repeat('a',35));putenv('TELEGRAM_CHAT_ID=12345');
check(notifications_ready(),'configured Telegram needs no Google');
$text=telegram_order_text(notification_payload(1));
check(str_contains($text,'Living / L')&&str_contains($text,'Pending delivery fee'),'items and pending totals');
check(str_contains($text,'Name: PRIVATE NAME')&&str_contains($text,'Phone: PRIVATE PHONE')&&str_contains($text,'Address: PRIVATE ADDRESS')&&str_contains($text,'private@example.com'),'authorized customer contact details');
$long=notification_payload(1);$long['order']['notes']=str_repeat('বাংলা ',2000);$parts=telegram_order_messages($long);check(count($parts)>1,'long orders split');foreach($parts as $part)check(mb_strlen($part,'UTF-8')<2000,'bounded Telegram messages');
check(!notification_send(1,fn()=>['ok'=>false])&&$status==='failed','failure is retryable');
check(notification_send(1,fn()=>['ok'=>true])&&$status==='sent','successful retry');
check(!notification_send(1,function(){throw new Exception('duplicate');}),'sent orders not resent');
echo "PASS: Telegram readiness, customer contact details, item summary, failure, retry and sent-job protection.\n";

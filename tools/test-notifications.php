<?php
define('FLAWED_NO_SESSION',true);require dirname(__DIR__).'/server/app/bootstrap.php';require dirname(__DIR__).'/server/app/notifications.php';
function check($condition,$label){if(!$condition)throw new RuntimeException('FAILED: '.$label);}
check(notifications_installed(),'migration');check((int)query('SELECT COUNT(*) FROM order_notifications')->fetchColumn()===2,'checkout queued once per order');
check(!notifications_ready(),'disabled initially');$called=0;check(!notification_send(1,function()use(&$called){$called++;return [];}),'disabled send');check($called===0,'disabled has no outbound call');
foreach(['google_webhook_url'=>'https://script.google.com/macros/s/TEST/exec','google_secret'=>str_repeat('a',64),'google_enabled'=>'1'] as $k=>$v)query('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]);
check(!valid_google_url('https://localhost/exec'),'ssrf URL blocked');check(!valid_google_url('https://script.google.com.evil.test/macros/s/TEST/exec'),'lookalike blocked');
$ok=notification_send(1,function($p){check(!isset($p['order']['access_token']),'no order bearer token exported');return ['event_id'=>$p['event_id'],'ok'=>false,'sheet_logged'=>true,'owner_emailed'=>true,'customer_emailed'=>false,'error'=>'mail_quota'];});check(!$ok,'partial failure');
$row=query('SELECT * FROM order_notifications WHERE order_id=1')->fetch();check($row['status']==='failed'&&$row['sheet_logged']&&$row['owner_emailed']&&!$row['customer_emailed'],'partial states persisted');check((int)query('SELECT COUNT(*) FROM orders')->fetchColumn()===2,'orders preserved');
check(!notification_send(1,function(){throw new RuntimeException('must not call during backoff');}),'retry backoff');query('UPDATE order_notifications SET next_attempt_at=NULL WHERE order_id=1');
check(notification_send(1,fn($p)=>['event_id'=>$p['event_id'],'ok'=>true,'sheet_logged'=>true,'owner_emailed'=>true,'customer_emailed'=>true]),'retry success');check(!notification_send(1,fn()=>[]),'sent not resent');
echo "PASS: SQL outbox, disabled mode, URL checks, partial delivery states, order preservation, backoff, successful retry and sent-job deduplication.\n";

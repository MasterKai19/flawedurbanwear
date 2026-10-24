<?php require __DIR__.'/app/bootstrap.php';$result=null;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$ip=(string)(getenv('VERCEL')?($_SERVER['HTTP_X_VERCEL_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'unknown'):($_SERVER['REMOTE_ADDR']??'unknown'));$key=hash('sha256','tracking:'.$ip);
 query('INSERT INTO login_attempts(ip_hash,attempts,last_attempt) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt < DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),last_attempt=NOW()',[$key]);
 $attempts=(int)query('SELECT attempts FROM login_attempts WHERE ip_hash=?',[$key])->fetchColumn();
 if($attempts>10){http_response_code(429);$error='Too many lookups. Please wait 15 minutes before trying again.';}
 else{$reference=strtoupper(substr(trim((string)($_POST['reference']??'')),0,80));$phone=preg_replace('/[^0-9]/','',substr((string)($_POST['phone']??''),0,40));
 $row=query('SELECT reference,phone,status,created_at FROM orders WHERE reference=?',[$reference])->fetch();
 $normal=fn($s)=>preg_replace('/^880/','0',preg_replace('/[^0-9]/','',$s));
 if($row&&strlen($phone)>=10&&hash_equals($normal($row['phone']),$normal($phone)))$result=$row;else $error='We could not match those details. Check the order reference and the phone number used at checkout.';
 }
}
render_header('Track your order'); ?>
<main class="page narrow"><p class="eyebrow">YOUR ORDER JOURNEY</p><h1>Track your order.</h1><p>Enter your order reference and the phone number you used at checkout. No account needed.</p><form method="post"><?=csrf_input()?><label>Order reference<input name="reference" maxlength="80" required autocomplete="off"></label><label>Order phone number<input type="tel" name="phone" maxlength="40" required autocomplete="tel"></label><button class="button dark">CHECK STATUS</button></form><?php if($error):?><p class="notice" role="alert"><?=h($error)?></p><?php endif ?><?php if($result):?><section class="tracking-result" aria-live="polite"><h2><?=h($result['reference'])?></h2><p>Current status: <strong><?=h(ucfirst($result['status']))?></strong></p><p>Placed <?=h($result['created_at'])?> UTC</p><p>This status is updated by our store team. For courier details, contact us with your order reference.</p></section><?php endif ?><p><a href="<?=url('info.php?page=contact')?>">Need help? Contact Flawed Urbanwear</a></p></main><?php render_footer(); ?>

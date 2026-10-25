<?php
function coupon_quote(string $code,int $subtotal,bool $lock=false):array {
 $code=strtoupper(trim($code));if($code==='')return ['amount'=>0,'code'=>'','id'=>null];
 if(!preg_match('/^[A-Z0-9-]{3,40}$/D',$code))throw new DomainException('That discount code is not available.');
 $c=query('SELECT *,expires_at>NOW() AS valid_date FROM coupons WHERE code=?'.($lock?' FOR UPDATE':''),[$code])->fetch();
 if(!$c||!$c['active']||!$c['valid_date']||(int)$c['uses']>=(int)$c['max_uses'])throw new DomainException('That discount code is expired or unavailable.');
 if($subtotal<(int)$c['min_spend'])throw new DomainException('This code needs a product subtotal of '.money($c['min_spend']).'.');
 $amount=$c['kind']==='percent'?intdiv($subtotal*(int)$c['amount'],100):(int)$c['amount'];
 return ['amount'=>min($subtotal,$amount),'code'=>$c['code'],'id'=>(int)$c['id']];
}
function order_discount(int $id):int {return (int)(query('SELECT amount FROM order_discounts WHERE order_id=?',[$id])->fetchColumn()?:0);}
function submission_limit(string $purpose,int $limit=10):void {
 $ip=(string)(getenv('VERCEL')?($_SERVER['HTTP_X_VERCEL_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'unknown'):($_SERVER['REMOTE_ADDR']??'unknown'));$key=hash('sha256',$purpose.':'.$ip);
 query('INSERT INTO login_attempts(ip_hash,attempts,last_attempt) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),last_attempt=NOW()',[$key]);
 if((int)query('SELECT attempts FROM login_attempts WHERE ip_hash=?',[$key])->fetchColumn()>$limit)throw new DomainException('Too many requests. Please try again in 15 minutes.');
}

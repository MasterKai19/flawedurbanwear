<?php
declare(strict_types=1);
const FLAWED_OWNER_EMAIL = 'urbanwearflawed@gmail.com';
function notifications_installed(): bool {
    try { query('SELECT id FROM order_notifications LIMIT 1'); return true; }
    catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
}
function notifications_ready(): bool {
    if(telegram_ready())return true;
    return setting('google_enabled')==='1' && valid_google_url((string)setting('google_webhook_url')) && strlen((string)setting('google_secret'))>=32;
}
function telegram_ready(): bool {
    return (bool)preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/D',(string)getenv('TELEGRAM_BOT_TOKEN'))
        && (bool)preg_match('/^-?\d+$/D',(string)getenv('TELEGRAM_CHAT_ID'));
}
function telegram_recent_chats(): array {
    $token=(string)getenv('TELEGRAM_BOT_TOKEN');
    if(!preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/D',$token))throw new RuntimeException('Save the bot token in Vercel and redeploy first.');
    $ch=curl_init('https://api.telegram.org/bot'.$token.'/getUpdates');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['limit'=>100,'timeout'=>0])]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $result=is_string($body)?json_decode($body,true):null;
    if($status!==200||($result['ok']??false)!==true)throw new RuntimeException('Could not look up Telegram chats. Check the token and ensure this bot has no other webhook integration.');
    $chats=[];
    foreach($result['result']??[] as $update){
        $chat=$update['message']['chat']??[];
        if(($chat['type']??'')!=='private')continue;
        $id=(string)($chat['id']??'');if(!preg_match('/^\d+$/D',$id))continue;
        $chats[$id]=['id'=>$id,'name'=>trim(($chat['first_name']??'').' '.($chat['last_name']??'')),'username'=>$chat['username']??''];
    }
    return array_values($chats);
}
function telegram_order_text(array $payload): string {
    $o=$payload['order'];
    $lines=['Flawed Urbanwear — new order', 'Reference: '.$payload['event_id']];
    foreach($o['items'] as $item)$lines[]=$item['product_name'].' / '.$item['size'].' × '.$item['quantity'];
    $lines[]='Subtotal: BDT '.$o['subtotal'];
    if(!empty($o['discount']))$lines[]='Discount: BDT '.$o['discount'];
    $lines[]='Delivery: '.($o['delivery_fee']===null?'To be confirmed':'BDT '.$o['delivery_fee']);
    $lines[]='Total: '.($o['total']===null?'Pending delivery fee':'BDT '.$o['total']);
    $lines[]='Payment: '.$o['payment_method'].' / '.$o['payment_status'];
    // Keep customer contact details and private order access tokens in the admin dashboard.
    $lines[]='View order: https://flawedurbanwear.vercel.app/admin/index.php';
    $text=implode("\n",$lines);
    return mb_strlen($text,'UTF-8')>4000?mb_substr($text,0,3800,'UTF-8')."\nMore items in the admin dashboard.":$text;
}
function telegram_request(string $text): void {
    if(!telegram_ready())throw new RuntimeException('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in Vercel and redeploy.');
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required.');
    $ch=curl_init('https://api.telegram.org/bot'.getenv('TELEGRAM_BOT_TOKEN').'/sendMessage');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['chat_id'=>(string)getenv('TELEGRAM_CHAT_ID'),'text'=>$text,'link_preview_options'=>['is_disabled'=>true]],JSON_THROW_ON_ERROR)]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    // Never expose the bot-token URL or raw transport errors in the dashboard/logs.
    $result=is_string($body)?json_decode($body,true):null;
    if($status!==200||($result['ok']??false)!==true)throw new RuntimeException('Telegram delivery failed. Check the bot token, chat ID and that you pressed Start in your bot. Retry later.');
}
function valid_google_url(string $url): bool {
    return (bool)preg_match('~^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$~D',$url);
}
function notification_enqueue(int $orderId): void {
    // Existing installations remain usable until the admin applies the migration.
    if(!notifications_installed())return;
    query('INSERT IGNORE INTO order_notifications(order_id) VALUES(?)',[$orderId]);
}
function notification_payload(int $orderId): array {
    $o=query('SELECT reference,customer_name,phone,email,address,area,notes,subtotal,delivery_fee,total,status,payment_status,payment_method,created_at FROM orders WHERE id=?',[$orderId])->fetch();
    if(!$o)throw new RuntimeException('Order is unavailable.');
    $o['discount']=order_discount($orderId);
    $o['items']=query('SELECT product_name,size,unit_price,quantity FROM order_items WHERE order_id=? ORDER BY id',[$orderId])->fetchAll();
    // No private order bearer token, database password, or admin session is sent.
    return ['version'=>1,'event'=>'order.created','event_id'=>$o['reference'],'order'=>$o];
}
function google_request(array $payload): array {
    $url=(string)setting('google_webhook_url');if(!valid_google_url($url))throw new RuntimeException('Set a valid Google Apps Script deployment URL.');
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required.');
    $body=json_encode(['secret'=>(string)setting('google_secret')]+$payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $post=true;
    for($i=0;$i<3;$i++){
        $parts=parse_url($url);if(($parts['scheme']??'')!=='https'||!in_array($parts['host']??'',['script.google.com','script.googleusercontent.com'],true)||isset($parts['user'])||isset($parts['pass'])||isset($parts['port']))throw new RuntimeException('Google returned an unexpected redirect.');
        $headers=[];$responseBody='';$ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);},CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$responseBody){if(strlen($responseBody)+strlen($chunk)>65536)return 0;$responseBody.=$chunk;return strlen($chunk);}]);
        if($post)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
        $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);curl_close($ch);
        if($ok===false)throw new RuntimeException($errno===60?'TLS certificate verification failed. Configure PHP curl.cainfo with a trusted CA bundle.':'Google connection failed or timed out. Retry from Notifications.');
        if(in_array($code,[301,302,303],true)&&isset($headers['location'])){$url=$headers['location'];$post=false;continue;}
        if($code!==200)throw new RuntimeException('Google returned HTTP '.$code.'. Check deployment access.');
        try{$result=json_decode($responseBody,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('Google returned a sign-in page or invalid response. Deploy as owner with access for Anyone.');}
        if(!is_array($result))throw new RuntimeException('Invalid Google response.');return $result;
    }
    throw new RuntimeException('Too many Google redirects.');
}
function notification_send(int $orderId, ?callable $transport=null): bool {
    if(!notifications_installed()||!notifications_ready())return false;
    $claim=query("UPDATE order_notifications SET status='sending',lease_until=DATE_ADD(NOW(),INTERVAL 3 MINUTE),attempts=attempts+1 WHERE order_id=? AND status<>'sent' AND (lease_until IS NULL OR lease_until<NOW()) AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempts<10",[$orderId]);
    if(!$claim->rowCount())return false;
    try{
        if(telegram_ready()){
            $payload=notification_payload($orderId);
            if($transport){$result=$transport($payload);if(($result['ok']??false)!==true)throw new RuntimeException('Telegram delivery failed.');}
            else telegram_request(telegram_order_text($payload));
            query("UPDATE order_notifications SET status='sent',sent_at=NOW(),lease_until=NULL,next_attempt_at=NULL,last_error=NULL WHERE order_id=?",[$orderId]);
            return true;
        }
        $payload=notification_payload($orderId);$result=$transport?$transport($payload):google_request($payload);
        if(($result['event_id']??null)!==$payload['event_id'])throw new RuntimeException('Google did not acknowledge this order.');
        $sheet=($result['sheet_logged']??false)===true;$owner=($result['owner_emailed']??false)===true;
        $customer=($result['customer_emailed']??false)===true;
        query('UPDATE order_notifications SET sheet_logged=?,owner_emailed=?,customer_emailed=? WHERE order_id=?',[$sheet?1:0,$owner?1:0,$customer?1:0,$orderId]);
        if(($result['ok']??false)!==true||!$sheet||!$owner||($payload['order']['email']!==''&&!$customer)){
            $codes=['mail_quota'=>'Google email quota reached. Retry after the quota resets.','mail_failed'=>'An email could not be sent. Check Google script permissions and retry.','unauthorized'=>'Shared secret does not match.','busy'=>'Google is busy. Retry shortly.','invalid_order'=>'Google rejected the order format.','configuration'=>'Set SPREADSHEET_ID and WEBHOOK_SECRET in Script Properties.'];
            throw new RuntimeException($codes[$result['error']??'']??'Google did not complete all notifications. Check the Sheet and retry.');
        }
        query("UPDATE order_notifications SET status='sent',sent_at=NOW(),lease_until=NULL,next_attempt_at=NULL,last_error=NULL WHERE order_id=?",[$orderId]);return true;
    }catch(Throwable $e){
        query("UPDATE order_notifications SET status='failed',lease_until=NULL,next_attempt_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE),last_error=? WHERE order_id=?",[substr($e instanceof RuntimeException?$e->getMessage():'Notification failed. Check the integration configuration.',0,255),$orderId]);return false;
    }
}
function notification_try(int $orderId): void {
    // Delivery is best effort AFTER the order transaction commits. Never erase an order.
    try{notification_send($orderId);}catch(Throwable $e){error_log('Flawed notification dispatch failed; order preserved.');}
}

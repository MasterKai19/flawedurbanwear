<?php
declare(strict_types=1);
// No filesystem fallback: server source, credentials, SQL and docs are never public.
$route=(string)($_GET['__route']??parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH));
$route=trim(rawurldecode($route),'/');unset($_GET['__route']);
if(PHP_SAPI==='cli-server'&&preg_match('~^assets/[a-zA-Z0-9_./-]+\.(css|js|png|jpg|jpeg|webp|svg|ico|woff2)$~',$route)){
    $root=realpath(dirname(__DIR__).'/assets');$file=realpath(dirname(__DIR__).'/'.$route);
    if($file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)){
        $types=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','ico'=>'image/x-icon','woff2'=>'font/woff2'];
        header('Content-Type: '.$types[pathinfo($file,PATHINFO_EXTENSION)]);readfile($file);exit;
    }
}
$routes=[''=>'index.php','index.php'=>'index.php','product.php'=>'product.php','cart.php'=>'cart.php','checkout.php'=>'checkout.php','order.php'=>'order.php','track.php'=>'track.php','info.php'=>'info.php','setup.php'=>'setup.php','image.php'=>'image.php','cron/notifications'=>'cron.php','admin'=>'admin/index.php'];
foreach(['index','login','logout','order','products','settings','notifications','export'] as $name)$routes['admin/'.$name.'.php']='admin/'.$name.'.php';
if(!isset($routes[$route])){http_response_code(404);header('Content-Type: text/plain');echo 'Page not found.';exit;}
// Browsing without a cart needs no database-backed session, locks or cookie.
// Product forms, carts, checkout and all admin routes retain full sessions/CSRF.
if($route==='image.php'||(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&in_array($route,['','index.php','info.php'],true)&&empty($_COOKIE['flawed_session'])))define('FLAWED_NO_SESSION',true);
if($route==='cron/notifications'){
    $secret=(string)getenv('CRON_SECRET');
    if(strlen($secret)<32||!hash_equals('Bearer '.$secret,(string)($_SERVER['HTTP_AUTHORIZATION']??''))){http_response_code(401);exit('Unauthorized');}
    define('FLAWED_NO_SESSION',true);
}
try{require dirname(__DIR__).'/server/'.$routes[$route];}
catch(Throwable $e){
    error_log('Flawed request failed: '.get_class($e).' '.$e->getCode());
    http_response_code(503);header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><title>Flawed Urbanwear</title><h1>Flawed Urbanwear</h1><p>The store is not ready yet. The owner needs to connect the cloud MySQL database and set OWNER_EMAIL and OWNER_PASSWORD in Vercel, then redeploy. The store initializes automatically.</p>';
}

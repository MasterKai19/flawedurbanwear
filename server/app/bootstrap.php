<?php
declare(strict_types=1);
$configFile = __DIR__.'/config.php';
$config = require is_file($configFile) ? $configFile : __DIR__.'/config.example.php';
require_once __DIR__.'/cloud.php';
require_once __DIR__.'/initialize.php';
initialize_store();
$https = (bool)getenv('VERCEL') || !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode','1');
session_name('flawed_session');
session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Lax','path'=>rtrim($config['base_path'],'/').'/']);
if(!defined('FLAWED_NO_SESSION')){session_set_save_handler(new CloudSessions(),true);session_start();}
require_once __DIR__.'/remember.php';
require_once __DIR__.'/commerce.php';
if(session_status()===PHP_SESSION_ACTIVE && (($route??'')==='admin'||str_starts_with($route??'','admin/')))admin_restore_device();
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $s=''): string { global $config; return rtrim($config['base_path'],'/').'/'.ltrim($s,'/'); }
function redirect(string $s): never { header('Location: '.url($s)); exit; }
function db(): PDO {return cloud_db();}
function query(string $sql,array $params=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($params); return $q; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
function check_csrf(): void { if(!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(403); exit('Your session expired. Refresh the page and try again.'); } }
function money($n): string { return '৳'.number_format((int)$n); }
function delivery_estimate_default(string $area):string {return $area==='dhaka'?'1–2 days':'2–3 days';}
function setting(string $key): ?string { $row=query('SELECT value FROM settings WHERE name=?',[$key])->fetch();$value=$row?$row['value']:null;if(($value===null||$value==='')&&in_array($key,['delivery_estimate_dhaka','delivery_estimate_outside'],true))return delivery_estimate_default(substr($key,18));return $value; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function require_admin(): void { if(empty($_SESSION['admin_id'])) redirect('admin/login.php'); }
function sizes(): array { return ['XS','S','M','L','XL','XXL','3XL']; }
function cart_items(bool $lock=false): array {
    $items=[];
    foreach(($_SESSION['cart']??[]) as $key=>$qty){
        if(!preg_match('/^(\d+):([A-Z0-9]+)$/',(string)$key,$m)) continue;
        $p=query('SELECT p.*,v.size,v.stock FROM products p JOIN variants v ON v.product_id=p.id WHERE p.id=? AND v.size=? AND p.active=1'.($lock?' FOR UPDATE':''),[(int)$m[1],$m[2]])->fetch();
        if($p){ $p['qty']=min(20,max(1,(int)$qty));$p['key']=$key;$items[]=$p; }
    } return $items;
}
function cart_subtotal(array $items): int {return array_sum(array_map(fn($p)=>(int)$p['price']*$p['qty'],$items));}
function render_header(string $title='Flawed Urbanwear',bool $admin=false): void {
    $count=array_sum($_SESSION['cart']??[]);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' — Flawed Urbanwear</title><meta name="description" content="Flawed Urbanwear. Independent street culture. Est. 2023. Shop clothing online.">'.(!empty($GLOBALS['storefront_theme'])?'<link rel="preload" as="image" href="'.url('assets/hero-fast.webp').'" fetchpriority="high">':'').'<link rel="stylesheet" href="'.url('assets/style.css').'"><link rel="stylesheet" href="'.url('assets/store.css?v='.filemtime(dirname(__DIR__,2).'/assets/store.css')).'"></head><body class="' . (!empty($GLOBALS['storefront_theme']) ? 'storefront-theme' : '') . '"><header><a href="'.url().'" class="brand"><img src="'.url('assets/logo.png').'" alt="Flawed logo"><span>Flawed<small>Urbanwear</small></span></a><nav aria-label="Main navigation">';
    echo $admin?'<a href="'.url('admin/index.php').'">ORDERS</a><a href="'.url('admin/products.php').'">PRODUCTS</a><a href="'.url('admin/sales.php').'">SALES</a><a href="'.url('admin/settings.php').'">SETTINGS</a>':'<a href="'.url('index.php#shop').'">SHOP</a><a href="'.url('index.php#story').'">OUR WORLD</a>';
    echo '</nav>';
    if($admin)echo '<form method="post" action="'.url('admin/logout.php').'">'.csrf_input().'<button class="text-button">LOG OUT</button></form>';
    else echo '<a data-open-bag class="bag-button" href="'.url('cart.php').'">BAG <span>'.(int)$count.'</span></a>';
    echo '</header>';
    if(isset($_SESSION['flash'])){echo '<div class="notice" role="status">'.h($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}
}
function render_footer(): void { ?>
<dialog id="bag-drawer" aria-labelledby="drawer-title" data-cart-url="<?=url('cart.php')?>"><div data-bag-content><div class="bag-head"><h2 id="drawer-title">Your bag</h2><button type="button" data-close-bag aria-label="Close shopping bag">×</button></div><p role="status">Loading your bag…</p></div></dialog>
<footer class="site-footer"><a class="footer-brand" href="<?=url()?>"><img src="<?=url('assets/logo.png')?>" alt="Flawed logo"><span>Flawed<br><em>Urbanwear</em></span></a><div class="footer-columns"><section><h2>Pages</h2><a href="<?=url()?>">Home</a><a href="<?=url('info.php?page=about')?>">About Us</a><a href="<?=url('index.php#shop')?>">Shop</a></section><section><h2>Help</h2><a href="<?=url('track.php')?>">Track Your Order</a><a href="<?=url('info.php')?>">Help & Delivery</a><a href="<?=url('info.php?page=privacy')?>">Privacy Policy</a><a href="<?=url('info.php?page=returns')?>">Refund, Trial & Exchange Policy</a><a href="<?=url('info.php?page=contact')?>">Contact Us</a><a href="<?=url('info.php?page=terms')?>">Terms & Conditions</a></section><section><h2>Contact Information</h2><p>Online only · Est. 2023</p><a href="https://www.instagram.com/flawedurbanwear/" target="_blank" rel="noopener">Instagram · @flawedurbanwear ↗</a><a href="https://www.facebook.com/flawedurbanwear/" target="_blank" rel="noopener">Facebook · Flawed Urbanwear ↗</a><a href="mailto:urbanwearflawed@gmail.com">urbanwearflawed@gmail.com</a></section></div><div class="footer-bottom"><p>© <?=date('Y')?> Flawed Urbanwear · Street culture. Your way.</p><a href="<?=url('admin/index.php')?>">Store admin</a></div></footer><script src="<?=url('assets/store.js?v='.filemtime(dirname(__DIR__,2).'/assets/store.js'))?>"></script></body></html>
<?php }
set_exception_handler(function(Throwable $e){error_log('Flawed application error: '.$e->getMessage());http_response_code(500);echo '<!doctype html><title>Flawed — setup needed</title><h1>Flawed Urbanwear</h1><p>The store is temporarily unavailable. If you are the owner, check the cloud database and owner environment variables in Vercel.</p>';});

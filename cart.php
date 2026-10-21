<?php require __DIR__.'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$action=$_POST['action']??'';
 if($action==='add'){
  $id=(int)($_POST['product_id']??0);$size=(string)($_POST['size']??'');$qty=filter_var($_POST['quantity']??1,FILTER_VALIDATE_INT);
  $p=query('SELECT p.id,v.stock FROM products p JOIN variants v ON p.id=v.product_id WHERE p.id=? AND v.size=? AND p.active=1',[$id,$size])->fetch();
  $key=$id.':'.$size;$total=(int)($_SESSION['cart'][$key]??0)+(int)$qty;
  if(!$p||!$qty||$qty<1||$total>20||($p['stock']!==null&&$total>(int)$p['stock']))flash('That size or quantity is unavailable. Please check your selection.');
  else{$_SESSION['cart'][$key]=$total;unset($_SESSION['checkout_key']);flash('Added to your bag.');if(($_POST['destination']??'')==='checkout')redirect('checkout.php');}
 }elseif($action==='update'){
  foreach(($_POST['qty']??[]) as $key=>$qty){if(!isset($_SESSION['cart'][$key]))continue;$n=filter_var($qty,FILTER_VALIDATE_INT);if($n===false||$n<0||$n>20){flash('Quantities must be between 0 and 20.');continue;}if($n===0)unset($_SESSION['cart'][$key]);else $_SESSION['cart'][$key]=$n;}unset($_SESSION['checkout_key']);
 }elseif($action==='remove'){unset($_SESSION['cart'][(string)($_POST['key']??'')],$_SESSION['checkout_key']);}
 redirect('cart.php');
}
$items=cart_items();render_header('Your bag'); ?>
<main class="page"><p class="eyebrow">YOUR SELECTION</p><h1>Your bag.</h1><?php if(!$items):?><div class="empty"><p>Your next everyday piece is waiting.</p><a class="button dark" href="<?=url('index.php#shop')?>">EXPLORE THE SHOP ↗</a></div><?php else:?>
<div class="checkout-layout"><section><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update"><?php foreach($items as $p):?><div class="bag-row"><img src="<?=url(h($p['image']))?>" alt="<?=h($p['name'])?>"><div><h3><?=h($p['name'])?></h3><p>Size <?=h($p['size'])?> · <?=money($p['price'])?> each</p><label>Quantity<input class="qty-input" name="qty[<?=h($p['key'])?>]" type="number" min="0" max="20" value="<?=$p['qty']?>" required></label><?php if($p['stock']!==null && $p['qty']>$p['stock']):?><p class="error">Only <?=h($p['stock'])?> available.</p><?php endif ?></div><strong><?=money($p['qty']*$p['price'])?></strong></div><?php endforeach ?><p class="hint">Set quantity to 0 to remove an item.</p><button class="button outline">UPDATE BAG</button></form></section><aside class="summary-box"><h2>Order summary</h2><div class="summary-line"><span>Subtotal</span><strong><?=money(cart_subtotal($items))?></strong></div><p>Delivery is calculated at checkout.</p><a class="button dark" href="<?=url('checkout.php')?>">CHECKOUT ↗</a><a class="back-link" href="<?=url('index.php#shop')?>">Continue shopping</a></aside></div><?php endif ?></main><?php render_footer(); ?>

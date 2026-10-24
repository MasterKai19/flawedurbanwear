<?php
function drawer_content(array $items,string $message=''):void { ?>
<div class="bag-head"><h2 id="drawer-title">Your bag <span><?=array_sum(array_column($items,'qty'))?></span></h2><button type="button" data-close-bag aria-label="Close shopping bag">×</button></div>
<p class="drawer-message" role="status"><?=h($message)?></p><input type="hidden" data-bag-csrf value="<?=h(csrf())?>">
<?php if(!$items):?><div class="empty"><p>Your bag is empty.</p><button class="button dark" type="button" data-close-bag>CONTINUE SHOPPING</button></div><?php else:?>
<div class="drawer-items"><?php foreach($items as $item):?><div class="drawer-item"><img src="<?=url(h(product_photo($item['image'],240)))?>" width="90" height="110" alt="<?=h($item['name'])?>"><div><a href="<?=url('product.php?slug='.rawurlencode($item['slug']))?>"><?=h($item['name'])?></a><p>Size <?=h($item['size'])?> · <?=money($item['price'])?> each</p><div class="drawer-quantity"><button type="button" data-bag-key="<?=h($item['key'])?>" data-bag-quantity="<?=$item['qty']-1?>" aria-label="Decrease quantity of <?=h($item['name'])?>">−</button><span><?=$item['qty']?></span><button type="button" data-bag-key="<?=h($item['key'])?>" data-bag-quantity="<?=$item['qty']+1?>" aria-label="Increase quantity of <?=h($item['name'])?>" <?=$item['qty']>=20||($item['stock']!==null&&$item['qty']>=$item['stock'])?'disabled':''?>>+</button><button type="button" data-bag-remove="<?=h($item['key'])?>">Remove</button></div><?php if($item['stock']!==null&&$item['qty']>$item['stock']):?><p class="error">Only <?=h($item['stock'])?> available.</p><?php endif ?></div><strong><?=money($item['qty']*$item['price'])?></strong></div><?php endforeach ?></div>
<div class="drawer-summary"><div class="subtotal"><span>Subtotal</span><strong><?=money(cart_subtotal($items))?></strong></div><p>Delivery is calculated or confirmed at checkout.</p><a class="button dark" href="<?=url('checkout.php')?>">CHECKOUT ↗</a><a class="button outline" href="<?=url('cart.php')?>">VIEW FULL BAG</a><button type="button" class="text-button" data-close-bag>Continue shopping</button></div>
<?php endif; }
function drawer_response(bool $ok=true,string $message=''):never {
    $items=cart_items();ob_start();drawer_content($items,$message);$html=ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');if(!$ok)http_response_code(409);
    echo json_encode(['ok'=>$ok,'html'=>$html,'count'=>array_sum(array_column($items,'qty'))],JSON_THROW_ON_ERROR);exit;
}

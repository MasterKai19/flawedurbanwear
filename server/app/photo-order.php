<?php
// Visually checked against the supplied catalogue photos.
function catalog_photo_pairs():array {
    return [
        'FU-FLAWS'=>['fu-falws-01.jpg','fu-falws-02.jpg'],
        'FU-FLAWED'=>['fu-flawed-06.jpg','fu-flawed-01.jpg'],
        'FU-GRATEFUL'=>['fu-grateful-03.jpg','fu-grateful-02.jpg'],
        'FU-JOURNEY'=>['fu-journey-01.jpg','fu-journey-02.jpg'],
        'FU-KLAMAR'=>['fu-klamar-02.jpg','fu-klamar-07.jpg'],
        'FU-LIVING'=>['fu-living-01.jpg','fu-living-02.jpg'],
        'FU-MEMORIES'=>['fu-memories-01.jpg','fu-memories-03.jpg'],
        'FU-MIRACLE'=>['fu-miracle-05.jpg','fu-miracle-02.jpg'],
    ];
}
function arrange_catalog_photos():void {
    foreach(catalog_photo_pairs() as $sku=>$pair){
        // Preserve any photos subsequently uploaded by the owner.
        query("UPDATE products SET image=?,back_image=? WHERE sku=? AND image LIKE 'assets/products/%' AND (back_image IS NULL OR back_image LIKE 'assets/products/%')",['assets/products/'.$pair[0],'assets/products/'.$pair[1],$sku]);
    }
    // These sets have no distinct front/back pair. Keep their gallery photos.
    query("UPDATE products SET back_image=NULL WHERE sku IN ('FU-PASSION','FU-LOST','FU-CARGO-BLUE','FU-CARGO-BROWN') AND image LIKE 'assets/products/%' AND back_image LIKE 'assets/products/%'");
}

function product_gallery(array $product):array {
    $photos=array_filter([$product['image']??null,$product['back_image']??null]);
    foreach(json_decode($product['source_images']??'[]',true)??[] as $source){
        if(preg_match('/^image\.php\?id=[a-f0-9]{64}$/D',$source))$photos[]=$source;
        else {$path='assets/products/'.basename($source);if(is_file(dirname(__DIR__,2).'/'.$path))$photos[]=$path;}
    }
    return array_values(array_unique($photos));
}
function product_hover_photo(array $product):?string {return product_gallery($product)[1]??null;}

// One-time removal preserves every existing real photo and its order.
function remove_catalog_mockups():void {
    if(setting('catalog_mockups_removed')==='1')return;
    foreach(query('SELECT * FROM products FOR UPDATE')->fetchAll() as $product){
        $old=product_gallery($product);
        $gallery=array_values(array_filter($old,fn($path)=>!str_contains($path,'-mockup-v2-')));
        if($gallery===$old)continue;
        if(!$gallery){
            $backup=json_decode(setting('mockup_backup_'.$product['id'])??'[]',true);
            $gallery=array_values(array_filter(is_array($backup)?$backup:[],fn($path)=>!str_contains($path,'-mockup-v2-')));
        }
        if(!$gallery)throw new RuntimeException('Original product photos missing.');
        query('UPDATE products SET image=?,back_image=?,source_images=? WHERE id=?',
            [$gallery[0],$gallery[1]??null,json_encode($gallery,JSON_UNESCAPED_SLASHES),$product['id']]);
    }
    query("INSERT INTO settings(name,value) VALUES('catalog_mockups_removed','1') ON DUPLICATE KEY UPDATE value='1'");
}

function product_photo(string $path,int $size=640):string {
    if(str_starts_with($path,'assets/products/')){
        $optimized='assets/optimized/'.pathinfo($path,PATHINFO_FILENAME).'-'.$size.'.webp';
        if(is_file(dirname(__DIR__,2).'/'.$optimized))return $optimized;
    }
    return $path;
}

function product_srcset(string $path): string {
    $sources=[];
    foreach([240,480,640,960,1280] as $width){
        $photo=product_photo($path,$width);
        if($photo!==$path){$dimensions=getimagesize(dirname(__DIR__,2).'/'.$photo);if($dimensions)$sources[$dimensions[0]]=url($photo).' '.$dimensions[0].'w';}
    }
    return implode(', ',$sources);
}
function product_fact(array $product,string $label): string {
    $description=(string)($product['description']??'');
    if(preg_match('/(?:^|\n)\s*(?:•\s*)?'.preg_quote($label,'/').':\s*([^\r\n]+)/iu',$description,$match))return trim($match[1]);
    return '';
}

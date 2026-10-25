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

// Apply this requested gallery change once; subsequent admin edits remain authoritative.
// Called inside initialize_store's transaction and installation lock.
function add_catalog_mockups():void {
    if(setting('catalog_mockups_v2')==='1')return;
    foreach(['LIVING','FLAWED','MEMORIES','GRATEFUL','MIRACLE','JOURNEY'] as $name){
        $product=query('SELECT * FROM products WHERE sku=? FOR UPDATE',['FU-'.$name])->fetch();
        if(!$product)continue;
        $prefix='assets/products/fu-'.strtolower($name).'-mockup-v2-';
        $front=$prefix.'front.webp';$back=$prefix.'back.webp';
        foreach([$front,$back] as $path){
            if(!is_file(dirname(__DIR__,2).'/'.$path))throw new RuntimeException('Missing mockup asset.');
        }
        $old=product_gallery($product);
        query('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=value',
            ['mockup_backup_'.$product['id'],json_encode($old)]);
        $gallery=array_values(array_unique(array_merge([$front,$back],$old)));
        query('UPDATE products SET image=?,back_image=?,source_images=? WHERE id=?',
            [$front,$back,json_encode($gallery,JSON_UNESCAPED_SLASHES),$product['id']]);
    }
    query("INSERT INTO settings(name,value) VALUES('catalog_mockups_v2','1') ON DUPLICATE KEY UPDATE value='1'");
}

function product_photo(string $path,int $size=640):string {
    if(str_starts_with($path,'assets/products/')){
        $optimized='assets/optimized/'.pathinfo($path,PATHINFO_FILENAME).'-'.$size.'.webp';
        if(is_file(dirname(__DIR__,2).'/'.$optimized))return $optimized;
    }
    return $path;
}

<?php
// CLI only: php import-catalog.php products.csv [--apply]
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/app/bootstrap.php';
try {
    $file=$argv[1]??'';
    if(!is_file($file))throw new RuntimeException('Supply the catalog CSV path.');
    $fh=fopen($file,'r');$headers=fgetcsv($fh,0,',','"','');$rows=[];
    while(($values=fgetcsv($fh,0,',','"',''))!==false){if($values===[null])continue;if(count($values)!==count($headers))throw new RuntimeException('Invalid CSV row.');$rows[]=array_combine($headers,$values);}fclose($fh);
    $catalog=[];$seen=[];
    foreach($rows as $r){
        if(empty($r['SKU'])||isset($seen[$r['SKU']]))throw new RuntimeException('Missing or duplicate SKU.');$seen[$r['SKU']]=true;
        if($r['Type']==='variable')$catalog[$r['SKU']]=['row'=>$r,'variants'=>[]];
        elseif($r['Type']!=='variation')throw new RuntimeException('Unsupported product type.');
    }
    foreach($rows as $r){if($r['Type']!=='variation')continue;
        $parent=$r['Parent'];$size=$r['Attribute 1 value(s)'];
        if(!isset($catalog[$parent])||!in_array($size,sizes(),true)||isset($catalog[$parent]['variants'][$size])||!ctype_digit($r['Stock'])||!ctype_digit($r['Regular price'])||(int)$r['Regular price']<1)throw new RuntimeException('Invalid variation '.$r['SKU']);
        if(((int)$r['Stock']>0)!==($r['In stock?']==='1'))throw new RuntimeException('Stock status mismatch '.$r['SKU']);
        $catalog[$parent]['variants'][$size]=['stock'=>(int)$r['Stock'],'price'=>(int)$r['Regular price']];
    }
    foreach($catalog as $sku=>$entry){
        $prices=array_unique(array_column($entry['variants'],'price'));
        $expected=array_map('trim',explode(',',$entry['row']['Attribute 1 value(s)']));
        if(count($prices)!==1||array_diff($expected,array_keys($entry['variants']))||array_diff(array_keys($entry['variants']),$expected))throw new RuntimeException('Prices or sizes need review: '.$sku);
        echo $sku.' | '.reset($prices).' BDT | '.array_sum(array_column($entry['variants'],'stock'))." available\n";
    }
    if(!in_array('--apply',$argv,true)){echo "Validated. Add --apply to import.\n";exit;}
    // Schema additions run before the data transaction (MySQL DDL commits implicitly).
    $columns=array_column(query('SHOW COLUMNS FROM products')->fetchAll(),'Field');
    foreach(['sku'=>'VARCHAR(80) NULL UNIQUE','category'=>"VARCHAR(100) NOT NULL DEFAULT ''",'short_description'=>'TEXT NULL','source_images'=>'TEXT NULL'] as $col=>$type){if(!in_array($col,$columns,true))db()->exec("ALTER TABLE products ADD COLUMN $col $type");}
    db()->beginTransaction();
    foreach($catalog as $sku=>$entry){
        $r=$entry['row'];$old=query('SELECT * FROM products WHERE sku=? FOR UPDATE',[$sku])->fetch();
        if(!$old&&$sku==='FU-LIVING')$old=query('SELECT * FROM products WHERE slug=? FOR UPDATE',['living-tee'])->fetch();
        $slug=$old['slug']??strtolower($sku);$images=array_map('trim',explode(',',$r['Images']));
        $available=array_values(array_filter(array_map(fn($f)=>'assets/products/'.basename($f),$images),fn($f)=>is_file(__DIR__.'/'.$f)));
        $image=$available[0]??($old['image']??'assets/product-placeholder.svg');$back=$available[1]??($old['back_image']??null);
        // Store readable plain text so imported HTML cannot execute in the shop or admin.
        $desc=preg_replace('~</(?:p|li|h3|tr)>~i',"\n",$r['Description']);
        $desc=preg_replace('~</(?:td|th)>~i',' / ',$desc);
        $desc=preg_replace('~<li>~i','• ',$desc);
        $desc=trim(html_entity_decode(strip_tags($desc),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $price=reset($entry['variants'])['price'];
        $data=[$slug,$r['Name'],$desc,$price,$image,$back,(int)$r['Published'],$sku,(['Cargo Pants'=>'Lowers','T-Shirts'=>'T-shirts'][$r['Categories']]??$r['Categories']),$r['Short description'],json_encode($images)];
        if($old){$id=(int)$old['id'];$data[]=$id;query('UPDATE products SET slug=?,name=?,description=?,price=?,image=?,back_image=?,active=?,sku=?,category=?,short_description=?,source_images=? WHERE id=?',$data);}
        else{query('INSERT INTO products(slug,name,description,price,image,back_image,active,sku,category,short_description,source_images) VALUES(?,?,?,?,?,?,?,?,?,?,?)',$data);$id=(int)db()->lastInsertId();}
        query('UPDATE variants SET stock=0 WHERE product_id=?',[$id]);
        foreach($entry['variants'] as $size=>$v)query('INSERT INTO variants(product_id,size,stock) VALUES(?,?,?) ON DUPLICATE KEY UPDATE stock=VALUES(stock)',[$id,$size,$v['stock']]);
        // No orders reference variant IDs; their product IDs and size snapshots stay intact.
        $marks=implode(',',array_fill(0,count($entry['variants']),'?'));
        query("DELETE FROM variants WHERE product_id=? AND size NOT IN ($marks)",array_merge([$id],array_keys($entry['variants'])));
    }
    db()->commit();echo 'Imported '.count($catalog)." products. Orders and admin accounts were not changed.\n";
}catch(Throwable $e){if(isset($config)&&db()->inTransaction())db()->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}

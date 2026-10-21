<?php
function seed_catalog():void {
    // First installation only. Redeployments never reset prices, inventory or orders.
    if((int)query('SELECT COUNT(*) FROM products')->fetchColumn()>0)return;
    $f=fopen(dirname(__DIR__).'/database/products.csv','r');$header=fgetcsv($f,0,',','"','');$rows=[];
    while(($row=fgetcsv($f,0,',','"',''))!==false){if($row===[null])continue;$rows[]=array_combine($header,$row);}fclose($f);
    foreach($rows as $r){
        if($r['Type']!=='variable')continue;
        $variants=array_values(array_filter($rows,fn($v)=>$v['Type']==='variation'&&$v['Parent']===$r['SKU']));
        if(!$variants)throw new RuntimeException('Missing seed variants.');
        $desc=preg_replace('~</(?:p|li|h3|tr)>~i',"\n",$r['Description']);
        $desc=preg_replace('~</(?:td|th)>~i',' / ',$desc);
        $desc=preg_replace('~<li>~i','• ',$desc);
        $desc=trim(html_entity_decode(strip_tags($desc),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $images=array_map('trim',explode(',',$r['Images']));
        $slug=$r['SKU']==='FU-LIVING'?'living-tee':strtolower($r['SKU']);
        $category=['Cargo Pants'=>'Lowers','T-Shirts'=>'T-shirts'][$r['Categories']]??$r['Categories'];
        query('INSERT INTO products(slug,name,description,price,image,back_image,active,sku,category,short_description,source_images) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$slug,$r['Name'],$desc,(int)$variants[0]['Regular price'],'assets/products/'.$images[0],isset($images[1])?'assets/products/'.$images[1]:null,1,$r['SKU'],$category,$r['Short description'],json_encode($images)]);
        $id=db()->lastInsertId();
        foreach($variants as $v)query('INSERT INTO variants(product_id,size,stock) VALUES(?,?,?)',[$id,$v['Attribute 1 value(s)'],(int)$v['Stock']]);
    }
}
function install_schema(string $name):void {
    foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/'.$name)) as $sql){if(trim($sql)!=='')db()->exec($sql);}
}

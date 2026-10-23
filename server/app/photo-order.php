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

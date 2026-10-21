<?php
require __DIR__.'/app/bootstrap.php';
$id=(string)($_GET['id']??'');
if(!preg_match('/^[a-f0-9]{64}$/D',$id)){http_response_code(404);exit;}
$row=query('SELECT mime,content FROM product_images WHERE id=?',[$id])->fetch();
if(!$row){http_response_code(404);exit;}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
header('Content-Type: '.$row['mime']);header('Cache-Control: public, max-age=31536000, immutable');header('X-Content-Type-Options: nosniff');echo $row['content'];

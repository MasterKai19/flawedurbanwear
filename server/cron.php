<?php
require __DIR__.'/app/bootstrap.php';require __DIR__.'/app/notifications.php';
header('Content-Type: application/json');
if(!notifications_installed()||!notifications_ready()){echo json_encode(['status'=>'not_configured']);exit;}
$id=query("SELECT order_id FROM order_notifications WHERE status<>'sent' AND attempts<10 AND (lease_until IS NULL OR lease_until<NOW()) AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY id LIMIT 1")->fetchColumn();
if($id)notification_try((int)$id);
query('DELETE FROM cloud_sessions WHERE expires_at<NOW()');
echo json_encode(['status'=>'processed','attempted'=>$id?1:0]);

<?php
// Optional CLI worker: never accessible as a public HTTP task endpoint.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/app/bootstrap.php';require __DIR__.'/app/notifications.php';
if(!notifications_installed()||!notifications_ready()){fwrite(STDERR,"Google notifications are not configured.\n");exit(1);}
$rows=query("SELECT order_id FROM order_notifications WHERE status<>'sent' AND attempts<10 AND (lease_until IS NULL OR lease_until<NOW()) AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY id LIMIT 10")->fetchAll();
$sent=0;foreach($rows as $row)if(notification_send((int)$row['order_id']))$sent++;
echo 'Completed '.$sent.' of '.count($rows)." due orders.\n";

<?php
declare(strict_types=1);
function admin_remember_cookie(string $value,int $expires):void {
    global $config,$https;
    setcookie('flawed_admin_remember',$value,['expires'=>$expires,'path'=>rtrim($config['base_path'],'/').'/admin','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
}
function admin_forget_device():void {
    $cookie=(string)($_COOKIE['flawed_admin_remember']??'');
    if(preg_match('/^([a-f0-9]{32})\.[a-f0-9]{64}$/D',$cookie,$m))query('DELETE FROM admin_remember_tokens WHERE selector=?',[$m[1]]);
    admin_remember_cookie('',time()-3600);
}
function admin_remember_device(int $adminId,string $passwordHash):void {
    admin_forget_device();
    $selector=bin2hex(random_bytes(16));$validator=bin2hex(random_bytes(32));
    query('INSERT INTO admin_remember_tokens(selector,admin_id,token_hash,password_stamp,expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 365 DAY))',[$selector,$adminId,hash('sha256',$validator),hash('sha256',$passwordHash)]);
    admin_remember_cookie($selector.'.'.$validator,time()+365*86400);
}
function admin_restore_device():void {
    // Upgrade sessions created before automatic remembered login.
    if(empty($_COOKIE['flawed_admin_remember'])){
        if(!empty($_SESSION['admin_id'])){
            $admin=query('SELECT id,password_hash FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetch();
            if($admin)admin_remember_device((int)$admin['id'],$admin['password_hash']);
        }
        return;
    }
    $cookie=(string)$_COOKIE['flawed_admin_remember'];
    if(!preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D',$cookie,$m)){admin_remember_cookie('',time()-3600);return;}
    $row=query('SELECT t.*,a.password_hash FROM admin_remember_tokens t JOIN admins a ON a.id=t.admin_id WHERE t.selector=? AND t.expires_at>NOW()',[$m[1]])->fetch();
    if(!$row||!hash_equals($row['token_hash'],hash('sha256',$m[2]))||!hash_equals($row['password_stamp'],hash('sha256',$row['password_hash']))){admin_remember_cookie('',time()-3600);return;}
    if(empty($_SESSION['admin_id'])){session_regenerate_id(true);$_SESSION['admin_id']=$row['admin_id'];$_SESSION['csrf']=bin2hex(random_bytes(32));}
    query('UPDATE admin_remember_tokens SET expires_at=DATE_ADD(NOW(),INTERVAL 365 DAY) WHERE selector=?',[$m[1]]);
    admin_remember_cookie($cookie,time()+365*86400);
}

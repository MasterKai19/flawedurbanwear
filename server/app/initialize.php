<?php
declare(strict_types=1);
require_once __DIR__.'/catalog.php';
function initialize_store():void {
    try {
        if(query("SELECT value FROM settings WHERE name='installation_version'")->fetchColumn()==='2')return;
    } catch(PDOException $e) { if($e->getCode()!=='42S02')throw $e; }
    $lock='flawed_install_'.substr(hash('sha256',(string)$GLOBALS['config']['db_name']),0,32);
    if((int)query('SELECT GET_LOCK(?,15)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Initialization busy. Retry shortly.');
    try {
        install_schema('schema.sql');install_schema('cloud.sql');install_schema('notifications.sql');
        if(query("SELECT value FROM settings WHERE name='installation_version'")->fetchColumn()==='2')return;
        $hasOwner=(int)query('SELECT COUNT(*) FROM admins')->fetchColumn()>0;
        $email=trim((string)getenv('OWNER_EMAIL'));
        $password=(string)getenv('OWNER_PASSWORD');
        if(!$hasOwner&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||strlen($password)<12)) {
            throw new RuntimeException('Set OWNER_EMAIL and OWNER_PASSWORD (at least 12 characters) in Vercel.');
        }
        db()->beginTransaction();
        if(!$hasOwner)query('INSERT INTO admins(email,password_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);
        seed_catalog();
        query("INSERT INTO settings(name,value) VALUES('installation_version','2') ON DUPLICATE KEY UPDATE value='2'");
        db()->commit();
    } catch(Throwable $e) {
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    } finally { query('SELECT RELEASE_LOCK(?)',[$lock]); }
}

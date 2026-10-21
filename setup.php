<?php
require __DIR__.'/app/bootstrap.php';
$error='';$local=in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true);
if(!$local && (empty($config['setup_key']) || !hash_equals($config['setup_key'],(string)($_POST['setup_key']??'')))) {
    render_header('Store setup');echo '<main class="page narrow"><h1>Owner setup</h1><p>Enter the setup key from app/config.php.</p><form method="post"><label>Setup key<input type="password" name="setup_key" required></label><button class="button dark">CONTINUE</button></form></main>';render_footer();exit;
}
try { $exists=db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();if($exists){http_response_code(403);exit('Setup is locked. An administrator already exists. Open admin/login.php.');} } catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['email'])){
    check_csrf();$email=trim($_POST['email']);$password=(string)$_POST['password'];
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)$error='Enter a valid email and a password of at least 12 characters.';
    else {
        db()->exec(file_get_contents(__DIR__.'/database/schema.sql'));
        db()->exec(file_get_contents(__DIR__.'/database/notifications.sql'));
        // A database lock prevents two first-run requests creating separate owners.
        $locked=(int)query("SELECT GET_LOCK('flawed_first_admin',10)")->fetchColumn();
        if(!$locked)throw new RuntimeException('Setup is busy.');
        try{db()->beginTransaction();if(query('SELECT COUNT(*) FROM admins')->fetchColumn())throw new RuntimeException('Setup is already complete.');
        query('INSERT INTO admins(email,password_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);
        if(!query('SELECT COUNT(*) FROM products')->fetchColumn()){
            query('INSERT INTO products(slug,name,description,price,image,back_image) VALUES(?,?,?,?,?,?)',['living-tee','Living Tee','A quiet signature up front. A little more life on the back. Black with the Flawed chest logo and “Just living my best life” artwork.',500,'assets/front.png','assets/back.png']);$id=db()->lastInsertId();foreach(['S','M','L','XL','XXL'] as $size)query('INSERT INTO variants(product_id,size,stock) VALUES(?,?,NULL)',[$id,$size]);
        }
        db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}finally{query("SELECT RELEASE_LOCK('flawed_first_admin')");}
        flash('Store installed. Sign in, set delivery fees and check your product sizes and inventory.');redirect('admin/login.php');
    }
}
render_header('Set up your store'); ?>
<main class="page narrow"><p class="eyebrow">OWNER SETUP / ONE TIME ONLY</p><h1>Make it yours.</h1><p>Create your admin account. The database must already exist in phpMyAdmin. Setup creates the tables and adds the Living Tee at ৳500.</p><?php if($error):?><p class="notice error"><?=h($error)?></p><?php endif ?>
<form method="post"><?=csrf_input()?><input type="hidden" name="setup_key" value="<?=h($_POST['setup_key']??'')?>"><label>Admin email<input type="email" name="email" required maxlength="190" autocomplete="username"></label><label>Admin password<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><p class="hint">Use at least 12 characters. There is no default password.</p><button class="button dark">CREATE STORE ↗</button></form></main><?php render_footer(); ?>

<?php
$databaseUrl=(string)(getenv('MYSQL_URL')?:getenv('DATABASE_URL'));
$parts=$databaseUrl!==''?parse_url($databaseUrl):[];
if($databaseUrl!==''&&(!$parts||!in_array($parts['scheme']??'', ['mysql','mysql2'],true)))throw new RuntimeException('Expected a MySQL connection URL.');
return [
 'db_host'=>(string)(getenv('DB_HOST')?:($parts['host']??'')),
 'db_port'=>(int)(getenv('DB_PORT')?:($parts['port']??3306)),
 'db_name'=>(string)(getenv('DB_NAME')?:rawurldecode(ltrim($parts['path']??'','/'))),
 'db_user'=>(string)(getenv('DB_USER')?:rawurldecode($parts['user']??'')),
 'db_pass'=>(string)(getenv('DB_PASSWORD')?:rawurldecode($parts['pass']??'')),
 'db_ssl_ca'=>(string)getenv('DB_SSL_CA'),
 'base_path'=>'', 'bkash_enabled'=>false,
];

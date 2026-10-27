<?php
require dirname(__DIR__).'/app/bootstrap.php';require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
check_csrf();
// Build a consistent, private download; never write the dump into public assets.
$pdo=db();$out=fopen('php://temp/maxmemory:2097152','w+');
$write=function(string $text)use($out){if(fwrite($out,$text)===false)throw new RuntimeException('Backup could not be written.');};
$quoteName=fn(string $name)=>'`'.str_replace('`','``',$name).'`';
try{
 $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 $tables=$pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
 $write("-- Flawed Urbanwear full database backup\n-- Created UTC: ".gmdate('c')."\n-- PRIVATE: includes customer records, admin password hashes and integration settings.\n-- Import into an EMPTY database. This script replaces tables with matching names.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+00:00';\n");
 foreach($tables as $table){
  $name=$quoteName($table[0]);$schema=$pdo->query('SHOW CREATE TABLE '.$name)->fetch(PDO::FETCH_NUM)[1];
  $write("\nDROP TABLE IF EXISTS ".$name.";\n".$schema.";\n");
  $columns=$pdo->query('SHOW FULL COLUMNS FROM '.$name)->fetchAll();
  $fields=implode(',',array_map(fn($column)=>$quoteName($column['Field']),$columns));
  $rows=$pdo->query('SELECT * FROM '.$name);$count=0;
  while($row=$rows->fetch(PDO::FETCH_NUM)){
   $values=[];foreach($row as $i=>$value){
    if($value===null)$values[]='NULL';
    elseif(preg_match('/blob|binary|bit/i',$columns[$i]['Type']))$values[]="X'".bin2hex((string)$value)."'";
    else $values[]=$pdo->quote((string)$value);
   }
   $write('INSERT INTO '.$name.' ('.$fields.') VALUES ('.implode(',',$values).");\n");$count++;
  }
  $write('-- Rows: '.$count."\n");
 }
 $pdo->exec('COMMIT');$write("\nSET FOREIGN_KEY_CHECKS=1;\n-- Backup complete.\n");
 rewind($out);header('Content-Type: application/sql; charset=utf-8');
 header('Content-Disposition: attachment; filename="flawed-urbanwear-'.gmdate('Y-m-d-His').'-backup.sql"');
 header('Cache-Control: no-store, private');header('Content-Length: '.fstat($out)['size']);
 fpassthru($out);fclose($out);
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fclose($out);throw $error;}

<?php
declare(strict_types=1);
function cloud_db(): PDO {
    static $pdo;
    if($pdo)return $pdo;
    global $config;
    if(!$config['db_host']||!$config['db_name']||!$config['db_user'])throw new RuntimeException('Missing database configuration.');
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
    $local=!getenv('VERCEL')&&in_array($config['db_host'],['127.0.0.1','localhost'],true);
    if(!$local){
        $ca=str_replace('\\n',"\n",$config['db_ssl_ca']);
        if(!str_contains($ca,'-----BEGIN CERTIFICATE-----'))throw new RuntimeException('Set DB_SSL_CA to the database CA certificate.');
        $path=sys_get_temp_dir().'/flawed-ca-'.hash('sha256',$ca).'.pem';
        if(!is_file($path)&&file_put_contents($path,$ca)===false)throw new RuntimeException('Unable to prepare CA certificate.');
        $options[PDO::MYSQL_ATTR_SSL_CA]=$path;$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
    }
    $pdo=new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],$options);
    $pdo->exec("SET time_zone = '+00:00'");return $pdo;
}
final class CloudSessions implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private ?string $lock=null;
    public function open(string $path,string $name):bool{
        cloud_db()->exec('CREATE TABLE IF NOT EXISTS cloud_sessions (id CHAR(64) PRIMARY KEY,data MEDIUMBLOB NOT NULL,expires_at DATETIME NOT NULL,INDEX(expires_at)) ENGINE=InnoDB');
        return true;
    }
    private function acquire(string $id):void{
        $name='fs:'.substr(hash('sha256',$id),0,60);
        if($this->lock===$name)return;
        $this->close();$q=cloud_db()->prepare('SELECT GET_LOCK(?,10)');$q->execute([$name]);
        if((int)$q->fetchColumn()!==1)throw new RuntimeException('Session busy.');
        $this->lock=$name;
    }
    public function close():bool{
        if($this->lock!==null){$q=cloud_db()->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$this->lock]);$this->lock=null;}return true;
    }
    public function read(string $id):string|false{
        $this->acquire($id);$q=cloud_db()->prepare('SELECT data FROM cloud_sessions WHERE id=? AND expires_at>NOW()');$q->execute([hash('sha256',$id)]);
        return (string)($q->fetchColumn()?:'');
    }
    public function write(string $id,string $data):bool{
        $this->acquire($id);$q=cloud_db()->prepare('INSERT INTO cloud_sessions(id,data,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE data=VALUES(data),expires_at=VALUES(expires_at)');
        return $q->execute([hash('sha256',$id),$data]);
    }
    public function destroy(string $id):bool{
        $q=cloud_db()->prepare('DELETE FROM cloud_sessions WHERE id=?');return $q->execute([hash('sha256',$id)]);
    }
    public function gc(int $max_lifetime):int|false{return cloud_db()->exec('DELETE FROM cloud_sessions WHERE expires_at<NOW()');}
    public function validateId(string $id):bool{
        $q=cloud_db()->prepare('SELECT 1 FROM cloud_sessions WHERE id=? AND expires_at>NOW()');$q->execute([hash('sha256',$id)]);return (bool)$q->fetchColumn();
    }
    public function updateTimestamp(string $id,string $data):bool{return $this->write($id,$data);}
}
function cloud_upload(string $field,string $fallback):string{
    if(empty($_FILES[$field]['name']))return $fallback;
    $f=$_FILES[$field];if($f['error']!==UPLOAD_ERR_OK||$f['size']>3*1024*1024)throw new DomainException('Images must be PNG, JPEG or WebP and at most 3 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if(!in_array($mime,['image/png','image/jpeg','image/webp'],true)||!getimagesize($f['tmp_name']))throw new DomainException('Choose a valid PNG, JPEG or WebP photo.');
    $bytes=file_get_contents($f['tmp_name']);$id=hash('sha256',$bytes);
    $q=cloud_db()->prepare('INSERT IGNORE INTO product_images(id,mime,content) VALUES(?,?,?)');$q->execute([$id,$mime,$bytes]);
    return 'image.php?id='.$id;
}

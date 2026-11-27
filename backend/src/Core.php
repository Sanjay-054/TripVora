<?php
declare(strict_types=1);
namespace TripVora;
use PDO;
use Throwable;
final class Config {
 public static function get(string $name,string $default=''):string {return getenv($name) ?: $default;}
}
final class DB {
 private static ?PDO $pdo=null;
 public static function conn():PDO {if(self::$pdo)return self::$pdo; $dsn='mysql:host='.Config::get('DB_HOST','127.0.0.1').';port='.Config::get('DB_PORT','4309').';dbname='.Config::get('DB_NAME','tripvora').';charset=utf8mb4'; return self::$pdo=new PDO($dsn,Config::get('DB_USER','root'),Config::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
 public static function rows(string $sql,array $params=[]):array {$s=self::conn()->prepare($sql);$s->execute($params);return $s->fetchAll();}
 public static function row(string $sql,array $params=[]):array|false {$s=self::conn()->prepare($sql);$s->execute($params);return $s->fetch();}
 public static function exec(string $sql,array $params=[]):int {$s=self::conn()->prepare($sql);$s->execute($params);return $s->rowCount();}
}
final class Http {
 public static function json(array $v,int $status=200):never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($v,JSON_UNESCAPED_SLASHES);exit;}
 public static function body():array {return json_decode(file_get_contents('php://input')?:'{}',true)?:[];}
 public static function must(bool $condition,string $message,int $status=422):void {if(!$condition)self::json(['ok'=>false,'message'=>$message],$status);}
 public static function id(string $role='user'):int {$id=(int)($_SESSION[$role.'_id']??0);self::must($id>0,'Login required',401);return $id;}
 public static function csrf():void {if($_SERVER['REQUEST_METHOD']!=='GET')self::must(hash_equals($_SESSION['csrf']??'',$_SERVER['HTTP_X_CSRF_TOKEN']??''),'Invalid CSRF token',403);}
}
interface PriceCalculator {public function total(int $units,float $rate):float;}
final class PerDayCalculator implements PriceCalculator {public function total(int $units,float $rate):float{return round($units*$rate,2);}}
final class PerNightCalculator implements PriceCalculator {public function total(int $units,float $rate):float{return round($units*$rate,2);}}
abstract class AccountService {protected function find(string $table,string $username):array|false {return DB::row("SELECT * FROM $table WHERE username=:u",['u'=>$username]);}}
final class AuthService extends AccountService {
 public function register(array $d):array { $name=trim($d['name']??'');$phone=preg_replace('/\D/','',$d['phone']??'');$email=strtolower(trim($d['email']??''));$username=trim($d['username']??'');$pass=$d['password']??'';$address=trim($d['address']??'');Http::must($name!==''&&preg_match('/^[0-9]{10}$/',$phone)&&filter_var($email,FILTER_VALIDATE_EMAIL)&&strlen($username)>=4&&strlen($pass)>=8&&$address!=='','Invalid registration details');Http::must(!DB::row('SELECT id FROM users WHERE email=:email OR username=:username',['email'=>$email,'username'=>$username]),'Email or username already exists',409);$pdo=DB::conn();$pdo->beginTransaction();try {DB::exec('INSERT INTO users(name,phone,email,username,password_hash,address) VALUES(:n,:p,:e,:u,:h,:a)',['n'=>$name,'p'=>$phone,'e'=>$email,'u'=>$username,'h'=>password_hash($pass,PASSWORD_DEFAULT),'a'=>$address]);$id=(int)$pdo->lastInsertId();$otp=(string)random_int(100000,999999);DB::exec('INSERT INTO otp_tokens(user_id,otp_hash,expires_at) VALUES(:id,:h,DATE_ADD(NOW(),INTERVAL 10 MINUTE))',['id'=>$id,'h'=>password_hash($otp,PASSWORD_DEFAULT)]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
 $sent=false;if(Config::get('MAIL_MODE','dev')==='mail')$sent=@mail($email,'TripVora verification OTP',"Hello $name, your OTP is $otp. It expires in 10 minutes.",'From: noreply@example.com');return ['ok'=>true,'message'=>$sent?'OTP sent by email':'Account created. Use development OTP when enabled; configure email for production.','user_id'=>$id,'dev_otp'=>Config::get('APP_ENV','local')==='local'&&Config::get('MAIL_MODE','dev')==='dev'?$otp:null];}
 public function verify(array $d):array {$id=(int)($d['user_id']??0);$otp=(string)($d['otp']??'');Http::must($id>0&&preg_match('/^[0-9]{6}$/',$otp),'Invalid OTP');$pdo=DB::conn();$pdo->beginTransaction();try{$row=DB::row('SELECT * FROM otp_tokens WHERE user_id=:id ORDER BY id DESC LIMIT 1 FOR UPDATE',['id'=>$id]);Http::must((bool)$row,'OTP not found',404);Http::must($row['used_at']===null,'OTP already used',409);Http::must(strtotime($row['expires_at'])>=time(),'OTP expired',410);Http::must((int)$row['attempts']<5,'Too many attempts',429);if(!password_verify($otp,$row['otp_hash'])) {DB::exec('UPDATE otp_tokens SET attempts=attempts+1 WHERE id=:id',['id'=>$row['id']]);$pdo->commit();Http::json(['ok'=>false,'message'=>'Incorrect OTP'],422);}DB::exec('UPDATE otp_tokens SET used_at=NOW() WHERE id=:id AND used_at IS NULL',['id'=>$row['id']]);DB::exec('UPDATE users SET email_verified_at=NOW() WHERE id=:id',['id'=>$id]);$pdo->commit();return ['ok'=>true,'message'=>'Email verified. Please login.'];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
 public function login(array $d,bool $admin=false):array {$identity=trim($d[$admin?'username':'identity']??'');$password=(string)($d['password']??'');Http::must($identity!==''&&$password!=='','Enter login credentials');$u=$admin?$this->find('admins',$identity):DB::row('SELECT * FROM users WHERE username=:username OR email=:email LIMIT 1',['username'=>$identity,'email'=>$identity]);Http::must((bool)$u&&password_verify($password,$u['password_hash']),'Invalid credentials',401);if(!$admin)Http::must($u['email_verified_at']!==null,'Verify email before login',403);session_regenerate_id(true);unset($_SESSION['admin_id'],$_SESSION['user_id']);$_SESSION[$admin?'admin_id':'user_id']=(int)$u['id'];unset($u['password_hash']);return ['ok'=>true,'user'=>$u];}
}

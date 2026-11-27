<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Core.php';require_once __DIR__.'/../src/TripService.php';
use TripVora\{Config,DB,Http,AuthService,TripService};
ini_set('session.use_strict_mode','1');ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Lax');if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');session_name('TRIPVORASESSID');session_start();header('Cache-Control: no-store');
$action=$_GET['action']??'';
try {
 if($action==='csrf'){$_SESSION['csrf']??=bin2hex(random_bytes(32));Http::json(['ok'=>true,'csrf'=>$_SESSION['csrf']]);}
 if($_SERVER['REQUEST_METHOD']!=='GET')Http::csrf();$data=Http::body();$auth=new AuthService();$trip=new TripService();
 switch($action){
 case 'register':Http::json($auth->register($data),201);
 case 'verify-otp':Http::json($auth->verify($data));
 case 'login':Http::json($auth->login($data));
 case 'admin-login':Http::json($auth->login($data,true));
 case 'logout':$_SESSION=[];session_regenerate_id(true);Http::json(['ok'=>true]);
 case 'me':$role=isset($_SESSION['admin_id'])?'admin':'user';$id=Http::id($role);$u=DB::row('SELECT id,name,username FROM '.($role==='admin'?'admins':'users').' WHERE id=:id',['id'=>$id]);Http::must((bool)$u,'Account not found',401);Http::json(['ok'=>true,'role'=>$role,'user'=>$u]);
 case 'destinations':Http::json(['ok'=>true,'items'=>DB::rows('SELECT * FROM destinations ORDER BY name')]);
 case 'attractions':Http::json(['ok'=>true,'items'=>DB::rows('SELECT * FROM attractions WHERE destination_id=:id ORDER BY name',['id'=>(int)($_GET['destination_id']??0)])]);
 case 'hotels':Http::json(['ok'=>true,'items'=>DB::rows('SELECT * FROM hotels WHERE destination_id=:id AND active=1 ORDER BY price_per_night',['id'=>(int)($_GET['destination_id']??0)])]);
 case 'vehicles':Http::json(['ok'=>true,'items'=>DB::rows('SELECT * FROM vehicles WHERE destination_id=:id AND active=1 ORDER BY vehicle_type',['id'=>(int)($_GET['destination_id']??0)])]);
 case 'quote':Http::id();Http::json($trip->quote($data));
 case 'book':$id=Http::id();Http::json($trip->quote($data,true,$id),201);
 case 'itinerary':Http::id();Http::json(['ok'=>true,'days'=>$trip->itinerary((int)($data['destination_id']??0),array_map('intval',$data['attraction_ids']??[]),(int)($data['days']??1))]);
 case 'my-trips':$id=Http::id();Http::json(['ok'=>true,'items'=>DB::rows('SELECT t.*,d.name destination_name,h.name hotel_name,v.name vehicle_name FROM trips t JOIN destinations d ON d.id=t.destination_id LEFT JOIN hotels h ON h.id=t.hotel_id LEFT JOIN vehicles v ON v.id=t.vehicle_id WHERE t.user_id=:id ORDER BY t.id DESC',['id'=>$id])]);
 case 'invoice':$id=Http::id();$row=DB::row('SELECT t.*,u.name customer_name,u.email,u.phone,u.address,d.name destination_name,h.name hotel_name,v.name vehicle_name FROM trips t JOIN users u ON u.id=t.user_id JOIN destinations d ON d.id=t.destination_id LEFT JOIN hotels h ON h.id=t.hotel_id LEFT JOIN vehicles v ON v.id=t.vehicle_id WHERE t.id=:trip AND t.user_id=:user',['trip'=>(int)($_GET['id']??0),'user'=>$id]);Http::must((bool)$row,'Invoice not found',404);$row['attractions']=DB::rows('SELECT a.name,a.entry_price FROM trip_attractions ta JOIN attractions a ON a.id=ta.attraction_id WHERE ta.trip_id=:id',['id'=>$row['id']]);Http::json(['ok'=>true,'invoice'=>$row]);
 case 'demo-pay':Http::must(Config::get('APP_ENV','local')==='local'&&Config::get('DEMO_PAYMENT','1')==='1','Demo payments disabled',403);$id=Http::id();DB::exec("UPDATE trips SET payment_status='paid' WHERE id=:trip AND user_id=:user AND status='booked'",['trip'=>(int)($data['trip_id']??0),'user'=>$id]);Http::json(['ok'=>true,'message'=>'DEMO payment recorded (no real money charged)']);
 case 'admin-stats':Http::id('admin');Http::json(['ok'=>true,'stats'=>DB::row("SELECT COUNT(*) bookings,COALESCE(SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END),0) revenue FROM trips"),'customers'=>DB::row('SELECT COUNT(*) total FROM users')['total'],'vehicles'=>DB::row('SELECT COUNT(*) total FROM vehicles')['total'],'hotels'=>DB::row('SELECT COUNT(*) total FROM hotels')['total']]);
 case 'admin-list':Http::id('admin');$table=(string)($_GET['table']??'');Http::must(in_array($table,['users','destinations','attractions','hotels','vehicles','trips','reviews'],true),'Invalid table');Http::json(['ok'=>true,'items'=>DB::rows('SELECT * FROM '.$table.' ORDER BY id DESC LIMIT 200')]);
 case 'admin-save':Http::id('admin');$table=(string)($data['table']??'');$allowed=['destinations'=>['name','description','image_url'],'attractions'=>['destination_id','name','description','image_url','entry_price','duration_minutes'],'hotels'=>['destination_id','name','description','image_url','rating','price_per_night','rooms_total','active'],'vehicles'=>['destination_id','name','vehicle_type','registration_number','image_url','price_per_day','active']];Http::must(isset($allowed[$table]),'Invalid table');$fields=array_values(array_filter($allowed[$table],fn($k)=>array_key_exists($k,$data)));Http::must(count($fields)>0,'No fields');$params=[];foreach($fields as $f)$params[$f]=$data[$f];$id=(int)($data['id']??0);if($id){$set=implode(',',array_map(fn($f)=>"$f=:$f",$fields));$params['id']=$id;DB::exec("UPDATE $table SET $set WHERE id=:id",$params);}else{$cols=implode(',',$fields);$placeholders=implode(',',array_map(fn($f)=>':'.$f,$fields));DB::exec("INSERT INTO $table($cols) VALUES($placeholders)",$params);$id=(int)DB::conn()->lastInsertId();}Http::json(['ok'=>true,'id'=>$id]);
 case 'admin-delete':Http::id('admin');$table=(string)($data['table']??'');Http::must(in_array($table,['destinations','attractions','hotels','vehicles'],true),'Invalid table');$id=(int)($data['id']??0);Http::must($id>0,'Invalid ID');if(in_array($table,['hotels','vehicles'],true)){DB::exec("UPDATE $table SET active=0 WHERE id=:id",['id'=>$id]);}else{DB::exec("DELETE FROM $table WHERE id=:id",['id'=>$id]);}Http::json(['ok'=>true]);
 default:Http::json(['ok'=>false,'message'=>'Unknown API action'],404);
 }
}catch(Throwable $e){error_log((string)$e);Http::json(['ok'=>false,'message'=>Config::get('APP_ENV','local')==='local'?'Server error: '.$e->getMessage():'Internal server error'],500);}

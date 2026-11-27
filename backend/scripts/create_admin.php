<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once __DIR__.'/../src/Core.php';
use TripVora\DB;
$username=$argv[1]??'';$password=$argv[2]??'';$name=$argv[3]??'TripVora Administrator';
if(strlen($username)<4||strlen($password)<12){fwrite(STDERR,"Usage: php create_admin.php USERNAME PASSWORD_AT_LEAST_12_CHARS [NAME]\n");exit(1);}
if(DB::row('SELECT id FROM admins WHERE username=:u',['u'=>$username])){fwrite(STDERR,"Admin username already exists.\n");exit(1);}
DB::exec('INSERT INTO admins(name,username,password_hash) VALUES(:n,:u,:p)',['n'=>$name,'u'=>$username,'p'=>password_hash($password,PASSWORD_DEFAULT)]);echo "Admin created: $username\n";

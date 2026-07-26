<?php
declare(strict_types=1);
$config = require '/etc/seyedasil-bot/config.php';
if(session_status()!==PHP_SESSION_ACTIVE){
 session_name('seyedasil_admin');
 session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
 session_start();
}
function cfg(?string $key=null){global $config;return $key===null?$config:($config[$key]??null);}
function db():PDO{static $p;if($p)return $p;$d=cfg('db');return $p=new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??''))){http_response_code(419);exit('CSRF');}}
function admin():bool{return !empty($_SESSION['admin']);}
function guard():void{if(!admin()){header('Location:/admin/login.php');exit;}}
function money(int $n):string{return number_format($n).' تومان';}
function telegram(string $method,array $data=[]):array{$ch=curl_init('https://api.telegram.org/bot'.cfg('bot_token').'/'.$method);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$data,CURLOPT_TIMEOUT=>30]);$r=curl_exec($ch);curl_close($ch);return json_decode((string)$r,true)?:[];}
function send_msg($chat,$text,$keyboard=null):array{$p=['chat_id'=>$chat,'text'=>$text,'parse_mode'=>'HTML'];if($keyboard)$p['reply_markup']=json_encode($keyboard,JSON_UNESCAPED_UNICODE);return telegram('sendMessage',$p);}
function user_upsert(array $f):int{$q=db()->prepare("INSERT INTO users(telegram_id,username,first_name,last_name)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),first_name=VALUES(first_name),last_name=VALUES(last_name),updated_at=NOW()");$q->execute([(int)$f['id'],$f['username']??null,$f['first_name']??null,$f['last_name']??null]);$q=db()->prepare("SELECT id FROM users WHERE telegram_id=?");$q->execute([(int)$f['id']]);return(int)$q->fetchColumn();}
function main_menu():array{return['keyboard'=>[[['text'=>'🛍 خدمات'],['text'=>'📦 سفارش‌های من']],[['text'=>'💰 کیف پول'],['text'=>'👤 حساب من']],[['text'=>'💬 پشتیبانی']],],'resize_keyboard'=>true];}
function storage(string $folder):string{$p=__DIR__.'/../storage/'.$folder;is_dir($p)||mkdir($p,0750,true);return $p;}
function safe_ext(string $name):string{return strtolower(pathinfo($name,PATHINFO_EXTENSION));}
function save_upload(array $file,string $folder):?string{if(($file['error']??1)!==UPLOAD_ERR_OK)return null;$max=(int)cfg('upload')['max_mb']*1024*1024;if(($file['size']??0)>$max)return null;$ext=safe_ext($file['name']??'');$allowed=['jpg','jpeg','png','pdf','webp','doc','docx'];if(!in_array($ext,$allowed,true))return null;$name=bin2hex(random_bytes(16)).'.'.$ext;$dest=storage($folder).'/'.$name;if(!move_uploaded_file($file['tmp_name'],$dest))return null;return $folder.'/'.$name;}
function make_order_no():string{return 'SN'.date('ymdHis').random_int(100,999);}
function user_by_tg(int $tg):?array{$q=db()->prepare("SELECT * FROM users WHERE telegram_id=?");$q->execute([$tg]);return$q->fetch()?:null;}
function debit_wallet(PDO $pdo,int $uid,int $amount,string $desc,int $ref):bool{$pdo->beginTransaction();try{$q=$pdo->prepare("SELECT wallet_balance FROM users WHERE id=? FOR UPDATE");$q->execute([$uid]);$bal=(int)$q->fetchColumn();if($bal<$amount){$pdo->rollBack();return false;}$pdo->prepare("UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?")->execute([$amount,$uid]);$pdo->prepare("INSERT INTO wallet_transactions(user_id,type,amount,description,reference_id)VALUES(?,?,?,?,?)")->execute([$uid,'debit',$amount,$desc,$ref]);$pdo->commit();return true;}catch(Throwable $e){$pdo->rollBack();throw$e;}}
function credit_wallet(PDO $pdo,int $uid,int $amount,string $desc,int $ref):void{$pdo->beginTransaction();try{$pdo->prepare("UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?")->execute([$amount,$uid]);$pdo->prepare("INSERT INTO wallet_transactions(user_id,type,amount,description,reference_id)VALUES(?,?,?,?,?)")->execute([$uid,'credit',$amount,$desc,$ref]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw$e;}}

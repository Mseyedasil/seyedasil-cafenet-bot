<?php
require_once __DIR__.'/../app/bootstrap.php';
if(!hash_equals((string)cfg('app_key'),$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'')){http_response_code(403);exit;}
$u=json_decode(file_get_contents('php://input')?:'',true)?:[];
try{
 if(isset($u['message']))msg($u['message']);
 if(isset($u['callback_query']))cb($u['callback_query']);
}catch(Throwable $e){error_log($e->getMessage());}
echo'OK';

function msg(array $m):void{
 if(empty($m['from']['id']))return;
 user_upsert($m['from']);$chat=(int)$m['chat']['id'];$text=trim((string)($m['text']??''));
 if(isset($_SESSION['collect'][$chat]) && !in_array($text,['🛍 خدمات','📦 سفارش‌های من','💰 کیف پول','👤 حساب من','💬 پشتیبانی'],true)){collect_value($chat,$m);return;}
 if(preg_match('/^شارژ\\s+(\\d+)\\s*(.*)$/u',$text,$mm)){create_topup_request($chat,(int)$mm[1],trim($mm[2]));return;}
 if($text==='/start'||$text==='/menu'){send_msg($chat,'سلام <b>'.e($m['from']['first_name']??'دوست عزیز').'</b> 👋'."\n\nبه <b>کافی‌نت سیداصیل</b> خوش آمدید.",main_menu());return;}
 if($text==='🛍 خدمات'){cats($chat);return;}
 if($text==='📦 سفارش‌های من'){orders($chat);return;}
 if($text==='💰 کیف پول'){wallet($chat);return;}
 if($text==='👤 حساب من'){account($chat);return;}
 if($text==='💬 پشتیبانی'){send_msg($chat,'💬 پیام خود را همین‌جا ارسال کنید. پشتیبانی به‌زودی فعال می‌شود.',main_menu());return;}
 send_msg($chat,'یکی از گزینه‌های منو را انتخاب کنید 👇',main_menu());
}
function cats(int $chat):void{$r=db()->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();if(!$r){send_msg($chat,'هنوز خدمتی ثبت نشده است.');return;}$b=[];foreach($r as $x)$b[]=[['text'=>'📁 '.$x['name'],'callback_data'=>'cat:'.$x['id']]];send_msg($chat,'دسته‌بندی را انتخاب کنید:',['inline_keyboard'=>$b]);}
function wallet(int $chat):void{$u=user_by_tg($chat);$card=cfg('payment')['card_number'];$owner=cfg('payment')['card_owner'];send_msg($chat,'💰 موجودی: <b>'.money((int)$u['wallet_balance'])."</b>\n\nبرای شارژ کیف پول، مبلغ و تصویر رسید را برای پشتیبانی ارسال کنید.\n\nشماره کارت: <code>".e($card)."</code>\nصاحب کارت: <b>".e($owner).'</b>');}
function account(int $chat):void{$u=user_by_tg($chat);send_msg($chat,'👤 <b>حساب من</b>'."\n\nنام: ".e(trim(($u['first_name']??'').' '.($u['last_name']??'')))."\nشناسه: <code>$chat</code>\nموجودی: <b>".money((int)$u['wallet_balance']).'</b>');}
function orders(int $chat):void{$u=user_by_tg($chat);$q=db()->prepare("SELECT o.order_no,s.name,o.amount,o.status,o.created_at FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 10");$q->execute([$u['id']]);$r=$q->fetchAll();if(!$r){send_msg($chat,'📦 سفارشی ندارید.');return;}$map=['pending'=>'در انتظار','processing'=>'در حال انجام','completed'=>'تکمیل','cancelled'=>'لغو','awaiting_payment'=>'در انتظار پرداخت'];$t="📦 <b>آخرین سفارش‌ها</b>\n\n";foreach($r as $x)$t.='🔹 <code>'.$x['order_no'].'</code> — '.e($x['name'])."\n💰 ".money((int)$x['amount']).' — '.($map[$x['status']]??$x['status'])."\n\n";send_msg($chat,$t);}
function cb(array $c):void{
 $chat=(int)$c['from']['id'];$d=(string)($c['data']??'');if($c['id'])telegram('answerCallbackQuery',['callback_query_id'=>$c['id']]);
 if(str_starts_with($d,'cat:')){ $id=(int)substr($d,4);$q=db()->prepare("SELECT name FROM categories WHERE id=? AND is_active=1");$q->execute([$id]);$cat=$q->fetch();if(!$cat)return;$q=db()->prepare("SELECT id,name,price FROM services WHERE category_id=? AND is_active=1 ORDER BY sort_order,id");$q->execute([$id]);$r=$q->fetchAll();$b=[];foreach($r as $x)$b[]=[['text'=>$x['name'].' — '.money((int)$x['price']),'callback_data'=>'srv:'.$x['id']]];send_msg($chat,'📁 <b>'.e($cat['name']).'</b>'."\n\nخدمت را انتخاب کنید:",['inline_keyboard'=>$b]);return;}
 if(str_starts_with($d,'srv:')){show_service($chat,(int)substr($d,4));return;}
 if(str_starts_with($d,'confirm:')){create_order($chat,(int)substr($d,8));return;}
}
function show_service(int $chat,int $sid):void{
 $q=db()->prepare("SELECT s.*,c.name category_name FROM services s JOIN categories c ON c.id=s.category_id WHERE s.id=? AND s.is_active=1 AND c.is_active=1");$q->execute([$sid]);$s=$q->fetch();if(!$s)return;
 $q=db()->prepare("SELECT * FROM service_fields WHERE service_id=? ORDER BY sort_order,id");$q->execute([$sid]);$f=$q->fetchAll();
 $_SESSION['service'][$chat]=['service_id'=>$sid,'fields'=>$f];
 $t='🛍 <b>'.e($s['name'])."</b>\n\n".e($s['description']??'')."\n\n💰 مبلغ: <b>".money((int)$s['price'])."</b>\n\n";
 if(!$f){send_msg($chat,$t.'برای ثبت سفارش روی تأیید بزنید.',['inline_keyboard'=>[[['text'=>'✅ ثبت سفارش','callback_data'=>'confirm:'.$sid]]]]);return;}
 $t.='📋 برای تکمیل فرم، در این نسخه اطلاعات متنی را به ترتیب ارسال کنید:'."\n\n";foreach($f as $i=>$x)$t.=($i+1).') '.e($x['label']).($x['is_required']?' *':'')."\n";$_SESSION['collect'][$chat]=['service_id'=>$sid,'index'=>0,'values'=>[],'fields'=>$f];send_msg($chat,$t."\n✏️ مقدار «".e($f[0]['label'])."» را ارسال کنید.");}
function collect_value(int $chat,array $m):void{
 $c=$_SESSION['collect'][$chat]??null;if(!$c)return;
 $i=(int)$c['index'];$f=$c['fields'][$i]??null;if(!$f)return;
 $value='';$filePath=null;
 if(in_array($f['field_type'],['file','image'],true)){
   if($f['field_type']==='image' && empty($m['photo'])){send_msg($chat,'📷 لطفاً تصویر را ارسال کنید.');return;}
   if($f['field_type']==='file' && empty($m['document'])){send_msg($chat,'📎 لطفاً فایل را ارسال کنید.');return;}
   $filePath=telegram_download_upload($m,$f['field_type']==='image'?'uploads':'uploads');
   if(!$filePath){send_msg($chat,'❌ دریافت فایل انجام نشد یا نوع فایل مجاز نیست.');return;}
   $value=$filePath;
 }else{
   $value=trim((string)($m['text']??''));
   if($value===''){send_msg($chat,'❌ مقدار معتبر ارسال کنید.');return;}
 }
 $c['values'][$i]=$value;$c['file_paths'][$i]=$filePath;$i++;
 if($i<count($c['fields'])){
   $c['index']=$i;$_SESSION['collect'][$chat]=$c;
   send_msg($chat,'✏️ مقدار «'.e($c['fields'][$i]['label']).'» را ارسال کنید.');
   return;
 }
 $_SESSION['collect'][$chat]=$c;$sid=(int)$c['service_id'];
 $q=db()->prepare("SELECT name,price FROM services WHERE id=? AND is_active=1");$q->execute([$sid]);$svc=$q->fetch();
 send_msg($chat,'📋 اطلاعات فرم تکمیل شد.'."\n\nخدمت: <b>".e($svc['name'])."</b>\nمبلغ: <b>".money((int)$svc['price'])."</b>\n\nبا تأیید، مبلغ از کیف پول کسر و سفارش ثبت می‌شود.",['inline_keyboard'=>[[['text'=>'✅ تأیید و ثبت سفارش','callback_data'=>'confirm:'.$sid]]]]);
}
function telegram_download_upload(array $m,string $folder):?string{
 $fileId=null;$ext='';
 if(!empty($m['document']['file_id'])){$fileId=$m['document']['file_id'];$ext=safe_ext($m['document']['file_name']??'');}
 elseif(!empty($m['photo'])){$last=end($m['photo']);$fileId=$last['file_id'];$ext='jpg';}
 if(!$fileId)return null;
 $r=telegram('getFile',['file_id'=>$fileId]);$path=$r['result']['file_path']??null;if(!$path)return null;
 $allowed=['jpg','jpeg','png','webp','pdf','doc','docx'];
 if(!in_array($ext,$allowed,true))return null;
 $name=bin2hex(random_bytes(16)).'.'.$ext;$dest=storage($folder).'/'.$name;
 $ch=curl_init('https://api.telegram.org/file/bot'.cfg('bot_token').'/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60]);$data=curl_exec($ch);curl_close($ch);
 if($data===false||strlen($data)>20*1024*1024)return null;
 return file_put_contents($dest,$data)!==false?$folder.'/'.$name:null;
}
function create_topup_request(int $chat,int $amount,string $tracking):void{
 if($amount<1000){send_msg($chat,'❌ مبلغ شارژ نامعتبر است.');return;}
 $u=user_by_tg($chat);
 db()->prepare("INSERT INTO topup_requests(user_id,amount,tracking_code)VALUES(?,?,?)")->execute([$u['id'],$amount,$tracking?:null]);
 send_msg($chat,'✅ درخواست شارژ ثبت شد.'."\nمبلغ: <b>".money($amount)."</b>\nکد پیگیری: <code>".e($tracking?:'ثبت نشده')."</code>\n\nپس از بررسی مدیر، موجودی کیف پول افزایش پیدا می‌کند.");
}
function create_order(int $chat,int $sid):void{
 $u=user_by_tg($chat);$q=db()->prepare("SELECT * FROM services WHERE id=? AND is_active=1");$q->execute([$sid]);$svc=$q->fetch();if(!$svc)return;
 $amount=(int)$svc['price'];$pdo=db();$pdo->beginTransaction();
 try{
   $lock=$pdo->prepare("SELECT wallet_balance FROM users WHERE id=? FOR UPDATE");$lock->execute([$u['id']]);$balance=(int)$lock->fetchColumn();
   if($balance<$amount){$pdo->rollBack();send_msg($chat,'❌ موجودی کیف پول کافی نیست.'."\nمبلغ خدمت: <b>".money($amount)."</b>\nموجودی شما: <b>".money($balance)."</b>\n\nابتدا کیف پول را شارژ کنید.");return;}
   $orderNo=make_order_no();
   $pdo->prepare("INSERT INTO orders(order_no,user_id,service_id,amount)VALUES(?,?,?,?)")->execute([$orderNo,$u['id'],$sid,$amount]);
   $oid=(int)$pdo->lastInsertId();
   $c=$_SESSION['collect'][$chat]??['values'=>[],'fields'=>[],'file_paths'=>[]];
   foreach(($c['fields']??[]) as $i=>$f){
     $v=$c['values'][$i]??'';$fp=$c['file_paths'][$i]??null;
     $pdo->prepare("INSERT INTO order_fields(order_id,field_label,field_key,field_type,value_text,file_path)VALUES(?,?,?,?,?,?)")->execute([$oid,$f['label'],$f['field_key'],$f['field_type'],is_string($v)?$v:json_encode($v,JSON_UNESCAPED_UNICODE),$fp]);
   }
   $pdo->prepare("UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?")->execute([$amount,$u['id']]);
   $pdo->prepare("INSERT INTO wallet_transactions(user_id,type,amount,description,reference_id)VALUES(?,?,?,?,?)")->execute([$u['id'],'debit',$amount,'ثبت سفارش '.$orderNo,$oid]);
   $pdo->commit();unset($_SESSION['collect'][$chat]);
   send_msg($chat,'✅ سفارش ثبت شد.'."\n\nشماره سفارش: <code>$orderNo</code>\nخدمت: <b>".e($svc['name'])."</b>\nمبلغ: <b>".money($amount)."</b>\n\nسفارش برای کافی‌نت ارسال شد.");
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());send_msg($chat,'❌ ثبت سفارش انجام نشد.');}
}

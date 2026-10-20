<?php
require __DIR__.'/../app/bootstrap.php'; require __DIR__.'/../app/paystack.php'; require __DIR__.'/../app/currency.php';
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')?:'/'; $post=$_SERVER['REQUEST_METHOD']==='POST';
$M=['bust'=>[20,250],'shoulder'=>[10,80],'waist'=>[20,250],'hip'=>[20,250],'sleeve'=>[5,100],'dress_length'=>[20,250],'top_length'=>[10,150],'trouser_length'=>[20,200]];
$STAGES=['pending_review','awaiting_quotation','awaiting_payment','confirmed','in_production','quality_check','ready','dispatched','delivered','cancelled'];
function ref(){return 'EC-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4)));}
function price_tag($amount){return '<span data-price-ngn="'.(int)$amount.'">'.e(naira($amount)).'</span>';}
switch(true){
case $path==='/api/currency'&&$_SERVER['REQUEST_METHOD']==='GET':
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: public, max-age=1800');
  try{echo json_encode(currency_rates(),JSON_THROW_ON_ERROR);}
  catch(RuntimeException $error){error_log($error->getMessage());http_response_code(502);echo json_encode(['error'=>'Exchange rates are temporarily unavailable.']);}
  exit;
case $path==='/': page('Home','<h1>Wear your style. Own your elegance.</h1><p>Ready-to-wear, custom design and made-to-measure fashion.</p><a href=/shop>Explore collections</a> · <a href=/custom>Request a custom design</a>');break;

case $path==='/shop': $rows=q("SELECT * FROM products WHERE status='published'")->fetchAll(); $h='<h1>Shop</h1><p><small>Converted prices are estimates. Paystack checkout is charged in NGN.</small></p>';
  foreach($rows as $p)$h.='<p><a href="/product?id='.$p['id'].'"><b>'.e($p['name']).'</b></a> — '.price_tag($p['price']).' <small>('.e($p['lead_time']).')</small></p>'; page('Shop',$h);break;

case $path==='/product': $p=q("SELECT * FROM products WHERE id=? AND status='published'",[(int)($_GET['id']??0)])->fetch(); if(!$p){http_response_code(404);exit('Not found');}
  $opts='';if(user())foreach(q('SELECT id,name FROM measurement_profiles WHERE user_id=?',[user()['id']]) as $m)$opts.='<option value="'.$m['id'].'">'.e($m['name']).'</option>';
  page($p['name'],'<h1>'.e($p['name']).'</h1><p>'.e($p['description']).'</p><p><b>'.price_tag($p['price']).'</b> · '.e($p['fabric']).' · '.e($p['lead_time']).'</p><p><small>Converted display only; Paystack checkout is charged in NGN.</small></p>
  <form method=post action=/order>'.field().'<input type=hidden name=product_id value='.$p['id'].'>
  <label>Size<select name=size><option>UK 8<option>UK 10<option>UK 12<option>UK 14<option>UK 16<option>Custom fit</select></label>
  <label>Measurement profile (for custom fit)<select name=profile_id><option value="">None</option>'.$opts.'</select></label>
  <label>Order by<select name=channel><option value=paystack>Pay now with Paystack<option value=whatsapp>Discuss on WhatsApp (pending approval)</select></label><button>Continue</button></form>');break;

case $path==='/order'&&$post: $u=need(); check();
  $p=q("SELECT * FROM products WHERE id=? AND status='published'",[(int)$_POST['product_id']])->fetch(); if(!$p)exit('Product unavailable');
  $snap=null; if(!empty($_POST['profile_id'])){$m=q('SELECT name,unit,data FROM measurement_profiles WHERE id=? AND user_id=?',[(int)$_POST['profile_id'],$u['id']])->fetch(); if($m)$snap=json_encode($m);}
  $ch=$_POST['channel']==='whatsapp'?'whatsapp':'paystack'; $r=ref(); $size=substr($_POST['size'],0,40);
  q('INSERT INTO orders(ref,user_id,product_id,item_name,size,measurement_snapshot,total,channel,status) VALUES(?,?,?,?,?,?,?,?,?)',[$r,$u['id'],$p['id'],$p['name'],$size,$snap,$p['price'],$ch,$ch==='paystack'?'awaiting_payment':'pending_review']);
  $oid=$pdo->lastInsertId(); history($oid,"Order created via $ch");
  if($ch==='whatsapp'){$msg="Hello Elegance Couture,\n\nI would like to place an order.\n\nOrder reference: $r\nOutfit: {$p['name']}\nSelected size: $size\n\nPlease confirm the design, final price, production timeline and payment arrangements.\n\nThank you.";
    go('https://wa.me/'.$_ENV['WHATSAPP'].'?text='.rawurlencode($msg));}
  $cb=(!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/paystack/callback';
  $res=ps('POST','/transaction/initialize',['email'=>$u['email'],'amount'=>$p['price']*100,'currency'=>'NGN','reference'=>$r,'callback_url'=>$cb]);
  if(!empty($res['data']['authorization_url']))go($res['data']['authorization_url']); page('Payment','<p>Could not start payment. Your order '.e($r).' is saved; try again from Client Corner.</p>');break;

case $path==='/paystack/callback': $r=$_GET['reference']??''; verify_and_mark($r); go('/client');break;

case $path==='/paystack/webhook'&&$post: $raw=file_get_contents('php://input');
  if(!hash_equals(hash_hmac('sha512',$raw,$_ENV['PAYSTACK_SECRET']),$_SERVER['HTTP_X_PAYSTACK_SIGNATURE']??'')){http_response_code(401);exit;}
  $ev=json_decode($raw,true); if(($ev['event']??'')==='charge.success')verify_and_mark($ev['data']['reference']??''); http_response_code(200);break;

case $path==='/register': if($post){check();$em=filter_var($_POST['email'],FILTER_VALIDATE_EMAIL);if(!$em||strlen($_POST['password'])<8)exit('Use a valid email and a password of 8+ characters.');
    try{q('INSERT INTO users(name,email,phone,password_hash) VALUES(?,?,?,?)',[$_POST['name'],$em,$_POST['phone'],password_hash($_POST['password'],PASSWORD_DEFAULT)]);}catch(PDOException $x){exit('That email is already registered.');}go('/login');}
  page('Register','<h1>Create account</h1><form method=post>'.field().'<input name=name placeholder=Name required><input name=email type=email placeholder=Email required><input name=phone placeholder=Phone required><input name=password type=password placeholder="Password (8+ characters)" required><button>Register</button></form>');break;

case $path==='/login': if($post){check();$_SESSION['fail']=($_SESSION['fail']??0)+1;if($_SESSION['fail']>5)exit('Too many attempts. Try again later.');
    $u=q('SELECT * FROM users WHERE email=?',[$_POST['email']])->fetch();
    if($u&&password_verify($_POST['password'],$u['password_hash'])){session_regenerate_id(true);$_SESSION['u']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];$_SESSION['fail']=0;go($u['role']==='admin'?'/admin':'/client');}exit('Wrong email or password.');}
  page('Log in','<h1>Log in</h1><form method=post>'.field().'<input name=email type=email placeholder=Email required><input name=password type=password placeholder=Password required><button>Log in</button></form>');break;

case $path==='/logout'&&$post: check();session_destroy();go('/');break;

case $path==='/measurements': $u=need(); if($post){check();$d=[];foreach($M as $k=>[$lo,$hi]){$v=$_POST[$k]??'';if($v!==''){if(!is_numeric($v)||$v<$lo||$v>$hi)exit("Check your $k value ($lo–$hi).");$d[$k]=(float)$v;}}
    q('INSERT INTO measurement_profiles(user_id,name,unit,data) VALUES(?,?,?,?)',[$u['id'],substr($_POST['name'],0,80),$_POST['unit']==='in'?'in':'cm',json_encode($d)]);go('/measurements');}
  $h='<h1>My measurements</h1><p><small>Online measurements help personalise your garment but cannot guarantee a perfect fit; a fitting may be needed.</small></p><form method=post>'.field().'<input name=name placeholder="Profile name" required><select name=unit><option value=cm>cm<option value=in>inches</select>';
  foreach($M as $k=>$_)$h.='<input name='.$k.' type=number step=.1 placeholder="'.ucwords(str_replace('_',' ',$k)).'">'; $h.='<button>Save profile</button></form>';
  foreach(q('SELECT name,unit,needs_review FROM measurement_profiles WHERE user_id=?',[$u['id']]) as $m)$h.='<p>'.e($m['name']).' ('.$m['unit'].')'.($m['needs_review']?' — <b>Please review this profile</b>':'').'</p>'; page('Measurements',$h);break;

case $path==='/custom': $u=need(); if($post){check();q('INSERT INTO custom_requests(user_id,category,description,fabric,event_date,budget,contact_pref) VALUES(?,?,?,?,?,?,?)',[$u['id'],$_POST['category'],$_POST['description'],$_POST['fabric'],$_POST['event_date']?:null,$_POST['budget'],$_POST['contact_pref']]);go('/client');}
  page('Custom design','<h1>Request a custom design</h1><p>Not an accepted job until the designer reviews it and agrees price and timeline.</p><form method=post>'.field().'<select name=category><option>Gown<option>Dress<option>Native wear<option>Suit</select><textarea name=description rows=5 placeholder="Describe the outfit" required></textarea><input name=fabric placeholder="Fabric / colour"><input name=event_date type=date><input name=budget placeholder="Budget (₦)"><select name=contact_pref><option>WhatsApp<option>Email<option>Phone</select><button>Submit request</button></form>');break;

case $path==='/client': $u=need(); $h='<h1>Welcome, '.e($u['name']).'</h1><h2>My orders</h2><table><tr><th>Ref<th>Item<th>Total<th>Payment<th>Status</tr>';
  foreach(q('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC',[$u['id']]) as $o)$h.='<tr><td>'.e($o['ref']).'<td>'.e($o['item_name']).'<td>'.price_tag($o['total']).'<td>'.e($o['payment_status']).'<td>'.e(str_replace('_',' ',$o['status']));
  $h.='</table><h2>Custom requests</h2>';foreach(q('SELECT * FROM custom_requests WHERE user_id=? ORDER BY id DESC',[$u['id']]) as $c)$h.='<p>'.e($c['category']).': '.e(substr($c['description'],0,60)).' — <b>'.e($c['status']).'</b></p>'; page('Client Corner',$h);break;

case $path==='/admin': need('admin'); $h='<h1>Admin: orders</h1><table><tr><th>Ref<th>Client<th>Item<th>Channel<th>Payment<th>Status<th></tr>';
  foreach(q('SELECT o.*,u.name cname FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 100') as $o){$s='';foreach($STAGES as $st)$s.='<option'.($st===$o['status']?' selected':'').'>'.$st;
    $h.='<tr><td>'.e($o['ref']).'<td>'.e($o['cname']).'<td>'.e($o['item_name']).'<td>'.$o['channel'].'<td>'.$o['payment_status'].'<td><form method=post action=/admin/status>'.field().'<input type=hidden name=id value='.$o['id'].'><select name=status>'.$s.'</select><button>Update</button></form><td>'.($o['channel']==='whatsapp'&&$o['status']==='pending_review'?'<form method=post action=/admin/status>'.field().'<input type=hidden name=id value='.$o['id'].'><input type=hidden name=status value=confirmed><button>Approve order</button></form>':'');}
  page('Admin',$h.'</table>');break;

case $path==='/admin/status'&&$post: need('admin'); check(); if(!in_array($_POST['status'],$STAGES,true))exit('Invalid status');
  q('UPDATE orders SET status=? WHERE id=?',[$_POST['status'],(int)$_POST['id']]); history((int)$_POST['id'],'Status set to '.$_POST['status']); go('/admin');break;

default: http_response_code(404); page('Not found','<h1>Page not found</h1>');}

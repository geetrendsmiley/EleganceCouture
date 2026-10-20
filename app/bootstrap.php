<?php
foreach (file(__DIR__.'/../.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $l) { if ($l[0]!=='#' && strpos($l,'=')) { [$k,$v]=explode('=',$l,2); $_ENV[trim($k)]=trim($v,'"'); } }
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
$pdo = new PDO($_ENV['DB_DSN'],$_ENV['DB_USER'],$_ENV['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function naira($n){return '₦'.number_format((int)$n);}
function q($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s;}
function csrf(){return $_SESSION['t']??=bin2hex(random_bytes(16));}
function field(){return '<input type="hidden" name="_t" value="'.csrf().'">';}
function check(){if(!hash_equals($_SESSION['t']??'',$_POST['_t']??'')){http_response_code(419);exit('Session expired. Go back and retry.');}}
function user(){return $_SESSION['u']??null;}
function need($role=null){$u=user();if(!$u)go('/login');if($role&&$u['role']!==$role){http_response_code(403);exit('Forbidden');}return $u;}
function go($p){header("Location: $p");exit;}
function history($oid,$note){q('INSERT INTO order_status_history(order_id,actor_id,note) VALUES(?,?,?)',[$oid,user()['id']??null,$note]);}
function page($title,$body){$u=user();echo '<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>'.e($title).' | Elegance Couture</title>
<style>body{font:16px/1.6 system-ui;background:#FAF6EF;color:#2A1A1D;margin:0}main,nav{max-width:960px;margin:auto;padding:16px}nav{display:flex;gap:16px;flex-wrap:wrap;border-bottom:1px solid #E6D9CC;align-items:center}h1,h2{font-family:Georgia,serif;color:#5E1226}a{color:#5E1226}input,select,textarea{display:block;width:100%;padding:9px;margin:4px 0 12px;border:1px solid #ccc}button{background:#5E1226;color:#fff;border:0;padding:11px 20px;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #E6D9CC;text-align:left}</style>
<nav><b>Elegance Couture</b><a href=/>Home</a><a href=/shop>Shop</a><a href=/custom>Custom design</a><a href=/measurements>Measurements</a>'.($u?'<a href=/client>Client Corner</a>'.($u['role']==='admin'?'<a href=/admin>Admin</a>':'').'<form method=post action=/logout>'.field().'<button>Log out</button></form>':'<a href=/login>Log in</a><a href=/register>Register</a>').'<label for=currencySelect>Display currency <select id=currencySelect aria-label="Display currency"><option value=NGN>NGN</option><option value=EUR>EUR</option><option value=USD>USD</option></select></label><small id=currencyStatus role=status>Prices shown in NGN while rates load.</small></nav><main>'.$body.'</main><script src=/currency.js defer></script>';}

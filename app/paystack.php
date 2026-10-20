<?php
function ps($method,$path,$body=null){
  $c=curl_init('https://api.paystack.co'.$path);
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$_ENV['PAYSTACK_SECRET'],'Content-Type: application/json'],CURLOPT_CUSTOMREQUEST=>$method]);
  if($body)curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($body));
  return json_decode(curl_exec($c),true)?:[];
}
// Called from both the browser callback and the webhook. Idempotent: the order is locked and only marked paid once.
function verify_and_mark($reference){
  global $pdo; $d=ps('GET','/transaction/verify/'.rawurlencode($reference))['data']??[];
  $o=q('SELECT * FROM orders WHERE ref=?',[$reference])->fetch();
  if(!$o||($d['status']??'')!=='success'||($d['currency']??'')!=='NGN'||(int)$d['amount']!==$o['total']*100) return false;
  $pdo->beginTransaction();
  $l=q('SELECT payment_status FROM orders WHERE id=? FOR UPDATE',[$o['id']])->fetch();
  if($l['payment_status']!=='paid'){
    q('INSERT INTO payments(order_id,reference,amount,status,paid_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$o['id'],$reference,$o['total'],'success']);
    q("UPDATE orders SET payment_status='paid',status='confirmed' WHERE id=?",[$o['id']]);
    history($o['id'],'Payment verified with Paystack');
  }
  $pdo->commit(); return true;
}

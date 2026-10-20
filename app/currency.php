<?php
function currency_rates(){
  $c=curl_init('https://open.er-api.com/v6/latest/NGN');
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>5]);
  $body=curl_exec($c);
  if($body===false){$error=curl_error($c);curl_close($c);throw new RuntimeException('Exchange-rate request failed: '.$error);}
  $status=curl_getinfo($c,CURLINFO_HTTP_CODE);
  curl_close($c);
  if($status!==200)throw new RuntimeException('Exchange-rate service returned HTTP '.$status);
  $data=json_decode($body,true);
  if(!is_array($data)||($data['result']??'')!=='success'||($data['base_code']??'')!=='NGN'||!is_array($data['rates']??null)){
    throw new RuntimeException('Exchange-rate service returned an invalid response');
  }
  $rates=['NGN'=>1.0];
  foreach($data['rates'] as $code=>$rate){
    if(preg_match('/^[A-Z]{3}$/',(string)$code)&&is_numeric($rate)&&(float)$rate>0)$rates[$code]=(float)$rate;
  }
  return ['base'=>'NGN','rates'=>$rates,'updated_at'=>$data['time_last_update_utc']??null];
}

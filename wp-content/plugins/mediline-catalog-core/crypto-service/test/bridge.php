<?php
// Isolated bridge contract tests: no WordPress boot, remote request, email or live order.
define('ABSPATH',__DIR__);define('MEDILINE_CRYPTO_ENABLED',true);
define('MEDILINE_CRYPTO_MODE',$argv[1]??'testnet');define('MEDILINE_CRYPTO_NAMESPACE','testshop');
define('MEDILINE_CRYPTO_URL','https://watcher.example');define('MEDILINE_CRYPTO_SECRET',str_repeat('a',64));
class WP_Error {public $code;public function __construct($code,$message='',$data=array()){$this->code=$code;}}
class WP_REST_Response {public $data;public function __construct($data){$this->data=$data;}public function header($a,$b){}}
function is_wp_error($x){return $x instanceof WP_Error;}
function wp_parse_url($url,$part){return parse_url($url,$part);}
function wp_json_encode($data,$options=0){return json_encode($data,$options);}
function wc_format_decimal($n,$d){return number_format((float)$n,$d,'.','');}
function wp_safe_remote_request($url,$args){$GLOBALS['remote_calls']++;return array('response'=>array('code'=>200),'body'=>json_encode($GLOBALS['invoice']));}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wc_get_order($id){return $id===10?$GLOBALS['order']:false;}
function absint($x){return abs((int)$x);}
function rest_url($path){return 'https://central.example/wp-json/'.$path;}
class Request {
 private $params,$headers,$body;
 public function __construct($params,$headers=array(),$body=''){$this->params=$params;$this->headers=$headers;$this->body=$body;}
 public function get_param($p){return $this->params[$p]??null;}
 public function get_header($p){return $this->headers[$p]??'';}
 public function get_body(){return $this->body;}
}
class WC_Order {
 public $meta=array('_mediline_payment_choice'=>'usdt_trc20','_mediline_source_store_id'=>'store-one');public $status='on-hold';public $completions=0;public $notes=array();
 public function get_id(){return 10;}public function get_order_key(){return 'valid-test-key';}
 public function get_meta($k,$single=true){return $this->meta[$k]??'';}
 public function get_payment_method(){return 'mediline_usdt_trc20';}
 public function get_total(){return '29.90';}public function get_currency(){return 'USD';}public function get_status(){return $this->status;}
 public function update_meta_data($k,$v){$this->meta[$k]=$v;}public function save(){}public function add_order_note($n){$this->notes[]=$n;}
 public function is_paid(){return $this->completions>0;}
 public function payment_complete($id){$this->completions++;$this->status=Mediline_Catalog_Payments::paid_status('completed',10,$this);}
}
class Mediline_Catalog_API {public static function store($r){return (object)array('store_uuid'=>$r->get_param('store'));}}
$wpdb=new class {public function prepare($q,...$args){return $q;}public function get_var($q){return '1';}};
require dirname(__DIR__,2).'/includes/class-mediline-catalog-payments.php';
function check($ok,$message){if(!$ok)throw new Exception($message);}
$order=new WC_Order();$remote_calls=0;
$invoice=array('id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','reference'=>'testshop:10','asset'=>'USDT_TRC20','network'=>MEDILINE_CRYPTO_MODE,'currency'=>'USD','fiat'=>'29.90','amount'=>'29.900000','remaining'=>'0.000000','received'=>'29.900000','address'=>'T'.str_repeat('A',33),'qr'=>'data:image/png;base64,AAAA','expires'=>time()+100,'revision'=>1,'checked'=>time(),'stale'=>false,'status'=>'paid','transactions'=>array(str_repeat('a',64)));
check(Mediline_Catalog_Payments::order_for(new Request(array('order_id'=>10,'order_key'=>'wrong')))===null,'Invalid key accepted');
check(Mediline_Catalog_Payments::order_for(new Request(array('order_id'=>10,'order_key'=>array('bad'))))===null,'Array key accepted');
$cross=Mediline_Catalog_Payments::store_status(new Request(array('order_id'=>10,'order_key'=>'valid-test-key','store'=>'different-store')));
check(is_wp_error($cross)&&$remote_calls===0,'Cross-store access allowed');
$created=Mediline_Catalog_Payments::checkout_response(array(),$order);
check($created['crypto_payment']['pending']&&$remote_calls===0,'Checkout waits for network');
check(!is_wp_error(Mediline_Catalog_Payments::invoice($order)),'Valid invoice rejected');
check(is_wp_error(Mediline_Catalog_Payments::validate($order,array_merge($invoice,array('network'=>'invalid')))),'Wrong network accepted');
check(is_wp_error(Mediline_Catalog_Payments::validate($order,array_merge($invoice,array('fiat'=>'1.00')))),'Wrong total accepted');
check(is_wp_error(Mediline_Catalog_Payments::validate($order,array_merge($invoice,array('address'=>'T'.str_repeat('B',33))))),'Changed address accepted');
check(is_wp_error(Mediline_Catalog_Payments::validate($order,array_merge($invoice,array('stale'=>null)))),'Missing health flag accepted');
$body=json_encode(array('invoice_id'=>$invoice['id'],'reference'=>'testshop:10'));
$stamp=(string)time();$nonce=str_repeat('b',32);$path='/wp-json/mediline/v1/crypto-events';
$sig=hash_hmac('sha256',implode("\n",array('POST',$path,$stamp,$nonce,hash('sha256',$body))),MEDILINE_CRYPTO_SECRET);
$request=new Request(array('invoice_id'=>$invoice['id'],'reference'=>'testshop:10'),array('x-mediline-time'=>$stamp,'x-mediline-nonce'=>$nonce,'x-mediline-signature'=>$sig),$body);
check(Mediline_Catalog_Payments::authorize_event($request),'Valid HMAC rejected');
check(!Mediline_Catalog_Payments::authorize_event(new Request(array())),'Unsigned event accepted');
$invoice['stale']=true;check(is_wp_error(Mediline_Catalog_Payments::event($request)),'Stale payment accepted');check($order->completions===0,'Stale invoice paid');$invoice['stale']=false;
check(Mediline_Catalog_Payments::event($request)['ok'],'Event failed');check(Mediline_Catalog_Payments::event($request)['ok'],'Repeat event failed');
check($order->completions===(MEDILINE_CRYPTO_MODE==='mainnet'?1:0),'Payment completion not idempotent or testnet marked paid');
if(MEDILINE_CRYPTO_MODE==='mainnet')check($order->status==='processing','Payment marked completed');
echo 'Bridge authorization, mode separation, validation and idempotency: PASS ('.MEDILINE_CRYPTO_MODE.")\n";

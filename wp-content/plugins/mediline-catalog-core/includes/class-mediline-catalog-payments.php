<?php
/** Watch-only crypto payments. Secrets live in server configuration, never theme packages. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Mediline_Catalog_Payments {
 public static function enabled() {
  return defined('MEDILINE_CRYPTO_ENABLED') && MEDILINE_CRYPTO_ENABLED === true;
 }
 public static function mode() { return defined('MEDILINE_CRYPTO_MODE') ? MEDILINE_CRYPTO_MODE : 'testnet'; }
 public static function asset($order) {
  $method=$order->get_meta('_mediline_payment_choice',true) ?: preg_replace('/^mediline_/','',$order->get_payment_method());
  return array('bitcoin'=>'BTC','usdt_trc20'=>'USDT_TRC20')[$method] ?? '';
 }
 public static function init() {
  add_action('rest_api_init',array(__CLASS__,'routes'));
  add_action('woocommerce_thankyou',array(__CLASS__,'receipt'),14);
  add_action('wp_enqueue_scripts',static function(){if(self::enabled() && function_exists('is_order_received_page') && is_order_received_page())self::assets();});
  add_action('admin_menu',array(__CLASS__,'menu'));
  add_filter('woocommerce_payment_complete_order_status',array(__CLASS__,'paid_status'),20,3);
 }
 public static function paid_status($status,$id,$order) {
  return $order && $order->get_meta('_mediline_crypto_invoice',true) ? 'processing' : $status;
 }
 private static function failure() { return new WP_Error('mediline_crypto_unavailable','Crypto payment is temporarily unavailable. Please retry later.',array('status'=>503)); }
 private static function configured() {
  return self::enabled() && defined('MEDILINE_CRYPTO_URL') && defined('MEDILINE_CRYPTO_SECRET') &&
   defined('MEDILINE_CRYPTO_NAMESPACE') && preg_match('/^[a-zA-Z0-9_-]{8,64}$/',MEDILINE_CRYPTO_NAMESPACE) &&
   preg_match('/^[a-f0-9]{64,128}$/',MEDILINE_CRYPTO_SECRET) && in_array(self::mode(),array('mainnet','testnet'),true);
 }
 public static function request($method,$path,$data=null) {
  if(!self::configured())return self::failure();
  $base=rtrim(MEDILINE_CRYPTO_URL,'/');
  if(wp_parse_url($base,PHP_URL_SCHEME)!=='https' || wp_parse_url($base,PHP_URL_QUERY) || wp_parse_url($base,PHP_URL_USER))return self::failure();
  $body=$data===null?'':wp_json_encode($data,JSON_UNESCAPED_SLASHES);
  $stamp=(string)time();$nonce=bin2hex(random_bytes(16));
  $route=(wp_parse_url($base,PHP_URL_PATH) ?: '').$path;
  $signature=hash_hmac('sha256',implode("\n",array($method,$route,$stamp,$nonce,hash('sha256',$body))),MEDILINE_CRYPTO_SECRET);
  $response=wp_safe_remote_request($base.$path,array('method'=>$method,'timeout'=>45,'redirection'=>0,'limit_response_size'=>131072,
   'headers'=>array('Content-Type'=>'application/json','X-Mediline-Time'=>$stamp,'X-Mediline-Nonce'=>$nonce,'X-Mediline-Signature'=>$signature),'body'=>$body));
  if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)return self::failure();
  $result=json_decode(wp_remote_retrieve_body($response),true);
  return is_array($result)?$result:self::failure();
 }
 private static function reference($order) { return MEDILINE_CRYPTO_NAMESPACE.':'.$order->get_id(); }
 public static function validate($order,$data) {
  $statuses=array('awaiting','confirming','partial','paid','overpaid','expired','late_review','reorg_review');
  if(!is_array($data))return self::failure();
  foreach(array('reference','asset','network','currency','fiat','id','status','amount','address','qr','received','remaining') as $field) {
   if(!isset($data[$field])||!is_string($data[$field]))return self::failure();
  }
  if(!is_bool($data['stale']??null)||!is_int($data['checked']??null)||!is_array($data['transactions']??null)||
   !preg_match('/^\d+\.\d{6,8}$/',$data['received'])||!preg_match('/^\d+\.\d{6,8}$/',$data['remaining']))return self::failure();
  foreach($data['transactions'] as $tx){if(!is_string($tx)||!preg_match('/^[a-f0-9]{64}$/',$tx))return self::failure();}
  if(!is_array($data) || ($data['reference']??'')!==self::reference($order) || ($data['asset']??'')!==self::asset($order) ||
   ($data['network']??'')!==self::mode() || ($data['currency']??'')!==$order->get_currency() ||
   ($data['fiat']??'')!==wc_format_decimal($order->get_total(),2) ||
   !preg_match('/^[a-f0-9-]{36}$/',$data['id']??'') || !in_array($data['status']??'',$statuses,true) ||
   !preg_match('/^\d+\.\d{6,8}$/',$data['amount']??'') || !is_int($data['expires']??null) || !is_int($data['revision']??null))return self::failure();
  $saved=$order->get_meta('_mediline_crypto_invoice',true);
  if($saved && $saved!==$data['id'])return self::failure();
  $address=$order->get_meta('_mediline_crypto_address',true);
  if($address && $address!==($data['address']??''))return self::failure();
  if(!preg_match(self::asset($order)==='BTC'?'/^(bc1q|tb1q)[a-z0-9]{38}$/':'/^T[1-9A-HJ-NP-Za-km-z]{33}$/',$data['address']??''))return self::failure();
  if(!preg_match('~^data:image/png;base64,[A-Za-z0-9+/=]+$~',$data['qr']??''))return self::failure();
  return $data;
 }
 public static function invoice($order) {
  if(!self::enabled() || !self::asset($order))return null;
  if(!self::configured())return self::failure();
  $id=$order->get_meta('_mediline_crypto_invoice',true);
  // Never issue payment instructions for paid/cancelled historical orders.
  if(!$id && !in_array($order->get_status(),array('pending','on-hold'),true))return self::failure();
  $result=$id ? self::request('GET','/v1/invoices/'.$id) : self::request('POST','/v1/invoices',array(
   'reference'=>self::reference($order),'asset'=>self::asset($order),'total'=>wc_format_decimal($order->get_total(),2),'currency'=>$order->get_currency()));
  if(is_wp_error($result))return $result;
  $result=self::validate($order,$result);if(is_wp_error($result))return $result;
  if(!$id) {
   $order->update_meta_data('_mediline_crypto_invoice',$result['id']);
   $order->update_meta_data('_mediline_crypto_address',$result['address']);
   $order->update_meta_data('_mediline_crypto_network',$result['network']);
   $order->update_meta_data('_mediline_crypto_amount',$result['amount']);
   $order->save();
  }
  return $result;
 }
 public static function checkout_response($response,$order) {
  if(!self::enabled() || !self::asset($order))return $response;
  // Keep the receipt credentials even on a provider outage: retry this order, never create another.
  // Issue/fetch via the subsequent payment poll; order submission does not wait for blockchain RPC.
  $response['crypto_payment']=array('pending'=>true);
  $response['crypto_order_key']=$order->get_order_key();
  return $response;
 }
 public static function routes() {
  register_rest_route('mediline/v1','/crypto-payment',array('methods'=>'POST','callback'=>array(__CLASS__,'store_status'),'permission_callback'=>array('Mediline_Catalog_API','permission')));
  register_rest_route('mediline/v1','/crypto-receipt',array('methods'=>'POST','callback'=>array(__CLASS__,'receipt_status'),'permission_callback'=>array(__CLASS__,'poll_permission')));
  register_rest_route('mediline/v1','/crypto-events',array('methods'=>'POST','callback'=>array(__CLASS__,'event'),'permission_callback'=>array(__CLASS__,'authorize_event')));
 }
 public static function order_for($request) {
  if(!is_scalar($request->get_param('order_id'))||!is_string($request->get_param('order_key')))return null;
  $id=absint($request->get_param('order_id'));$key=$request->get_param('order_key');
  if(!$id || strlen($key)>100)return null;
  $order=wc_get_order($id);
  return $order && $key && hash_equals($order->get_order_key(),$key) ? $order : null;
 }
 private static function response($order) {
  if(!$order)return new WP_Error('mediline_crypto_not_found','Payment not found.',array('status'=>404));
  $data=self::invoice($order);
  if(is_wp_error($data))return $data;
  if(!$data)return new WP_Error('mediline_crypto_disabled','Online crypto payment is unavailable.',array('status'=>409));
  $response=new WP_REST_Response($data);$response->header('Cache-Control','no-store');return $response;
 }
 public static function store_status($request) {
  $order=self::order_for($request);
  $store=Mediline_Catalog_API::store($request);
  if(!$store || !$order || $order->get_meta('_mediline_source_store_id',true)!==$store->store_uuid)return new WP_Error('mediline_crypto_not_found','Payment not found.',array('status'=>404));
  return self::response($order);
 }
 public static function receipt_status($request) { return self::response(self::order_for($request)); }
 public static function poll_permission() {
  $key='ml_crypto_poll_'.hash('sha256',$_SERVER['REMOTE_ADDR']??'');$count=(int)get_transient($key);
  if($count>=60)return new WP_Error('mediline_crypto_rate','Please wait before checking again.',array('status'=>429));
  set_transient($key,$count+1,MINUTE_IN_SECONDS);return true;
 }
 public static function authorize_event($request) {
  if(!self::configured())return false;
  $stamp=$request->get_header('x-mediline-time');$nonce=$request->get_header('x-mediline-nonce');$sig=$request->get_header('x-mediline-signature');
  if(!preg_match('/^\d{10}$/',$stamp)||abs(time()-(int)$stamp)>120||!preg_match('/^[a-f0-9]{32}$/',$nonce)||!preg_match('/^[a-f0-9]{64}$/',$sig))return false;
  $path=wp_parse_url(rest_url('mediline/v1/crypto-events'),PHP_URL_PATH);
  $expected=hash_hmac('sha256',implode("\n",array('POST',$path,$stamp,$nonce,hash('sha256',$request->get_body()))),MEDILINE_CRYPTO_SECRET);
  // Replays have no effect: event() serializes by order and re-reads authoritative current state.
  return hash_equals($expected,$sig);
 }
 public static function event($request) {
  $reference=(string)$request->get_param('reference');
  if(!preg_match('/^'.preg_quote(MEDILINE_CRYPTO_NAMESPACE,'/').':(\d+)$/',$reference,$matches))return self::failure();
  $order=wc_get_order((int)$matches[1]);if(!$order)return self::failure();
  global $wpdb;$lock='ml_crypto_'.hash('sha256',$reference);
  $lock=substr($lock,0,64);
  if('1'!==(string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)',$lock)))return self::failure();
  try {
   $order=wc_get_order($order->get_id());
   $data=self::invoice($order);if(is_wp_error($data)||!$data||!empty($data['stale']))return self::failure();
   if(($request->get_param('invoice_id')??'')!==$data['id'])return self::failure();
   $old=(string)$order->get_meta('_mediline_crypto_status',true);
   $order->update_meta_data('_mediline_crypto_status',$data['status']);
   $order->update_meta_data('_mediline_crypto_received',$data['received']);
   $collection=$data['collection']??null;
   if(is_array($collection) && in_array($collection['status']??'',array('dry_run','activation_pending','funding_pending','sweep_pending','complete','review'),true)) {
    $previous=$order->get_meta('_mediline_crypto_collection',true);
    $order->update_meta_data('_mediline_crypto_collection',$collection['status']);
    foreach(array('funding_tx','sweep_tx') as $field) {
     if(preg_match('/^[a-f0-9]{64}$/',$collection[$field]??''))$order->update_meta_data('_mediline_crypto_'.$field,$collection[$field]);
    }
    if($previous!==$collection['status'])$order->add_order_note('USDT collection: '.$collection['status'].'.');
   }
   $order->update_meta_data('_mediline_crypto_transactions',array_values(array_unique(array_filter((array)$data['transactions'],static function($id){return is_string($id)&&preg_match('/^[a-f0-9]{64}$/',$id);} ))));
   $order->save();
   if($old!==$data['status'])$order->add_order_note('Crypto payment: '.$data['status'].' ('.$data['network'].').');
   if(in_array($data['status'],array('paid','overpaid'),true) && !$order->is_paid() && self::mode()==='mainnet') {
    if(in_array($order->get_status(),array('pending','on-hold'),true))$order->payment_complete($data['transactions'][0]??'');
    elseif(!$order->get_meta('_mediline_crypto_manual_review',true)) {
     $order->update_meta_data('_mediline_crypto_manual_review',true);$order->save();
     $order->add_order_note('Confirmed crypto funds require manual review: order is no longer payable.');
    }
   }
   if($data['status']==='reorg_review' && $old!=='reorg_review')$order->add_order_note('URGENT: blockchain payment changed after confirmation. Review fulfillment and CRM manually.');
   // A retryable CRM note update; never marks the deal Won and never creates a PAP sale here.
   if(class_exists('Mediline_Integrations_Pipedrive') && Mediline_Integrations_Pipedrive::configured()) {
    $deal=(int)$order->get_meta('_mediline_pipedrive_deal_id',true);
    if($deal) {
     $client=new Mediline_Integrations_Pipedrive(mediline_integrations_settings(),mediline_integrations_secret('pipedrive_api_token'));
     $note=$client->sync_order_note($order,$deal);if(is_wp_error($note))return self::failure();
    }
   }
   return array('ok'=>true);
  }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
 }
 public static function assets() {
  wp_enqueue_script('mediline-crypto-ui',MEDILINE_CATALOG_URL.'assets/crypto/payment.js',array(),MEDILINE_CATALOG_VERSION,true);
  wp_enqueue_style('mediline-crypto-ui',MEDILINE_CATALOG_URL.'assets/crypto/payment.css',array(),MEDILINE_CATALOG_VERSION);
  wp_localize_script('mediline-crypto-ui','MedilineCryptoConfig',array('endpoint'=>rest_url('mediline/v1/crypto-receipt'),'lang'=>substr(determine_locale(),0,2)));
 }
 public static function receipt($id) {
  if(!self::enabled())return;$order=wc_get_order($id);
  if(!$order||!self::asset($order))return;
  $key=isset($_GET['key'])?sanitize_text_field(wp_unslash($_GET['key'])):'';
  if(!$key||!hash_equals($order->get_order_key(),$key))return;
  self::assets();
  $ref=array('order_id'=>$order->get_id(),'crypto_order_key'=>$key,'crypto_payment'=>array('pending'=>true),'language'=>$order->get_meta('_mediline_language',true)?:'en');
  echo '<div class="mediline-crypto-receipt" data-payment="'.esc_attr(wp_json_encode($ref)).'"></div>';
 }
 public static function menu() { add_submenu_page('woocommerce','Crypto payments','Crypto payments','manage_woocommerce','mediline-crypto',array(__CLASS__,'admin')); }
 public static function admin() {
  if(!current_user_can('manage_woocommerce'))return;
  echo '<div class="wrap"><h1>Crypto payments</h1><p>Mode: '.esc_html(self::enabled()?self::mode():'Disabled').'. Private keys are not stored by WordPress.</p>';
  $health=self::request('GET','/v1/status');
  echo '<p>'.esc_html(is_wp_error($health)?'Service not configured or unavailable.':wp_json_encode($health)).'</p>';
  echo '<p>Collection is separate from payment. Review means automation stopped: inspect the existing transaction before retrying. Do not send TRX again blindly.</p>';
  echo '<table class="widefat"><thead><tr><th>Order</th><th>Network</th><th>Status</th><th>Amount / received</th><th>USDT collection</th></tr></thead><tbody>';
  foreach(wc_get_orders(array('limit'=>50,'orderby'=>'date','order'=>'DESC','meta_key'=>'_mediline_crypto_invoice','meta_compare'=>'EXISTS')) as $order) {
   echo '<tr><td><a href="'.esc_url($order->get_edit_order_url()).'">'.esc_html($order->get_order_number()).'</a></td><td>'.esc_html($order->get_meta('_mediline_crypto_network',true)).'</td><td>'.esc_html($order->get_meta('_mediline_crypto_status',true)?:'awaiting').'</td><td>'.esc_html($order->get_meta('_mediline_crypto_amount',true).' / '.$order->get_meta('_mediline_crypto_received',true)).'</td><td>'.esc_html($order->get_meta('_mediline_crypto_collection',true)?:'—').'</td></tr>';
  }
  echo '</tbody></table></div>';
 }
}

<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-mediline-integrations-pipedrive.php';
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function wp_strip_all_tags($s) { return strip_tags($s); }
class Note_Order {
 public $meta = array();
 public function __call($name, $args) {
  $values = array('get_id'=>60,'get_order_number'=>60,'get_payment_method_title'=>'Bank transfer','get_payment_method'=>'mediline_bank_wire','get_status'=>'on-hold','get_currency'=>'USD','get_subtotal'=>30,'get_discount_total'=>0,'get_shipping_total'=>0,'get_total_tax'=>0,'get_total'=>30,'get_shipping_method'=>'Delivery','get_customer_note'=>'<script>test</script>','get_items'=>array());
  return $values[$name];
 }
 public function get_address($type) { return array('first_name'=>'Test', 'address_1'=>'Example Street', 'email'=>'test@example.test'); }
 public function get_meta($key,$single=true) { return $this->meta[$key] ?? ''; }
 public function update_meta_data($key,$value) { $this->meta[$key]=$value; }
 public function save() {}
}
$client = new Mediline_Integrations_Pipedrive(array('pipedrive_api_base'=>'https://test.pipedrive.com'), 'test-token');
$order = new Note_Order();
Test_Suite::run('Checkout details include payment/address and escape customer HTML', function() use ($order) {
 $html=Mediline_Integrations_Pipedrive::order_note_content($order);
 Test_Suite::assert_contains('Bank transfer',$html);
 Test_Suite::assert_contains('Example Street',$html);
 Test_Suite::assert_contains('&lt;script&gt;',$html);
 Test_Suite::assert_not_contains('<script>',$html);
});
Test_Suite::run('Retry reconciles remote note after lost create response', function() use ($client,$order) {
 test_http_reset();
 test_http_queue_json(array('data'=>array()));
 $GLOBALS['test_http_responses'][]=new WP_Error('timeout','Lost response');
 Test_Suite::assert_true(is_wp_error($client->sync_order_note($order,4)));
 $posted=json_decode($GLOBALS['test_http_requests'][1]['args']['body'],true);
 test_http_queue_json(array('data'=>array(array('id'=>23,'content'=>$posted['content']))));
 test_http_queue_json(array('data'=>array('id'=>23)));
 Test_Suite::assert_same(23,$client->sync_order_note($order,4));
 Test_Suite::assert_same('PUT',$GLOBALS['test_http_requests'][3]['args']['method']);
 Test_Suite::assert_same(1,$posted['pinned_to_deal_flag']);
});
Test_Suite::run('Read failure does not create a duplicate note', function() use ($client,$order) {
 test_http_reset();
 test_http_queue_json(array('error'=>'Unavailable'),503);
 Test_Suite::assert_true(is_wp_error($client->sync_order_note($order,4)));
 Test_Suite::assert_same(1,count($GLOBALS['test_http_requests']));
});
Test_Suite::finish();

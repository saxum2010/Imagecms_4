<?php

/*
* Treasurer payment module
*
* author: http://idfox.ru/
* more info at: https://kaznachey.ua/

*/

class TreasurerSystem extends BasePaymentProcessor {

public $order_id = '';
public $template_vars = array();

public $URL_CreatePayment = 'http://payment.kaznachey.net/api/PaymentInterface/CreatePayment';
public $URL_MerchatInformation = 'http://payment.kaznachey.net/api/PaymentInterface/GetMerchatInformation';

public function __construct() {

$this->order = ShopCore::app()->SPaymentSystems->getOrder();

}

//Сохранение параметров
public function saveSettings(SPaymentMethods $paymentMethod) {

$saveKey = $paymentMethod->getId() . '_TreasurerData';
ShopCore::app()->SSettings->set($saveKey, serialize($_POST['robo']));
return true;

}

//Загрузка параметров
protected function loadSettings() {

$settingsKey = $this->paymentMethod->getId() . '_TreasurerData';
$data = unserialize(ShopCore::app()->SSettings->$settingsKey);
if ($data === false) $data = array();
return array_map('encode', $data);

}

//ХЗ для чего
public function add_array($arr) {

if (count($arr) > 0) {
$this->template_vars = array_merge($this->template_vars, $arr);
return TRUE;
}

return FALSE;

}

//Вспомогательное
function Treasurer_Request ($req_uri, $req_data) {

$req_data = json_encode($req_data);

if (function_exists('curl_init')) {

$fp_init = curl_init();
curl_setopt($fp_init, CURLOPT_URL, $req_uri);
curl_setopt($fp_init, CURLOPT_POST, true);
curl_setopt($fp_init, CURLOPT_POSTFIELDS, $req_data);
curl_setopt($fp_init, CURLOPT_RETURNTRANSFER, true);

curl_setopt($fp_init, CURLOPT_HTTPHEADER, array(
'Expect: ',
'Content-Type: application/json; charset=UTF-8',
'Content-Length: '. strlen($req_data))
);

$fp_res = curl_exec($fp_init);
curl_close($fp_init);

}

else {

$opts = array(
'http'=>array(
'method' => 'POST',
'header' => 'Content-Length: ' . strlen($req_data) . "\r\nContent-Type: application/json\r\n",
'content' => $req_data,
)
);

$context = stream_context_create($opts); 
$fp_res = @file_get_contents($req_uri, 0, $context);

}

return $fp_res;

}

//Параметры (в админке)
public function getAdminForm() {

$data = $this->loadSettings();

$form = '

<div class="control-group">
<label class="control-label" for="inputRecCount">MerchantGuid</label>
<div class="controls">
<input type="text" name="robo[MerchantGuid]" value="' . $data['MerchantGuid'] . '"  />
</div>
</div>

<div class="control-group">
<label class="control-label" for="inputRecCount">MerchantSecretKey</label>
<div class="controls">
<input type="text" name="robo[MerchantSecretKey]" value="' . $data['MerchantSecretKey'] . '"/>
</div>
</div>

';

return $form;

}

public function getPSI() {

	$data = $this->loadSettings();
	$req_data = array(
	'MerchantGuid' => $data ['MerchantGuid'],
	'Signature' => md5($data ['MerchantGuid'] . $data ['MerchantSecretKey'])
	);

	$fp_res = $this->Treasurer_Request($this->URL_MerchatInformation, $req_data);
	$Form_DATA = '';
	$fp_res = json_decode($fp_res, 1);
	$PaySystems_a = $fp_res ['PaySystems'];

	foreach ($PaySystems_a as $PaySystem) {
		return $PaySystem['Id'];
	}
}

//Создание формы для оплаты
public function getForm() {

$k_Result = $_GET ['Result'];
$k_OrderId = $_GET ['OrderId'];

if ($k_Result && $k_OrderId) {

ob_end_clean();

header("Content-Type: text/html; charset=utf-8");

if ($k_Result == 'success') $mes = "<p class=\"valid\">Спасибо! Ваш заказ #$k_OrderId оплачен.</p>";
if ($k_Result == 'failed') $mes = "<p class=\"invalid\">Платеж по заказу #$k_OrderId не прошел, либо находится в обработке.</p>";

?>

<html>
	<head>
		<style>
			body{background-color: #527496; font: normal 13px Verdana,sans-serif;}
			.message_container{background-color: #fff; width: 50%; text-align:center; margin: auto; margin-top: 100px; padding: 50px;}
			.valid {color: green;}
			.invalid {color: red;}
		</style>
	</head>
	
	<body>
		<div class='message_container'> <h4><?=$mes;?></h4> 
		<input type='button' value=' Закрыть ' onCLick="location='http://<?=$_SERVER['HTTP_HOST'];?>';">
		</div>
	
	</body>
</html>

<?

exit;

}

$order_key = $this->order->getKey();
$paymentMethod = $this->paymentMethod->getId();

$data = $this->loadSettings();

$req_data = array(
'MerchantGuid' => $data ['MerchantGuid'],
'Signature' => md5($data ['MerchantGuid'] . $data ['MerchantSecretKey'])
);

$fp_res = $this->Treasurer_Request($this->URL_MerchatInformation, $req_data);

$Form_DATA = '';
$fp_res = json_decode($fp_res, 1);
$PaySystems_a = $fp_res ['PaySystems'];

foreach ($PaySystems_a as $PaySystem) {

if ($Form_DATA) $checked = '';
else $checked = ' checked="checked"';

$PS_ID = $PaySystem ['Id'];
$PS_Name = $PaySystem ['PaySystemName'];

$Form_DATA .= "<input type=\"radio\" name=\"PSystem_ID\" value=\"$PS_ID\"$checked> $PS_Name <br/>";

}

$Form_DATA .= "<input type=\"hidden\" name=\"pm\" value=\"$paymentMethod\"><br/>";

$this->render('Treasurer', array(
'action' => shop_url("cart/view/$order_key/"),
'Form_DATA' => $Form_DATA,
));

return;

}

//Инициализация оплаты и callBack ответ
public function processPayment() {

$PSystem_ID = $_GET ['PSystem_ID'];

$order_key = $this->order->getKey();
$order_id = $this->order->getId();
$paymentMethod = $this->paymentMethod->getId();
$CurrencyId = $this->paymentMethod->getCurrencyId();

$data = $this->loadSettings();
$MerchantGuid = $data ['MerchantGuid'];
$MerchantSecretKey = $data ['MerchantSecretKey'];

$currencies = SCurrenciesQuery::create()->find();
foreach ($currencies as $c) $this->currencies[$c->getId()] = $c;
$currency = $this->currencies[$CurrencyId];

$currency_Code = $currency->code;
$currency_Rate = $currency->getRate();

$TotalPrice = 0;
$PSystem_ID = isset($PSystem_ID)?$PSystem_ID:$this->getPSI();
$send_data ['SelectedPaySystemId'] = $PSystem_ID;

$db = \CI::$APP->db;
$db->where('shop_orders_products.order_id', $order_id);
$db->join('shop_products', 'shop_orders_products.product_id=shop_products.id', 'left outer');

$Products = $db->get('shop_orders_products')->result_array();
foreach ($Products as $orderProduct) {
	$product_item = array();
	$product_item ['ImageUrl'] = productImageUrl($orderProduct['mainModImage']);
	$product_item ['ProductItemsNum'] = number_format($orderProduct['quantity'], 2, '.', '');
	$product_item ['ProductName'] = $orderProduct['product_name'];
	$product_item ['ProductPrice'] = number_format($orderProduct['price'] * $currency_Rate, 2, '.', '');
	$product_item ['ProductId'] = $orderProduct['product_id'];

	$send_data ['Products'][] = $product_item;
	$TotalPrice += $product_item ['ProductPrice'] * $product_item ['ProductItemsNum'];
	$product_count += $product_item ['ProductItemsNum'];
}

$TotalPrice = number_format($TotalPrice, 2, '.', '');
$product_count = number_format($product_count, 2, '.', '');

//Инициализация
if ($PSystem_ID && !$_GET['back']) {

if ($user_id = $this->order->getUserId()) {
    $profile = SUserProfileQuery::create()->filterById($user_id)->findone();
}else{
	$user_id = 1;
}

$send_data ['PaymentDetails']['MerchantInternalPaymentId'] = $order_id;
$send_data ['PaymentDetails']['MerchantInternalUserId'] = $user_id;
$send_data ['PaymentDetails']['EMail'] = $this->order->user_email;
$send_data ['PaymentDetails']['PhoneNumber'] = $profile->getPhone();
$send_data ['PaymentDetails']['Description'] = "Оплата заказа $order_id";
$send_data ['PaymentDetails']['DeliveryType'] = $this->order->getSDeliveryMethods()->getName();
$send_data ['PaymentDetails']['CustomMerchantInfo'] = '';

$send_data ['PaymentDetails']['StatusUrl'] = shop_url("cart/view/$order_key?back=1&pm=$paymentMethod");
$send_data ['PaymentDetails']['ReturnUrl'] = shop_url("cart/view/$order_key?back=1");

$send_data ['PaymentDetails']['BuyerLastname'] = '';
$send_data ['PaymentDetails']['BuyerFirstname'] = $this->order->user_full_name;
$send_data ['PaymentDetails']['BuyerPatronymic'] = '';
$send_data ['PaymentDetails']['BuyerStreet'] = $profile->getAddress();
$send_data ['PaymentDetails']['BuyerCity'] = '';
$send_data ['PaymentDetails']['BuyerZone'] = '';
$send_data ['PaymentDetails']['BuyerZip'] = '';
$send_data ['PaymentDetails']['BuyerCountry'] = '';

$send_data ['PaymentDetails']['DeliveryLastname'] = '';
$send_data ['PaymentDetails']['DeliveryFirstname'] = $this->order->user_full_name;
$send_data ['PaymentDetails']['DeliveryPatronymic'] = '';
$send_data ['PaymentDetails']['DeliveryStreet'] = $profile->getAddress();
$send_data ['PaymentDetails']['DeliveryCity'] = '';
$send_data ['PaymentDetails']['DeliveryZone'] = '';
$send_data ['PaymentDetails']['DeliveryZip'] = '';
$send_data ['PaymentDetails']['DeliveryCountry'] = '';

$signature_s = 
$MerchantGuid . 
$TotalPrice . 
$product_count . 
$send_data ['PaymentDetails']['MerchantInternalUserId'] . 
$send_data ['PaymentDetails']['MerchantInternalPaymentId'] . 
$PSystem_ID . 
$data ['MerchantSecretKey'];

if (stristr($currency_Code, 'R') === 0) $currency_Code = 'RUB';
elseif (stristr($currency_Code, 'UA') === 0) $currency_Code = 'UAH';
elseif (stristr($currency_Code, 'US') === 0) $currency_Code = 'USD';
elseif (stristr($currency_Code, 'E') === 0) $currency_Code = 'EUR';

$send_data ['Signature'] = md5($signature_s);
$send_data ['MerchantGuid'] = $MerchantGuid;
$send_data ['Currency'] = $currency_Code;

$res = $this->Treasurer_Request($this->URL_CreatePayment, $send_data);
$data = json_decode($res, 1);

$ErrorCode = $data ['ErrorCode'];
$ExternalForm = $data ['ExternalForm'];
if ($ErrorCode) die("ErrorCode=$ErrorCode");

$ExternalForm = base64_decode($ExternalForm);

die($ExternalForm);

}else {//CallBack оплаты

$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : file_get_contents('php://input');

$req_data = json_decode($HTTP_RAW_POST_DATA, 1);
$MerchantInternalPaymentId = abs(intval($req_data ['MerchantInternalPaymentId']));
$Signature = strtoupper($req_data ['Signature']);
$Sum = $req_data ['Sum'];
$ErrorCode = $req_data ['ErrorCode'];
$MerchantInternalUserId = $req_data ['MerchantInternalUserId'];
$CustomMerchantInfo = $req_data ['CustomMerchantInfo'];

if (!$MerchantInternalPaymentId) return false;
if ($ErrorCode) return false;

$Sum = number_format($Sum, 2, '.', ''); //857.00 => 857 WTF!!!

$signature_true = strtoupper(md5($ErrorCode . $order_id . $MerchantInternalUserId . $Sum . $CustomMerchantInfo . $MerchantSecretKey));
if ($Signature != $signature_true) return false;

if ($this->order->getPaid() == true) return ERROR_ORDER_PAID_BEFORE;

$this->setOrderPaid();

die("OK{$order_id}");

}

}

}

?>
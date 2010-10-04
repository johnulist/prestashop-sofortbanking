<?php
/**
 * $Id$
 *
 * sofortueberweisung Module
 *
 * Copyright (c) 2009 touchDesign
 *
 * @category Payment
 * @version 0.7
 * @copyright 19.08.2009, touchDesign
 * @author Christoph Gruber, <www.touchdesign.de>
 * @link http://www.touchdesign.de/loesungen/prestashop/sofortueberweisung.htm
 * @link http://www.homepage-community.de/index.php?topic=569.0
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module directebanking
 *
 */

require dirname(__FILE__).'/../../config/config.inc.php';
require dirname(__FILE__).'/sofortueberweisung.php';

$su = new Sofortueberweisung();

$orderState = Configuration::get('SOFORTUEBERWEISUNG_OS_ERROR');
$password = Configuration::get('SOFORTUEBERWEISUNG_NOTIFY_PW') 
	? Configuration::get('SOFORTUEBERWEISUNG_NOTIFY_PW') 
	: Configuration::get('SOFORTUEBERWEISUNG_PROJECT_PW');

$reqData = array('transaction' => $_POST['transaction'] , 'user_id' => $_POST['user_id'] , 
	'project_id' => $_POST['project_id'] , 'sender_holder' => $_POST['sender_holder'] , 
	'sender_account_number' => $_POST['sender_account_number'] , 'sender_bank_code' => $_POST['sender_bank_code'] , 
	'sender_bank_name' => $_POST['sender_bank_name'] , 'sender_bank_bic' => $_POST['sender_bank_bic'] , 
	'sender_iban' => $_POST['sender_iban'] , 'sender_country_id' => $_POST['sender_country_id'] , 
	'recipient_holder' => $_POST['recipient_holder'] , 'recipient_account_number' => $_POST['recipient_account_number'] , 
	'recipient_bank_code' => $_POST['recipient_bank_code'] , 'recipient_bank_name' => $_POST['recipient_bank_name'] , 
	'recipient_bank_bic' => $_POST['recipient_bank_bic'] , 'recipient_iban' => $_POST['recipient_iban'] , 
	'recipient_country_id' => $_POST['recipient_country_id'] , 'international_transaction' => $_POST['international_transaction'] , 
	'amount' => $_POST['amount'] , 'currency_id' => $_POST['currency_id'] , 'reason_1' => $_POST['reason_1'] , 
	'reason_2' => $_POST['reason_2'] , 'security_criteria' => $_POST['security_criteria'] , 
	'user_variable_0' => $_POST['user_variable_0'] , 'user_variable_1' => $_POST['user_variable_1'] , 
	'user_variable_2' => $_POST['user_variable_2'] , 'user_variable_3' => $_POST['user_variable_3'] , 
	'user_variable_4' => $_POST['user_variable_4'] , 'user_variable_5' => $_POST['user_variable_5'] , 
	'created' => $_POST['created'] , 'project_password' => $password);

$cart = new Cart(intval($_POST['user_variable_1']));
if($_POST['hash'] != sha1(implode('|', $reqData))){
	echo($su->l('Fatal Error (1)'));
}elseif(!is_object($cart) || !$cart){
	echo($su->l('Fatal Error (2)'));
}else{
	$orderState = Configuration::get('SOFORTUEBERWEISUNG_OS_ACCEPTED');
}

$su->validateOrder($cart->id, $orderState, floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', '')), 
	$su->displayName, $su->l('Sofortueberweisung Transaction ID: ').$_POST['transaction']);

?>
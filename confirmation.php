<?php
/**
 * $Id$
 *
 * sofortueberweisung Module
 *
 * Copyright (c) 2009 touchDesign
 *
 * @category Payment
 * @version 0.9
 * @copyright 19.08.2009, touchDesign
 * @author Christoph Gruber, <www.touchdesign.de>
 * @link http://www.touchdesign.de/loesungen/prestashop/sofortueberweisung.htm
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module directebanking
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@touchdesign.de so we can send you a copy immediately.
 *
 */

require dirname(__FILE__).'/../../config/config.inc.php';
require dirname(__FILE__).'/sofortueberweisung.php';
require_once dirname(__FILE__).'/lib/touchdesign.php';

$touchDirectEBank = new Sofortueberweisung();

$order_id = Order::getOrderByCartId(intval($_GET['user_variable_1']));

$order = new Order($order_id);

touchdesign::redirect(__PS_BASE_URI__ . 'order-confirmation.php','id_cart=' . $order->id_cart 
  . '&id_module=' . $touchDirectEBank->id . '&id_order=' . $order_id
  . '&key='.$order->secure_key);
  
?>
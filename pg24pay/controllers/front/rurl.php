<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of payment
 *
 * @author 24-pay
 */


include_once 'modules/pg24pay/core/pg24pay_order_from_order.php';

class Pg24payRurlModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $orderId = null;
        $orderRef = null;
        
        if (isset($_GET['MsTxnId'])){
            $cartId = $_GET['MsTxnId'];
            $orderId = Order::getIdByCartId((int)$cartId);


            if ($orderId) {
                $order = new Order($orderId);
                if (Validate::isLoadedObject($order) && $order->module === 'pg24pay' && (int) $order->id_customer === (int) $this->context->customer->id && $this->context->customer->id) {
                    $orderRef = $order->reference;
                } else {
                    $orderId = null;
                }
            }
        }
        
        $this->context->smarty->assign(array(
            'PAY24_TEMP' => $_GET,
            'PAY24_REPAY' => Configuration::get('PAY24_REPAY'),
            'PAY24_ORDER' => $orderId,
            'PAY24_ORDER_REF' => $orderRef,
            'PAY24_CAN_REPAY' => $orderId ? Pg24payOrderFromOrder::isRepayable($orderId) : false,

        ));
        
        $this->setTemplate('module:pg24pay/views/templates/front/rurl.tpl');
	}	
	

}
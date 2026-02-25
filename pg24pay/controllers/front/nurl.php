<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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



class Pg24payNurlModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();

        $params = Tools::getValue('params');

        if (!$params) {
            echo "NO PARAMS!";
            $this->setTemplate('module:pg24pay/views/templates/front/empty.tpl');
            return;
        }

        $this->processPaymentNotification($params);
        $this->setTemplate('module:pg24pay/views/templates/front/empty.tpl');
    }

    private function processPaymentNotification($params)
    {
        include_once 'modules/pg24pay/core/pg24pay_nurl.php';

        $nurl = new Pg24payNurl($params);

        if (!$nurl->validateSign()) {
            echo "BAD NURL SIGN!";
            return;
        }

        $cartId = $nurl->get24Id();
        $orderId = Order::getIdByCartId((int)$cartId);

        $this->logPaymentIfEnabled($cartId, $orderId);

        if ($orderId) {
            $this->handleExistingOrder($orderId, $cartId, $nurl);
        } else {
            $this->handleNewOrder($cartId, $nurl);
        }
    }

    private function logPaymentIfEnabled($cartId, $orderId)
    {
        if (Configuration::get('PAY24_LOG') != "1") {
            return;
        }

        $income = "PAYMENT FROM CART " . $cartId . "\n\r" . print_r($_POST, true);

        if ($orderId) {
            $orderObj = new Order($orderId);
            $income .= "\n\r\n\r\n\r INFORMATIONS ORDER ID: " . $orderId .
                " STATUS: " . $orderObj->current_state;
            Logger::addLog("pg24pay: " . $income, 1, null, "Order", $orderId);
        } else {
            Logger::addLog("pg24pay: " . $income, 1, null, "Cart", $cartId);
        }
    }

    private function handleExistingOrder($orderId, $cartId, $nurl)
    {
        $orderObj = new Order($orderId);
        $orderStatus = $orderObj->current_state;
        $pendingStatus = Configuration::get('PAY24_PENDING');
        $failStatus = Configuration::get('PAY24_FAIL');

        if ($orderStatus == $pendingStatus || $orderStatus == $failStatus) {
            $this->updateOrderStatus($orderId, $orderObj, $nurl->result);
        }
        else {
            Logger::addLog("pg24pay: Order already processed, cartId: ".$cartId.", orderId: ".$orderId.", status: ".$orderStatus, 1, null, "Order", $orderId);
            header('HTTP/1.1 500 ORDER ALREADY CONFIRMED');
            exit();
        }
    }

    private function handleNewOrder($cartId, $nurl)
    {
        $result = $nurl->result;

        if ($result == "OK") {
            $orderId = $this->confirmOrder($cartId, $result);
            echo "Result: " . $result . "\n";
            echo "ORDER ID: " . $orderId . "\n";
            echo "24-pay ID: " . $cartId . "\n";
            die("WHY?");
        }

        if ($result == "PENDING") {
            $this->confirmOrder($cartId, $result);
            return;
        }

        if ($result == "FAIL" && Configuration::get('PAY24_REPAY') == 1) {
            $this->confirmOrder($cartId, $result);
        }
    }

    private function updateOrderStatus($orderId, $orderObj, $result)
    {
        if ($result == "OK") {
            $orderObj->setInvoice(true);

            $history = new OrderHistory();
            $history->id_order = (int)$orderId;
            $history->changeIdOrderState(
                Configuration::get('PAY24_OK'),
                (int)$orderId
            );
            $history->addWithemail(true);
            return;
        }

        if ($result == "FAIL") {
            $history = new OrderHistory();
            $history->id_order = (int)$orderId;
            $history->changeIdOrderState(
                Configuration::get('PAY24_FAIL'),
                $orderId
            );
        }
    }

	private function confirmOrder($cartId, $result){

		$cart = new Cart($cartId);
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'pg24pay')
			{
				$authorized = true;
				break;
			}

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');
        $context = Context::getContext();

        $currency = new Currency($cart->id_currency);
        $context->cart = $cart;
        $context->customer = $customer;
        $context->currency = $currency;
        $context->language = new Language((int)$cart->id_lang);
        $context->shop = new Shop((int)$cart->id_shop);

		$total = (float)$cart->getOrderTotal(true, Cart::BOTH, null, null, false);
		$mailVars = array(
			'{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
			'{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
			'{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
		);

		if ($result == "OK")
			$this->module->validateOrder($cart->id, Configuration::get('PAY24_OK'), $total, $this->module->displayName, NULL, NULL, (int)$currency->id, false, $customer->secure_key);
		else if ($result == "FAIL"){
			$this->module->validateOrder($cart->id, Configuration::get('PAY24_FAIL'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
		}
		else if ($result == "PENDING")
			$this->module->validateOrder($cart->id, Configuration::get('PAY24_PENDING'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);

		return $this->module->currentOrder;
	}
	
}
<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * Class EMerchantPay_Genesis_Helper_Data
 *
 * Helper functions for eMerchantPay Direct / Checkout
 */
class EMerchantPay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
    const SECURE_TRANSCTION_TYPE_SUFFIX = "3D";

    const RAW_DETAILS_TRANSACTION_TYPE = 'transaction_type';
    const RAW_DETAILS_TERMINAL_TOKEN = 'terminal_token';

    /**
     * Include Genesis library
     *
     * @return void
     */
    public function initLibrary()
    {
        if (!class_exists('\Genesis\Genesis', false)) {
            // @codingStandardsIgnoreStart
            include Mage::getBaseDir('lib') . DS . 'Genesis' . DS . 'vendor' . DS . 'autoload.php';
            // @codingStandardsIgnoreEnd
        }
    }
    
    /**
     * Check whether Genesis is initialized and init if not
     *
     * @param string $model Name of the model, for which we query settings
     *
     * @return void
     */
    public function initClient($model)
    {
        $this->initLibrary();

        \Genesis\Config::setEndpoint(
            \Genesis\API\Constants\Endpoints::EMERCHANTPAY
        );

        \Genesis\Config::setUsername(
            $this->getConfigData(
                $model,
                'genesis_username'
            )
        );

        \Genesis\Config::setPassword(
            $this->getConfigData(
                $model,
                'genesis_password'
            )
        );

        \Genesis\Config::setEnvironment(
            $this->getConfigData(
                $model,
                'genesis_environment'
            )
        );

        \Genesis\Config::setToken(
            $this->getConfigData($model, 'genesis_token') ?: ""
        );
    }

    /**
     * Get Module Configuration Key
     *
     * @param string $model Name of the Model
     * @param string $key Configuration Key
     *
     * @return mixed The content of the requested key
     */
    public function getConfigData($model, $key)
    {
        return Mage::getStoreConfig(
            sprintf('payment/%s/%s', $model, $key)
        );
    }

    /**
     * Get A Success URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getSuccessURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/success', $model), array('_secure' => true));
    }

    /**
     * Get A Failure URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getFailureURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/failure', $model), array('_secure' => true));
    }

    /**
     * Get A Cancel URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getCancelURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/cancel', $model), array('_secure' => true));
    }

    /**
     * Get A Notification URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getNotifyURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/notify', $model), array('_secure' => true));
    }

    /**
     * Get a Redirect URL for the module
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getRedirectUrl($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/redirect', $model), array('_secure' => true));
    }

    /**
     * Generate Transaction Id based on the order id
     * and salted to avoid duplication
     *
     * @param string $prefix Prefix of the orderId
     * @param int $length Length of the generated string
     *
     * @return string
     */
    public function genTransactionId($prefix = '', $length = 30)
    {
        $hash = Mage::helper('core')->uniqHash();

        return substr($prefix . substr($hash, -(strlen($hash) - strlen($prefix))), 0, $length);
    }

    /**
     * Get the current locale in 2-digit i18n format
     *
     * @return string
     */
    public function getLocale($default = 'en')
    {
        $languageCode = substr(
            strtolower(
                Mage::app()->getLocale()->getLocaleCode()
            ),
            0,
            2
        );

        if (!\Genesis\API\Constants\i18n::isValidLanguageCode($languageCode)) {
            $languageCode = $default;
        }

        if (!\Genesis\API\Constants\i18n::isValidLanguageCode($languageCode)) {
            Mage::throwException(
                $this->__(
                    'The provided argument is not a valid ISO-639-1 language code ' .
                    'or is not supported by the Payment Gateway!'
                )
            );
        }

        return $languageCode;
    }

    /**
     * Return checkout session instance
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Return sales quote instance for specified ID
     *
     * @param int $quoteId Quote identifier
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote($quoteId)
    {
        return Mage::getModel('sales/quote')->load(
            abs(
                (int) $quoteId
            )
        );
    }

    /**
     * Get an array of (k=>v) from stdClass Genesis response
     *
     * @param $response
     * @return array
     */
    public function getArrayFromGatewayResponse($response)
    {
        $transactionDetails = array();

        foreach ($response as $key => $value) {
            if (is_string($value)) {
                $transactionDetails[$key] = $value;
            }

            if ($value instanceof DateTime) {
                $transactionDetails[$key] = $value->format('c');
            }
        }

        return $transactionDetails;
    }

    /**
     * Get DESC list of specific transactions from payment object
     *
     * @param Mage_Sales_Model_Order_Payment    $payment
     * @param array|string                      $typeFilter
     * @return array
     */
    public function getTransactionFromPaymentObject($payment, $typeFilter)
    {
        $transactions = array();

        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                          ->setOrderFilter($payment->getOrder())
                          ->addPaymentIdFilter($payment->getId())
                          ->addTxnTypeFilter($typeFilter)
                          ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC);

        /** @var Mage_Sales_Model_Order_Payment_Transaction $txn */
        foreach ($collection as $txn) {
            $transactions[] = $txn->setOrderPaymentObject($payment);
        }

        return $transactions;
    }

    /**
     * Get list of items in the order
     *
     * @see API parameter "Usage" or "Description"
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return string Formatted List of Items
     */
    public function getItemList($order)
    {
        $productResult = array();

        foreach ($order->getAllItems() as $item) {
            /** @var $item Mage_Sales_Model_Quote_Item */
            $product = $item->getProduct();

            $productResult[$product->getSku()] = array(
                'sku'  =>
                    $product->getSku(),
                'name' =>
                    $product->getName(),
                'qty'  =>
                    $item->getData('qty_ordered')
            );
        }

        $description = '';

        foreach ($productResult as $product) {
            $description .= sprintf("%s (%s) x %d\r\n", $product['name'], $product['sku'], $product['qty']);
        }

        return $description;
    }

    /**
     * Get list of items in the order
     *
     * @see API parameter "Usage" or "Description"
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @return string Formatted List of Items
     */
    public function getRecurringProfileItemDescription($profile)
    {
        $product = $profile->getOrderItemInfo();

        return
            sprintf(
                "%s (%s) x %d",
                $product['name'],
                $product['sku'],
                $product['qty']
            );
    }

    /**
     * Restore customer Quote
     *
     * @param $shouldCancel
     * @return bool
     */
    public function restoreQuote($shouldCancel = false)
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();

        if ($order->getId()) {
            $quote = $this->getQuote($order->getQuoteId());

            if ($shouldCancel && $order->canCancel()) {
                $order->cancel()->save();
            }

            if ($quote->getId()) {
                $quote->setIsActive(1)
                      ->setReservedOrderId(null)
                      ->save();
                $this->getCheckoutSession()
                     ->replaceQuote($quote)
                     ->unsLastRealOrderId();

                return true;
            }
        }

        return false;
    }

    /**
     * Set an order status based on transaction status
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $status
     * @param string $message
     */
    public function setOrderState($order, $status, $message = '')
    {
        $this->initLibrary();

        switch ($status) {
            case \Genesis\API\Constants\Transaction\States::APPROVED:
                $order
                    ->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        $message,
                        false
                    )
                    ->save();
                break;

            case \Genesis\API\Constants\Transaction\States::PENDING:
            case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                $order
                    ->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                        $message,
                        false
                    )
                    ->save();
                break;

            case \Genesis\API\Constants\Transaction\States::ERROR:
            case \Genesis\API\Constants\Transaction\States::DECLINED:
                /** @var Mage_Sales_Model_Order_Invoice $invoice */
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->cancel();
                }

                $order
                    ->registerCancellation($message)
                    ->setCustomerNoteNotify(true)
                    ->save();

                break;

            default:
                $order->save();
                break;
        }
    }

    /**
     * During "Checkout" we don't know have a Token,
     * however its required at a latter stage, which
     * means we have to extract it from the payment
     * data. We save the token when we receive a
     * notification from Genesis, then we only have
     * to find the earliest payment_transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return void
     */
    public function setTokenByPaymentTransaction($payment)
    {
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                          ->setOrderFilter($payment->getOrder())
                          ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_ASC);

        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        foreach ($collection as $transaction) {
            $information = $transaction->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );

            foreach ($information as $field => $value) {
                if ($field == self::RAW_DETAILS_TERMINAL_TOKEN) {
                    \Genesis\Config::setToken($value);
                }
            }
        }
    }

    /**
     * Redirect the visitor to the login page if
     * they are not logged in
     *
     * @param string $target Alternative target, if you don't want to redirect to login
     *
     * @return void
     */
    public function redirectIfNotLoggedIn($target = null)
    {
        /** @var Mage_Customer_Helper_Data $customer */
        $customer = Mage::helper('customer');

        /** @var Mage_Core_Helper_Url $url */
        $url = Mage::helper('core/url');

        if (!$customer->isLoggedIn()) {
            $target = $target ? $target : Mage::getUrl('customer/account/login', array('_secure' => true));

            $this->getCustomerSession()->setBeforeAuthUrl(
                $url->getCurrentUrl()
            );

            Mage::app()
                ->getFrontController()
                ->getResponse()
                ->setRedirect($target)
                ->sendHeaders();
        }
    }

    /**
     * @param string $model
     * @param string $key
     * @return bool
     */
    public function getConfigBoolValue($model, $key)
    {
        return
            filter_var(
                $this->getConfigData(
                    $model,
                    $key
                ),
                FILTER_VALIDATE_BOOLEAN
            );
    }

    /**
     * @param string $method
     * @return bool
     */
    public function getIsMethodActive($method)
    {
        return $this->getConfigBoolValue($method, 'active');
    }

    /**
     * Returns true if the WebSite is configured over Secured SSL Connection
     * @return bool
     */
    public function getIsSecureConnectionEnabled()
    {
        return (bool) Mage::app()->getStore()->isCurrentlySecure();
    }

    /**
     * @param string $method
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public function validateRecurringMethodMinMaxOrderTotal($method, $quote)
    {
        if (!$this->getCheckoutHasRecurringItems($quote)) {
            return false;
        }

        $total = $this->getRecurringQuoteBaseNominalRowTotal(
            $quote
        );

        $minTotal = $this->getConfigData($method, 'min_order_total');
        $maxTotal = $this->getConfigData($method, 'max_order_total');
        if ($total == 0 || !empty($minTotal) && $total < $minTotal || !empty($maxTotal) && $total > $maxTotal) {
            return false;
        }

        return true;
    }

    /**
     * @param string $method
     * @param Mage_Sales_Model_Quote $quote
     * @param bool $requiresSecureConnection
     * @param bool $supportsRecurring
     * @return bool
     */
    public function getIsMethodAvailable($method, $quote, $requiresSecureConnection = false, $supportsRecurring = true)
    {
        return
            $this->getIsMethodActive($method) &&
            (!$requiresSecureConnection || $this->getIsSecureConnectionEnabled()) &&
            (
                ($supportsRecurring &&
                    (!$quote->hasNominalItems() || $this->getConfigBoolValue($method, 'recurring_enabled'))
                ) ||
                (!$supportsRecurring && !$quote->hasNominalItems())
            );
    }

    /**
     * @param string $transactionType
     * @return bool
     */
    public function getIsTransaction3dSecure($transactionType)
    {
        return
            $this->getStringEndsWith(
                strtoupper($transactionType),
                self::SECURE_TRANSCTION_TYPE_SUFFIX
            );
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function getStringEndsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    /**
     * Get the Remote Address of the machine
     * @return string
     */
    public function getRemoteAddress()
    {
        $remoteAddress = Mage::helper('core/http')->getRemoteAddr(false);

        if (!$remoteAddress && function_exists("gethostbyname")) {
            $requestHostName = Mage::app()->getRequest()->getHttpHost();

            $remoteAddress = gethostbyname($requestHostName);
        }

        return $remoteAddress ?: "127.0.0.1";
    }

    /**
     * @param string $transactionType
     * @return bool
     */
    public function getIsTransactionTypeInitRecurring($transactionType)
    {
        $this->initLibrary();

        $initRecurringTransactionTypes = array(
            \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE,
            \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE_3D
        );

        return !empty($transactionType) && in_array($transactionType, $initRecurringTransactionTypes);
    }

    /**
     * Cancels a recurring profile when capture transaction is refunded
     * @param Mage_Sales_Model_Order_Payment_Transaction $captureTransaction
     * @return string|null
     */
    public function checkAndCancelRecurringProfile($captureTransaction)
    {
        $profileReferenceId = null;

        if ($captureTransaction && $captureTransaction->getId()) {
            $captureTransactionType = $this->getGenesisPaymentTransactionType(
                $captureTransaction
            );

            if ($captureTransactionType && $this->getIsTransactionTypeInitRecurring($captureTransactionType)) {
                $recurringProfileReferenceId =
                    $captureTransaction->getParentTxnId()
                        ?: $captureTransaction->getTxnId();

                $recurringProfile = Mage::getModel("sales/recurring_profile")->load(
                    $recurringProfileReferenceId,
                    'reference_id'
                );

                if ($recurringProfile && $recurringProfile->getId()) {
                    if ($recurringProfile->getState() != Mage_Sales_Model_Recurring_Profile::STATE_CANCELED) {
                        $recurringProfile->setState(
                            Mage_Sales_Model_Recurring_Profile::STATE_CANCELED
                        );

                        $recurringProfile->save();

                        $profileReferenceId = $recurringProfile->getReferenceId();
                    }
                }
            }
        }

        return $profileReferenceId;
    }

    /**
     * Extracts a Transaction Param Value from Transaction Additional Information
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * @return string|null
     */
    public function getGenesisPaymentTransactionParam($transaction, $paramName)
    {
        if (!is_object($transaction) || !$transaction->getId()) {
            return null;
        }

        $transactionRawDetails = $transaction->getAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );

        return
            isset($transactionRawDetails[$paramName])
                ? $transactionRawDetails[$paramName]
                : null;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * @return string|null
     */
    public function getGenesisPaymentTransactionType($transaction)
    {
        return $this->getGenesisPaymentTransactionParam(
            $transaction,
            self::RAW_DETAILS_TRANSACTION_TYPE
        );
    }

    /**
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * @return string|null
     */
    public function getGenesisPaymentTransactionToken($transaction)
    {
        return $this->getGenesisPaymentTransactionParam(
            $transaction,
            self::RAW_DETAILS_TERMINAL_TOKEN
        );
    }

    /**
     * Get Admin Session (Used to display Success and Error Messages)
     * @return Mage_Core_Model_Session_Abstract
     */
    public function getAdminSession()
    {
        return Mage::getSingleton("adminhtml/session");
    }

    /**
     * Get Init Recurring Fee Config Value for Method
     * @param string $methodCode
     * @return string
     */
    public function getMethodInitRecurringFee($methodCode)
    {
        return str_replace(
            ',',
            '.',
            $this->getConfigData(
                $methodCode,
                'recurring_initial_fee'
            )
        );
    }

    /**
     * Returns the current datetime as a formatted MySQL Datetime Value
     * @return string
     */
    public function formatCurrentDateTimeToMySQLDateTime()
    {
        return Mage::getSingleton('core/date')->gmtDate();
    }

    /**
     * Detects if a Recurring Item has been added to the Cart
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function getCheckoutHasRecurringItems($quote = null)
    {
        $quote = $quote ?: $this->getCheckoutSession()->getQuote();

        return $quote->hasRecurringItems();
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return float
     */
    public function getRecurringQuoteBaseNominalRowTotal($quote)
    {
        $baseNominalRowTotal = 0;

        foreach ($quote->getAllAddresses() as $quoteAddress) {
            foreach ($quoteAddress->getAllNominalItems() as $nominalItem) {
                if ($nominalItem->getIsNominal()) {
                    $baseNominalRowTotal += $nominalItem->getBaseNominalRowTotal();
                }
            }
        }

        return $baseNominalRowTotal;
    }

    /**
     * Returns Recurring Token per Payment Method
     * @param string $model
     * @return string
     */
    public function getRecurringSaleToken($model)
    {
        return $this->getConfigData(
            $model,
            'recurring_token'
        );
    }

    /**
     * Determines the Initial Txn Type of a Method Instance
     * @param Mage_Payment_Model_Method_Abstract $methodInstance
     * @return string|false
     */
    public function getTxnTypeOfMethodInstance($methodInstance)
    {
        if (! $methodInstance instanceof Mage_Payment_Model_Method_Abstract) {
            return false;
        }

        $paymentActionTxnTypes = array(
            Mage_Payment_Model_Method_Abstract::ACTION_ORDER             =>
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER,
            Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE         =>
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE =>
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
        );

        $paymentAction = $methodInstance->getConfigPaymentAction();

        if (array_key_exists($paymentAction, $paymentActionTxnTypes)) {
            return $paymentActionTxnTypes[$paymentAction];
        }

        return false;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return stdClass|false
     */
    public function reconcileCheckoutOrder($payment)
    {
        $methodInstance = $payment->getMethodInstance();

        $txnType = $this->getTxnTypeOfMethodInstance($methodInstance);

        if ($txnType === false) {
            return false;
        }

        $checkoutTransaction = $payment->lookupTransaction(null, $txnType);

        if ($checkoutTransaction === false) {
            return false;
        }

        if (!method_exists($methodInstance, 'reconcile')) {
            return false;
        }

        try {
            return $methodInstance->reconcile(
                $checkoutTransaction->getTxnId()
            );
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            // This is reconciliation, don't throw on ErrorAPI
        }

        return false;
    }

    /**
     * Retrieves the original Error Message from Gateway by doing Reconciliation
     *
     * @param string $default
     * @return string
     */
    public function getReconciledFailureActionMessage($default = '')
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();

        if (!$order || !$order->getPayment()) {
            return $default;
        }

        $responseObject = $this->reconcileCheckoutOrder($order->getPayment());

        if (! $responseObject instanceof \stdClass) {
            return $default;
        }

        $paymentTransaction =
            isset($responseObject->payment_transaction)
                ? $responseObject->payment_transaction
                : $responseObject;

        if (isset($paymentTransaction->message) && isset($paymentTransaction->technical_message)) {
            return "{$paymentTransaction->message} Details: {$paymentTransaction->technical_message}";
        } elseif (isset($paymentTransaction->message)) {
            return $paymentTransaction->message;
        }

        return $default;
    }

    /**
     * Retrieves the consumer's user id
     *
     * @return int
     */
    public static function getCurrentUserId()
    {
        /** @var Mage_Customer_Helper_Data $helper */
        $helper = Mage::helper('customer');
        if ($helper->isLoggedIn()) {
            return $helper->getCurrentCustomer()->getId();
        }
        return 0;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function getCurrentUserIdHash($length = 30)
    {
        $userId = self::getCurrentUserId();

        $userHash = $userId > 0 ? sha1($userId) : self::genTransactionId();

        return substr($userHash, 0, $length);
    }

    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     *
     * @return bool
     */
    public function getIsRefundable(Varien_Object $payment)
    {
        $refundableGatewayTransactionTypes = array(
            \Genesis\API\Constants\Transaction\Types::SALE,
            \Genesis\API\Constants\Transaction\Types::SALE_3D,
            \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE,
            \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE_3D,
            \Genesis\API\Constants\Transaction\Types::CASHU,
            \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Transaction\Types::INPAY,
            \Genesis\API\Constants\Transaction\Types::P24,
            \Genesis\API\Constants\Transaction\Types::PAYPAL_EXPRESS,
            \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE
        );

        return in_array(
            $this->getGenesisPaymentTransactionType($payment),
            $refundableGatewayTransactionTypes
        );
    }

    /**
     * Looks for the capture transaction, checks if it supports refunds and returns it.
     * Adds to Mage::log, if capture transaction is not found or is not refundable.
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Sales_Model_Order_Payment_Transaction|null
     */
    public function getCaptureForRefund(Varien_Object $payment) {
        $capture = $payment->lookupTransaction(
            null,
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
        );

        /* Refund Transaction is only possible, when Capture Transaction Exists */
        if ($capture === false) {
            Mage::log(
                'Refund transaction for order #' .
                $payment->getOrder()->getIncrementId() .
                ' could not be completed! (No Capture Transaction Exists)'
            );

            return null;
        }

        if (!$this->getIsRefundable($capture)) {
            Mage::log(
                'Refund transaction for order #' .
                $payment->getOrder()->getIncrementId() .
                ' could not be completed! (This transaction type does\'t support refund)'
            );

            return null;
        }

        return $capture;
    }
}

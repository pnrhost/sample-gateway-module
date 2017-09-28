<?php

/**
 * WHMCS Sample Payment Callback File
 *
 * This sample file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = 'payzw';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$integration_key = $gatewayParams['secretKey'];

use WHMCS\Database\Capsule;

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$invoiceId = $_GET["id"];

//Lets get our locally saved settings for this order

$invoice = Capsule::table('tblpayzw')->where('invoice_id', $invoiceId)
        ->orderBy('id', 'DESC')
        ->where('status', 'pending_payment')
        ->first();

$invoice_data_url = $invoice->data;
$returnUrl = $invoice->return_url;
$payzw_id = $invoice->id;


$order_data = ParseMsg($invoice_data_url);

$ch = curl_init();

//set the url, number of POST vars, POST data
curl_setopt($ch, CURLOPT_URL, $order_data['pollurl']);
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

//execute post
$result = curl_exec($ch);


//close connection

if ($result) {
    $msg = ParseMsg($result);
    $transactionId = 'Payzw-' . $payzw_id . '-' . msg['paynowreference'];

    $validateHash = CreateHash($msg, $integration_key);

    /**
     * Log Transaction.
     *
     * Add an entry to the Gateway Log for debugging purposes.
     *
     * The debug data can be a string or an array. In the case of an
     * array it will be
     *
     * @param string $gatewayName        Display label
     * @param string|array $debugData    Data to log
     * @param string $transactionStatus  Status
     */
    $log_data = array(['key' => $integration_key], ['local_data' => $invoice->data], ['poll_data' => $msg], ['hash_remote' => $msg['hash']], ['hash_local' => $validateHash]);
    logTransaction($gatewayParams['name'], $log_data, 'Complete');


    /**
     * Validate callback authenticity.
     *
     * Most payment gateways provide a method of verifying that a callback
     * originated from them. In the case of our example here, this is achieved by
     * way of a shared secret which is used to build and compare a hash.
     */
    if ($validateHash != $msg["hash"]) {
        header("Location: $returnUrl");
    } else {

        /**
         * Validate Callback Invoice ID.
         *
         * Checks invoice ID is a valid invoice number. Note it will count an
         * invoice in any status as valid.
         *
         * Performs a die upon encountering an invalid Invoice ID.
         *
         * Returns a normalised invoice ID.
         *
         * @param int $invoiceId Invoice ID
         * @param string $gatewayName Gateway Name
         */
        $checkInvoice = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

        /**
         * Check Callback Transaction ID.
         *
         * Performs a check for any existing transactions with the same given
         * transaction number.
         *
         * Performs a die upon encountering a duplicate.
         *
         * @param string $transactionId Unique Transaction ID
         */
        checkCbTransID($transactionId);

        /**
         * Check is invoice is paid
         */
        if ($msg['status'] == 'Paid' || $msg['status'] == 'Awaiting Delivery' || $msg['status'] == 'Delivered') {

            /**
             * Add Invoice Payment.
             *
             * Applies a payment transaction entry to the given invoice ID.
             *
             * @param int $invoiceId         Invoice ID
             * @param string $transactionId  Transaction ID
             * @param float $paymentAmount   Amount paid (defaults to full balance)
             * @param float $paymentFee      Payment fee (optional)
             * @param string $gatewayModule  Gateway module name
             */
            addInvoicePayment(
                    $invoiceId, $transactionId, $paymentAmount, 0, $gatewayParams['name']
            );

            /**
             * Update the tblpayzw table for this transaction
             */
            try {
                $updatedTblpayzw = Capsule::table('tblpayzw')
                        ->where('id', $payzw_id)
                        ->update(
                        [
                            'status' => 'Paid',
                        ]
                );
            } catch (\Exception $e) {
                echo "Error while making payment";
//                {$e->getMessage()};
            }
        }

        /**
         * redirect to return url as per the system
         */
        header("Location: $returnUrl");
    }
}



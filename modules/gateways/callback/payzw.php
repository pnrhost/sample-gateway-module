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
use WHMCS\Database\Capsule;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../payzw/functions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$status = $_POST["status"];
$invoiceId = $_POST["reference"];
// $paymentAmount = $_POST["amount"];
$hash = $_POST["hash"];

$success = false;

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */

//Lets get our locally saved settings for this order

$invoice = Capsule::table('tblpayzw')
        ->where('status', 'pending_payment')
        ->where('invoice_id', $invoiceId)
        ->orderBy('id', 'DESC')
        ->first();

logTransaction($gatewayParams['name'], $_POST, $status);

$payzw_id = $invoice->id;
$transactionId = 'Payzw-' . $payzw_id . '-' . $_POST["paynowreference"];

$ch = curl_init();

  //set the url, number of POST vars, POST data
  curl_setopt($ch, CURLOPT_URL, $invoice->pollurl);
  curl_setopt($ch, CURLOPT_POST, 0);
  curl_setopt($ch, CURLOPT_POSTFIELDS, '');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  //execute post
  $result = curl_exec($ch);

if ($result) {
    //close connection
    curl_close($ch);

    $msg = ParseMsg($result);
    
    $secretKey =  $$params['secretKey'];
    ;
    $validateHash = CreateHash($msg, $secretKey);

    // TODO

    // if ($validateHash != $msg["hash"]) {
    //     $success = false;
    // } else {
    //     if (!empty($invoice)  && $status === 'Paid') {
    //         $success = true;
    //     }
    // }
}




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
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

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
logTransaction($gatewayParams['name'], $_POST, $status);

if ($_POST['status'] == 'Paid' || $_POST['status'] == 'Awaiting Delivery' || $_POST['status'] == 'Delivered'){

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
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $gatewayModuleName
    );

      /**
       * Update the tblpayzw table for this transaction
       */
    try {
        $updatedTblpayzw = Capsule::table('tblpayzw')
                ->where('invoice_id', $invoiceId)
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

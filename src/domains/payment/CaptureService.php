<?php

namespace QD\altapay\domains\payment;

use Exception;
use QD\altapay\api\PaymentApi;
use QD\altapay\config\Utils;
use QD\altapay\services\OrderService;
use QD\altapay\services\TransactionService;

class CaptureService
{
  //* Authorize
  public static function execute(string $reference)
  {
    $transaction = TransactionService::getTransactionByReference($reference);
    if (!$transaction) throw new Exception("Authorized transaction not found", 1);

    $order = $transaction->getOrder();
    if (!$order) throw new Exception("Order not found for transaction", 1);

    $authorized = (float) $transaction->paymentAmount;
    $amount = (float) $order->getOutstandingBalance();

    if ($authorized <= 0) throw new Exception("Cannot capture a transaction with an amount of 0 or less", 1);

    // If the amount is larger that the authorized, cap the amount at authorized value
    if ($authorized <= $amount) {
      $amount = $authorized;
    }

    $payload = [
      'amount' => Utils::amount($amount),
      'orderLines' => OrderService::lines($order),
      'reconciliation_identifier' => $order->number ?? '',
      // 'sales_tax' => 0,
      'transaction_id' => $reference
    ];

    $response = PaymentApi::captureReservation($payload);
    return new PaymentResponse($response, $reference);
  }
}

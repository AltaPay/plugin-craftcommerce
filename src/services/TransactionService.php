<?php

namespace QD\altapay\services;

use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use Exception;
use craft\commerce\records\Transaction as RecordsTransaction;
use QD\altapay\Altapay;
use QD\altapay\config\Data;
use QD\altapay\events\PaymentAuthorizationEvent;
use QD\altapay\events\PaymentCaptureEvent;
use QD\altapay\events\SubscriptionCreatedEvent;

class TransactionService
{
  public static function authorize(string $status, object $response, string $msg = '', string $code = ''): Transaction
  {
    $parent = self::getTransactionByHash($response->transaction_info->transaction);
    if (!$parent) throw new Exception("Parent transaction not found", 1);

    $order = Order::findOne($response->transaction_info->order);
    if (!$order) throw new Exception("Order not found", 1);

    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);
    $transaction->type = RecordsTransaction::TYPE_AUTHORIZE;
    $transaction->status = $status;
    $transaction->reference = $response->payment_id;
    $transaction->response = $response;
    $transaction->message = $msg;
    $transaction->code = $code;

    $save = Commerce::getInstance()->getTransactions()->saveTransaction($transaction);
    if (!$save) {
      throw new Exception("Transaction could not be saved: " . json_encode($transaction->getErrors()), 1);
    }

    // EVENT
    $plugin = Altapay::getInstance();
    switch ($response->type) {
      case Data::PAYMENT_REQUEST_TYPE_PAYMENT:
        $plugin = Altapay::getInstance();
        $event = new PaymentAuthorizationEvent([
          'order' => $order,
          'transaction' => $transaction,
          'status' => $status
        ]);

        if ($plugin->hasEventHandlers(Altapay::EVENT_PAYMENT_AUTHORIZATION)) {
          $plugin->trigger(Altapay::EVENT_PAYMENT_AUTHORIZATION, $event);
        }
        break;

      case Data::PAYMENT_REQUEST_TYPE_SUBSCRIPTION:
        $event = new SubscriptionCreatedEvent([
          'order' => $order,
          'transaction' => $transaction,
          'status' => $status,
          'id' => $response->transaction_id
        ]);

        if ($plugin->hasEventHandlers(Altapay::EVENT_SUBSCRIPTION_CREATED)) {
          $plugin->trigger(Altapay::EVENT_SUBSCRIPTION_CREATED, $event);
        }
        break;
    }

    return $transaction;
  }

  public static function captureTransaction(Transaction $transaction): Transaction
  {
    $order = $transaction->getOrder();
    if (!$order) throw new Exception("Order not found for transaction", 1);

    $child = Commerce::getInstance()->getPayments()->captureTransaction($transaction);

    switch ($child->status) {
      case RecordsTransaction::STATUS_SUCCESS:
        $order->updateOrderPaidInformation();
        break;
      case RecordsTransaction::STATUS_PROCESSING:
        break;
      case RecordsTransaction::STATUS_PENDING:
        break;
      default:
        throw new Exception('Could not capture payment');
        break;
    }

    // EVENT
    $plugin = Altapay::getInstance();
    $event = new PaymentCaptureEvent([
      'order' => $order,
      'transaction' => $transaction,
      'status' => $child->status
    ]);

    if ($plugin->hasEventHandlers(Altapay::EVENT_PAYMENT_CAPTURE)) {
      $plugin->trigger(Altapay::EVENT_PAYMENT_CAPTURE, $event);
    }

    return $child;
  }

  public static function create(Order $order, ?Transaction $parent = null, string|int $reference, string $type, string $status, mixed $response = null, ?string $msg = '', ?string $code = '')
  {
    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);
    $transaction->type = $type;
    $transaction->status = $status;
    $transaction->reference = $reference;
    $transaction->response = $response;
    $transaction->message = $msg;
    $transaction->code = $code;

    $save = Commerce::getInstance()->getTransactions()->saveTransaction($transaction);
    if (!$save) {
      throw new Exception("Transaction could not be saved: " . json_encode($transaction->getErrors()), 1);
    }

    if ($transaction->type === RecordsTransaction::TYPE_CAPTURE && $transaction->status === RecordsTransaction::STATUS_SUCCESS) {
      $order->updateOrderPaidInformation();
    }

    return $transaction;
  }

  //* Utils
  public static function getTransactionById(int $id): ?Transaction
  {
    return Commerce::getInstance()->getTransactions()->getTransactionById($id);
  }

  public static function getLastChildTransactionById(int $id) {}

  public static function getTransactionByReference(string $reference): ?Transaction
  {
    return Commerce::getInstance()->getTransactions()->getTransactionByReference($reference);
  }

  public static function getTransactionByHash(string $hash): ?Transaction
  {
    return Commerce::getInstance()->getTransactions()->getTransactionByHash($hash);
  }

  public static function isTransactionSuccessful(Transaction $transaction): bool
  {
    return Commerce::getInstance()->getTransactions()->isTransactionSuccessful($transaction);
  }

  public static function getSuccessfulTransaction(int $orderId): ?Transaction
  {
    $transactions = Commerce::getInstance()->getTransactions()->getAllTransactionsByOrderId($orderId);

    $validTransactions = array_filter($transactions, function ($transaction) {
      return $transaction->type === RecordsTransaction::TYPE_AUTHORIZE &&
        $transaction->status === RecordsTransaction::STATUS_SUCCESS;
    });

    // If no transactions found, return null
    if (empty($validTransactions)) {
      return null;
    }

    // Sort by dateCreated in descending order (newest first)
    usort($validTransactions, function ($a, $b) {
      return $b->dateCreated <=> $a->dateCreated;
    });

    // Return the first (newest) transaction
    return $validTransactions[0];
  }

  public static function getLatestParentByConfig(int $orderId, $type, $status, $reference): ?Transaction
  {
    $transactions = Commerce::getInstance()->getTransactions()->getAllTransactionsByOrderId($orderId);

    $validTransactions = array_filter($transactions, function ($transaction) use ($type, $status, $reference) {
      return $transaction->type === $type && $transaction->status === $status && $transaction->reference === $reference && !$transaction->parentId;
    });

    // If no transactions found, return null
    if (empty($validTransactions)) {
      return null;
    }

    // Sort by dateCreated in descending order (newest first)
    usort($validTransactions, function ($a, $b) {
      return $b->dateCreated <=> $a->dateCreated;
    });

    // Return the first (newest) transaction
    return $validTransactions[0];
  }
}

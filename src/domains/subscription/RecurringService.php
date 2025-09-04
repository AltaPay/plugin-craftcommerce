<?php

namespace QD\altapay\domains\subscription;

use Craft;
use Exception;
use QD\altapay\api\ApiResponse;
use QD\altapay\api\SubscriptionApi;
use QD\altapay\config\Data;
use QD\altapay\config\Utils;
use QD\altapay\services\TransactionService;
use craft\commerce\records\Transaction as RecordsTransaction;
use QD\altapay\Altapay;
use QD\altapay\events\RecurringChargeEvent;
use QD\altapay\hooks\RecurringChargeHook;
use QD\altapay\services\OrderService;

class RecurringService
{
  public static function charge($id, $order): ApiResponse
  {
    if (!$order) throw new Exception("Order not found", 1);
    if (!$id) throw new Exception("Subscription not found", 1);

    $parent = TransactionService::getLatestParentByConfig($order->id, RecordsTransaction::TYPE_CAPTURE, RecordsTransaction::STATUS_PENDING, $id);
    if (!$parent) $parent = TransactionService::create($order, null, $id, RecordsTransaction::TYPE_CAPTURE, RecordsTransaction::STATUS_PENDING);

    $site = Craft::$app->getSites()->getSiteById($order->siteId);
    if (!$site) throw new Exception("Site not found", 1);

    // PAYLOAD
    $payload = [
      'amount' => Utils::amount($order->total),
      'reconciliation_identifier' => $order->number ?? '',
      'transaction_info' => [
        'store' => $order->storeId ?? '',
        'order' => $order->id ?? '',
        'transaction' => $transaction->hash ?? '',
        'subscription' => $id,
      ],
      'agreement' => [
        'id' => $id,
      ],
      'config' => [
        'callback_ok' => $site->baseUrl . 'callback/v1/altapay/recurring/ok',
        'callback_fail' => $site->baseUrl . 'callback/v1/altapay/recurring/fail',
      ],
      'orderLines' => OrderService::lines($order),
    ];

    // HOOK
    $plugin = Altapay::getInstance();
    $hook = new RecurringChargeHook([
      'order' => $order,

      'unscheduled_type' => Data::AGREEMENT_UNSCHEDULED_INCREMENTAL,
      'retry_days' => null,
      'surcharge_amount' => null,
      'dynamic_descriptor' => null,
    ]);
    $plugin->trigger(Altapay::HOOK_RECURRING_CHARGE, $hook);

    if ($hook->unscheduled_type) $payload['agreement']['unscheduled_type'] = $hook->unscheduled_type;
    if ($hook->retry_days) $payload['agreement']['retry_days'] = $hook->retry_days;
    if ($hook->surcharge_amount) $payload['surcharge_amount'] = $hook->surcharge_amount;
    if ($hook->dynamic_descriptor) $payload['dynamic_descriptor'] = $hook->dynamic_descriptor;

    $response = SubscriptionApi::chargeSubscription($payload);
    $status = self::_status($response->data->Result);
    $child = TransactionService::create($order, $parent, $response->data->Transactions->Transaction[0]->PaymentId, RecordsTransaction::TYPE_CAPTURE, $status, $response);

    // EVENT
    if ($plugin->hasEventHandlers(Altapay::EVENT_RECURRING_CHARGE)) {
      $event = new RecurringChargeEvent([
        'order' => $order,
        'transaction' => $child,
        'status' => $status
      ]);
      $plugin->trigger(Altapay::EVENT_RECURRING_CHARGE, $event);
    }

    return $response;
  }

  private static function _status(string $result): string
  {
    switch ($result) {
      case Data::RESPONSE_SUCCESS:
        return RecordsTransaction::STATUS_SUCCESS;

      case Data::RESPONSE_ERROR:
      case Data::RESPONSE_FAIL:
        return RecordsTransaction::STATUS_FAILED;

      case Data::RESPONSE_OPEN:
        return RecordsTransaction::STATUS_PROCESSING;

      case Data::RESPONSE_PARTIAL_SUCCESS:
        throw new Exception("Partial Success not implemented", 1);

      default:
        throw new Exception("Unknown response status: $result", 1);
    }
  }
}

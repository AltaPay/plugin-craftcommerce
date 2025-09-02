<?php

namespace QD\altapay\domains\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use QD\altapay\api\PaymentApi;
use QD\altapay\config\Data;
use QD\altapay\domains\payment\AuthorizeService;
use QD\altapay\domains\payment\CaptureService;
use QD\altapay\domains\payment\RefundService;


class SubscriptionGateway extends Gateway
{
  const SUPPORTS = [
    'Authorize' => true,
    'Capture' => true,
    'CompleteAuthorize' => false,
    'CompletePurchase' => false,
    'PaymentSources' => false,
    'Purchase' => true,
    'Refund' => true,
    'PartialRefund' => true,
    'Void' => true,
    'Webhooks' => false,
  ];

  use GatewayTrait;

  //* Settings
  public string $statusToCapture = Data::NULL_STRING;
  public string $statusAfterCapture = Data::NULL_STRING;
  public string $terminal = '';

  private string|bool $_onlyAllowForZeroPriceOrders = false;

  public static function displayName(): string
  {
    return Craft::t('commerce', 'AltaPay Payment');
  }

  //* Authorize
  public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
  {
    //TODO: Update this to use the auth subscription
    $response = AuthorizeService::execute($transaction);
    return $response;
  }

  //* Capture
  public function capture(Transaction $transaction, string $reference): RequestResponseInterface
  {
    //TODO: Update to use capture recurring function
    $response = CaptureService::execute($reference);
    return $response;
  }

  //* Refund
  public function refund(Transaction $transaction): RequestResponseInterface
  {
    //TODO: Update to use refund recurring function
    $response = RefundService::execute($transaction);
    return $response;
  }

  //* Settings
  public function getSettings(): array
  {
    $settings = parent::getSettings();
    $settings['onlyAllowForZeroPriceOrders'] = $this->getOnlyAllowForZeroPriceOrders(false);

    return $settings;
  }

  public function getSettingsHtml(): ?string
  {
    //* Terminal
    $terminals[] = ['value' => Data::NULL_STRING, 'label' => 'None'];
    foreach (PaymentApi::getTerminals() as $terminal) {
      $terminals[] = ['value' => $terminal->Title, 'label' => $terminal->Title];
    }

    //* Status
    // $statuses[] = ['value' => Data::NULL_STRING, 'label' => 'None'];
    // foreach (Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
    //   $statuses[] = ['value' => $status->handle, 'label' => $status->displayName];
    // }

    $options = [
      // 'statuses' => $statuses,
      'terminals' => $terminals,
    ];

    return Craft::$app->getView()->renderTemplate('craftcms-altapay/gateways/subscription', ['gateway' => $this, 'options' => $options]);
  }
}

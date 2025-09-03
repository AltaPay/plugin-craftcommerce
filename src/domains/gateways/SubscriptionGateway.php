<?php

namespace QD\altapay\domains\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use QD\altapay\api\PaymentApi;
use QD\altapay\config\Data;
use QD\altapay\domains\payment\AuthorizeService;

class SubscriptionGateway extends Gateway
{
  const SUPPORTS = [
    'Authorize' => true,
    'Capture' => false,
    'CompleteAuthorize' => false,
    'CompletePurchase' => false,
    'PaymentSources' => false,
    'Purchase' => true,
    'Refund' => false,
    'PartialRefund' => true,
    'Void' => true,
    'Webhooks' => false,
  ];

  use GatewayTrait;

  //* Settings
  public string $agreementName = '';
  public string $agreementDescription = '';

  public string $statusToCapture = Data::NULL_STRING;
  public string $statusAfterCapture = Data::NULL_STRING;
  public string $terminal = '';

  private string|bool $_onlyAllowForZeroPriceOrders = false;

  public static function displayName(): string
  {
    return Craft::t('commerce', 'AltaPay Subscription');
  }

  //* Authorize
  public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
  {
    $response = AuthorizeService::execute($transaction);
    return $response;
  }

  //* Capture
  public function capture(Transaction $transaction, string $reference): RequestResponseInterface
  {
    throw new NotImplementedException('Should be handled programatically by calling RecurringService::charge()');
  }

  //* Refund
  public function refund(Transaction $transaction): RequestResponseInterface
  {
    throw new NotImplementedException('Currently not supported, refund through dashboard');
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

    //TODO: Add GUI for editing agreement settings

    $options = [
      'terminals' => $terminals,
      'agreement' => (object)[
        'name' => $this->agreementName ?? '',
        'description' => $this->agreementDescription ?? '',
      ],
    ];

    return Craft::$app->getView()->renderTemplate('craftcms-altapay/gateways/subscription', ['gateway' => $this, 'options' => $options]);
  }
}

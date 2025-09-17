<?php

namespace QD\altapay\domains\payment;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;
use Exception;
use QD\altapay\api\PaymentApi;
use QD\altapay\config\Utils;
use craft\db\Query;
use Throwable;
use craft\commerce\db\Table;
use QD\altapay\Altapay;
use QD\altapay\config\Data;
use QD\altapay\domains\gateways\SubscriptionGateway;
use QD\altapay\hooks\SubscriptionAgreementHook;
use QD\altapay\services\OrderService;

class AuthorizeService
{
  //* Authorize
  public static function execute(Transaction $transaction): PaymentResponse
  {
    //* Order
    $order = $transaction->getOrder();
    if (!$order) throw new Exception("Order not found", 1);

    //* Reference
    $reference = self::_reference($order);
    if (!$reference) throw new Exception("Order reference not found", 1);

    //* Gateway
    $gateway = $transaction->getGateway();
    if (!$gateway) throw new Exception("Gateway not found", 1);
    if (!$gateway->terminal) throw new Exception("Gateway terminal not set", 1);

    //* Site
    $site = Craft::$app->getSites()->getSiteById($order->siteId);
    if (!$site) throw new Exception("Site not found", 1);

    $payload = [
      // required
      'terminal' => $gateway->terminal,
      'shop_orderid' => $order->reference,
      'amount' => Utils::amount($transaction->paymentAmount),
      'currency' => $order->paymentCurrency,
      'language' => explode('-', $site->language)[0],

      // optional
      'type' => Data::PAYMENT_REQUEST_TYPE_PAYMENT,
      'transaction_info' => [
        'store' => $order->storeId ?? '',
        'order' => $order->id ?? '',
        'number' => $order->number ?? '',
        'transaction' => $transaction->hash ?? '',
      ],
      'sale_reconciliation_identifier' => $order->number ?? '',
      // 'credit_card_token' => '',
      'fraud_service' => 'none',
      // 'cookie' => '',
      'payment_source' => 'eCommerce',
      // 'shipping_method' => '',
      // 'customer_created_date' => 'yyy-mm-dd',
      // 'organisation_number' => '',
      // 'account_offer' => '',
      // 'sales_tax' => 0,
    ];

    $payload['customer_info'] = self::_customer($order);
    $payload['config'] = self::_config($site->baseUrl);
    $payload['orderLines'] = OrderService::lines($order);
    self::_subscription($payload, $gateway);

    $response = PaymentApi::createPaymentRequest($payload);
    return new PaymentResponse($response);
  }

  //* PRIVATE
  private static function _customer(Order $order): array
  {
    $customer = $order->getCustomer();
    $shipping = $order->getShippingAddress();
    $billing = $order->getBillingAddress();

    $info = [];
    if ($customer) {
      $info += [
        'username' => $customer->id ?: '',
        'email' => $customer->email ?: '',
        'cardholder_name' => $customer->fullName ?? $billing->fullName ?? $shipping->fullName ?? '',
        // 'birthdate' => '',
        // 'gender' => '',
        // 'customer_phone' => '',

        // BANK
        // 'bank_phone' => '',
        // 'bank_name' => ''

        // CLIENT
        // 'client_session_id' => '',
        // 'client_accept_language' => '',
        // 'client_user_agent' => '',
        // 'client_forwarded_ip' => '',
      ];
    }

    if ($shipping) {
      $info += [
        'shipping_lastname' => $shipping->lastName ?? '',
        'shipping_firstname' => $shipping->firstName ?? '',
        'shipping_address' => $shipping->addressLine1 ?? '',
        'shipping_postal' => $shipping->postalCode ?? '',
        // 'shipping_region' => '',
        'shipping_country' => $shipping->countryCode ?? '',
        'shipping_city' => $shipping->locality ?? '',
      ];
    }

    if ($billing) {
      $info += [
        'billing_lastname' => $billing->lastName ?? '',
        'billing_firstname' => $billing->firstName ?? '',
        'billing_address' => $billing->addressLine1 ?? '',
        'billing_postal' => $billing->postalCode ?? '',
        // 'billing_region' => '',
        'billing_country' => $billing->countryCode ?? '',
        'billing_city' => $billing->locality ?? '',
      ];
    }

    return $info;
  }

  private static function _config($url): array
  {
    return [
      'callback_ok' => $url . 'callback/v1/altapay/payment/ok',
      'callback_fail' => $url . 'callback/v1/altapay/payment/fail',
      'callback_open' => $url . 'callback/v1/altapay/payment/open',
      'callback_notification' => $url . 'callback/v1/altapay/payment/notification',
    ];
  }

  private static function _reference(Order &$order): Order
  {
    if ($order->reference) return $order;

    $referenceTemplate = $order->getStore()->getOrderReferenceFormat();
    try {
      $baseReference = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $order);

      $suffix = 0;
      $testReference = $baseReference;

      while (true) {
        $existingReference = (new Query())
          ->select('id')
          ->from([Table::ORDERS])
          ->where(['reference' => $testReference])
          ->exists();

        if (!$existingReference) {
          $order->reference = $testReference;
          break;
        }

        $suffix++;
        $testReference = $baseReference . '-' . $suffix;
      }

      Craft::$app->getElements()->saveElement($order, false);
    } catch (Throwable $exception) {
      throw $exception;
    }

    if (!$order->reference) {
      throw new Exception('Failed to generate order reference', 1);
    }

    return $order;
  }

  private static function _subscription(&$payload, $gateway)
  {
    // Use default payment settings if order is not a subscription
    if (!$gateway instanceof SubscriptionGateway) return;

    // Set subscription data
    $payload['type'] = Data::PAYMENT_REQUEST_TYPE_SUBSCRIPTION;

    // HOOK
    //? This hook allows other developers to modify the subscription agreement data
    $plugin = Altapay::getInstance();
    $hook = new SubscriptionAgreementHook([
      'payload' => $payload,
      'gateway' => $gateway,
      'agreement' => [
        'type' => Data::AGREEMENT_TYPE_UNSCHEDULED,
      ],
    ]);

    $plugin->trigger(Altapay::HOOK_SUBSCRIPTION_AGREEMENT, $hook);
    $payload['agreement'] = $hook->agreement;

    // Handle unscheduled
    //? In case the subscription is unscheduled, this method is only used for authorization
    //? so we leave amount and items empty, as they will be a part of the individual charges
    $isUnscheduled = ($hook->agreement['type'] ?? '') === Data::AGREEMENT_TYPE_UNSCHEDULED;
    if ($isUnscheduled) {
      $payload['amount'] = Utils::amount(0);
      $payload['orderLines'] = [
        [
          'goodsType' => 'subscription_model',
          'itemId' => $gateway->agreementName ?? 'Subscription',
          'description' => $gateway->agreementDescription ?? 'Service',
          'quantity' => 1,
          'unitPrice' => Utils::amount(1),
        ]
      ];
    }
  }
}

<?php

namespace QD\altapay\config;

use Craft;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterComponentTypesEvent;
use QD\altapay\domains\gateways\PaymentGateway;
use QD\altapay\domains\gateways\SubscriptionGateway;
use QD\altapay\services\EventService;
use yii\base\Event;

trait Events
{
  public function events(): void
  {
    $request = Craft::$app->getRequest();
    $isCpRequest = $request->getIsCpRequest();
    $isConsoleRequest = $request->getIsConsoleRequest();

    $this->global();
    if (!$isCpRequest && !$isConsoleRequest) $this->frontendEvents();
    if ($isCpRequest && !$isConsoleRequest) $this->cpEvents();
  }

  //* GLOBAL EVENTS
  protected function global(): void
  {
    Event::on(
      Gateways::class,
      Gateways::EVENT_REGISTER_GATEWAY_TYPES,
      function (RegisterComponentTypesEvent $event) {
        $event->types[] = PaymentGateway::class;
        $event->types[] = SubscriptionGateway::class;
      }
    );

    Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, [EventService::class, 'eventOrderStatusChange']);

    // Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [ElementEvents::class, 'save']);
  }

  //* FRONTEND EVENTS
  protected function frontendEvents(): void {}

  //* CONTROL PANEL EVENTS
  protected function cpEvents(): void {}
}

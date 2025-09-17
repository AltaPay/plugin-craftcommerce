<?php

namespace QD\altapay\hooks;

use QD\altapay\domains\gateways\SubscriptionGateway;
use yii\base\Event;

class SubscriptionAgreementHook extends Event
{
  public array $payload;
  public SubscriptionGateway $gateway;
  public array $agreement;
}

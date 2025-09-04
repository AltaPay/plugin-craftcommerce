<?php

namespace QD\altapay\hooks;

use craft\commerce\elements\Order;
use yii\base\Event;

class RecurringChargeHook extends Event
{
  public Order $order;

  public ?string $unscheduled_type = null;
  public ?int $retry_days = null;
  public ?float $surcharge_amount = null;
  public ?string $dynamic_descriptor = null;
}

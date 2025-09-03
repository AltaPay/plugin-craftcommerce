<?php

namespace QD\altapay\events;

use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;
use yii\base\Event;

class PaymentAuthorizationEvent extends Event
{
    public Order $order;
    public Transaction $transaction;
    public string $status;
}

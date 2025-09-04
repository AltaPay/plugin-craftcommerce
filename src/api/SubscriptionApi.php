<?php

namespace QD\altapay\api;

class SubscriptionApi extends Api
{
  public function __construct()
  {
    parent::__construct();
  }

  public static function chargeSubscription(array $payload): ApiResponse
  {
    $response = (new Api())
      ->setMethod('chargeSubscription')
      ->setPayload($payload)
      ->post();

    return $response;
  }
}

<?php

namespace QD\altapay\queue;

use craft\queue\BaseJob;
use QD\altapay\domains\payment\AuthorizeCallbackService;

class NotificationQueue extends BaseJob
{
  public mixed $response;

  public function execute($queue): void
  {
    AuthorizeCallbackService::notification($this->response);
  }


  public function getTtr(): int
  {
    return 5;
  }

  public function canRetry($attempt, $error)
  {
    return false;
  }

  protected function defaultDescription(): string
  {
    return 'AltaPay: Notification';
  }
}

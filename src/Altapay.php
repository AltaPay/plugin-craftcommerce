<?php

namespace QD\altapay;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use QD\altapay\config\Events;
use QD\altapay\config\Routes;
use QD\altapay\config\Settings;

class Altapay extends Plugin
{
  // Use
  use Routes;
  use Events;

  // Settings
  public static $plugin;
  public string $schemaVersion = "5.0.0";
  public bool $hasCpSettings = true;
  public bool $hasCpSection = false;

  // Hooks
  const HOOK_SUBSCRIPTION_AGREEMENT = 'beforeSubscriptionAgreement';
  const HOOK_RECURRING_CHARGE = 'beforeRecurringCharge';

  // Events
  const EVENT_RECURRING_CHARGE = 'afterRecurringCharge';
  const EVENT_SUBSCRIPTION_CREATED = 'afterSubscriptionCreated';
  const EVENT_PAYMENT_AUTHORIZATION = 'afterPaymentAuthorization';
  const EVENT_PAYMENT_CAPTURE = 'afterPaymentCapture';

  public function init()
  {
    parent::init();
    Craft::setAlias('@QD/altapay', __DIR__);

    self::$plugin = $this;

    $this->routes();
    $this->events();
  }

  protected function createSettingsModel(): ?Model
  {
    return new Settings();
  }

  protected function settingsHtml(): ?string
  {
    return Craft::$app->getView()->renderTemplate('craftcms-altapay/settings', ['settings' => $this->getSettings()]);
  }
}

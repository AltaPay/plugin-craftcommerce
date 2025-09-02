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
  use Routes;
  use Events;

  public static $plugin;
  public string $schemaVersion = "5.0.0";
  public bool $hasCpSettings = true;
  public bool $hasCpSection = false;

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

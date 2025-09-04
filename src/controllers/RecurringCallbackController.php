<?php

namespace QD\altapay\controllers;

use Craft;
use craft\web\Controller;
use QD\altapay\config\Data;
use QD\altapay\config\Utils;
use QD\altapay\domains\payment\CaptureCallbackService;
use Exception;
use Throwable;

class RecurringCallbackController extends Controller
{
  public $enableCsrfValidation = false;
  protected array|bool|int $allowAnonymous = [
    'ok' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
    'fail' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
  ];


  // callback_ok
  public function actionOk()
  {
    try {
      $response = $this->_response();
      $this->_validate($response);
      CaptureCallbackService::callback(Data::CALLBACK_OK, $response);
    } catch (\Throwable $th) {
      throw new Exception($th->getMessage(), 1);
    }
  }

  // callback_fail
  public function actionFail()
  {
    try {
      $response = $this->_response();
      $this->_validate($response);
      CaptureCallbackService::callback(Data::CALLBACK_FAIL, $response);
    } catch (\Throwable $th) {
      throw new Exception($th->getMessage(), 1);
    }
  }

  private function _response()
  {
    $request = Craft::$app->getRequest()->getBodyParams();

    $xml = simplexml_load_string($request['xml'], 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) throw new Exception('Failed to parse XML response', 1);
    unset($request['xml']);

    $meta = json_decode(json_encode($xml), true);
    unset($meta['@attributes']);

    $response = Utils::objectify($request);
    $response->meta = Utils::objectify($meta);

    return $response;
  }

  private function _validate($response)
  {
    if (!$response->data->CaptureAmount) {
      throw new Exception('Invalid response: Missing capture arguments', 1);
    }

    if (!$response->data->Transactions->Transaction[0]->PaymentId) {
      throw new Exception('Invalid response: Missing payment reference', 1);
    }
  }
}

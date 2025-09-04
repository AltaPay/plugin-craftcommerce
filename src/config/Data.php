<?php

namespace QD\altapay\config;

abstract class Data
{
  const NULL_STRING = 'null';

  const CALLBACK_OK = 'ok';
  const CALLBACK_FAIL = 'fail';
  const CALLBACK_OPEN = 'open';
  const CALLBACK_NOTIFICATION = 'notification';

  const RESPONSE_SUCCESS = 'Success';
  const RESPONSE_FAIL = 'Fail';
  const RESPONSE_OPEN = 'Open';
  const RESPONSE_ERROR = 'Error';
  const RESPONSE_PARTIAL_SUCCESS = 'PartialSuccess';

  const PAYMENT_REQUEST_TYPE_PAYMENT = 'payment';
  const PAYMENT_REQUEST_TYPE_PAYMENT_CAPTURE = 'paymentAndCapture';
  const PAYMENT_REQUEST_TYPE_VERIFY = 'verifyCard';
  const PAYMENT_REQUEST_TYPE_CREDIT = 'credit';
  const PAYMENT_REQUEST_TYPE_SUBSCRIPTION = 'subscription';
  const PAYMENT_REQUEST_TYPE_SUBSCRIPTION_CHARGE = 'subscriptionAndCharge';
  const PAYMENT_REQUEST_TYPE_SUBSCRIPTION_RESERVE = 'subscriptionAndReserve';

  const AGREEMENT_TYPE_UNSCHEDULED = 'unscheduled';
  const AGREEMENT_TYPE_RECURRING = 'recurring';
  const AGREEMENT_TYPE_INSTALMENT = 'instalment';

  const AGREEMENT_UNSCHEDULED_INCREMENTAL = 'incremental';
  const AGREEMENT_UNSCHEDULED_RESUBMISSION = 'resubmission';
  const AGREEMENT_UNSCHEDULED_DELAYED_CHARGES = 'delayedCharges';
  const AGREEMENT_UNSCHEDULED_REAUTHORISATION = 'reauthorisation';
  const AGREEMENT_UNSCHEDULED_NO_SHOW = 'noShow';
  const AGREEMENT_UNSCHEDULED_CHARGE = 'charge';
}

<?php

namespace QD\altapay\config;

class Utils
{
  public static function stringify($data): string
  {
    if (is_string($data)) return $data;
    if (is_array($data)) return json_encode($data);
    if (is_object($data)) return json_encode($data);

    return '';
  }


  public static function objectify($data): object|array|string
  {
    if (is_object($data)) {
      return $data;
    }

    if (is_array($data)) {
      return json_decode(json_encode($data));
    }

    if (is_string($data)) {
      return json_decode($data);
    }

    return (object)[];
  }

  /**
   * @param mixed $data
   * @return object
   */
  public static function asObject(mixed $data): object
  {
    if (is_string($data)) {
      $data = self::decodeIfJson($data);
    }

    if (is_object($data)) return $data;
    if (is_array($data)) return json_decode(json_encode($data));
    return (object)[];
  }

  public static function decodeIfJson(mixed $string): mixed
  {
    if (!is_string($string)) return $string;

    $decode = json_decode($string);
    $isJson = json_last_error() === JSON_ERROR_NONE;

    if (!$isJson) return $string;
    return $decode;
  }

  public static function amount(mixed $raw): float
  {
    return (float) number_format((float) $raw, 2, '.', '');
  }
}

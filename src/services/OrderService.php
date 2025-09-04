<?php

namespace QD\altapay\services;

use Craft;
use craft\commerce\elements\Order;
use Exception;
use QD\altapay\config\Data;
use craft\commerce\Plugin as Commerce;
use QD\altapay\config\Utils;

class OrderService
{
  public static function setAfterCaptureStatus($order)
  {
    $gateway = $order->getGateway();
    if (!$gateway) throw new Exception("Gateway not found", 1);
    if ($gateway->statusAfterCapture === Data::NULL_STRING) return;

    $status = Commerce::getInstance()->getOrderStatuses()->getOrderStatusByHandle($gateway->statusAfterCapture, $order->storeId);
    if (!$status) throw new Exception("Order status not found", 1);

    $order->orderStatusId = $status->id;
    Craft::$app->getElements()->saveElement($order);
  }


  // Data
  public static function lines(Order $order): array
  {
    $lines = [];

    // Items
    $items = $order->getLineItems();
    if (!$items) return $lines;

    foreach ($items as $item) {
      $purchasable = $item->getPurchasable();
      if (!$purchasable) continue;

      //TODO: Allow setting the field in altapay settings
      $image = $purchasable->image ?? $purchasable->images ?? $purchasable->variantImages ?? null;

      $unitPrice = $item->taxIncluded ? $item->price - $item->taxIncluded : $item->price;
      $taxAmount = $item->taxIncluded ? $item->taxIncluded : 0.00;

      $lines[] = [
        'description' => $item->description ?: '',
        'itemId' => $purchasable->sku ?: '',
        'quantity' => $item->qty ?: 1,

        'unitPrice' => Utils::amount($unitPrice),
        'taxAmount' => Utils::amount($taxAmount),
        'discount' => Utils::amount(self::discount($item->subtotal, $item->total)),
        'goodsType' => 'item', // TODO: Handle verbb/git-vouchers?
        'imageUrl' => $image ? Craft::$app->getAssets()->getAssetUrl($image->eagerly()->one()) : '',
        'productUrl' => $purchasable->url ?: '',
      ];
    }

    // Adjustments
    $adjustments = $order->getAdjustments();
    foreach ($adjustments as $adjustment) {
      if ($adjustment->lineItemId) continue;
      if ($adjustment->type === 'shipping') continue;
      if ($adjustment->type === 'tax') continue;

      switch ($adjustment->type) {
        case 'discount':
          $type = 'discount';
          break;
        default:
          $type = 'item';
          break;
      }

      $lines[] = [
        'description' => $adjustment->name ?: 'Adjustment',
        'itemId' => strtolower(str_replace(' ', '-', $adjustment->name ?: 'adjustment')),
        'quantity' => 1,

        'unitPrice' => Utils::amount($adjustment->amount),
        'taxAmount' => Utils::amount(0.00),
        'discount' => 0.00,
        'goodsType' => $type,
        'imageUrl' => '',
        'productUrl' => '',
      ];
    }

    // Shipping
    $shipping = $order->totalShippingCost ?: null;
    if ($shipping) {
      $lines[] = [
        'description' => $order->shippingMethodName ?: 'Shipping',
        'itemId' => $order->shippingMethodHandle ?: 'shipping',
        'quantity' => 1,

        'unitPrice' => Utils::amount($shipping),
        'taxAmount' => Utils::amount(0.00),
        'discount' => 0.00,
        'goodsType' => 'shipment',
        'imageUrl' => '',
        'productUrl' => '',
      ];
    }

    return $lines;
  }

  public static function discount($subtotal, $total): float
  {
    if ($subtotal <= 0) return 0;
    if ($subtotal === $total) return 0;
    if ($total <= 0) return 100;

    $discount = (($subtotal - $total) / $subtotal) * 100;
    return (float) $discount ?? 0.00;
  }
}

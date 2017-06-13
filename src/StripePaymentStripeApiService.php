<?php
/**
 * Created by PhpStorm.
 * User: cfblack
 * Date: 5/31/17
 * Time: 12:56 PM
 */

namespace Drupal\badcamp_stripe_payment;


use Drupal\stripe_api\StripeApiService;

class StripePaymentStripeApiService extends StripeApiService {
  /**
   *
   */
  public function getApiKey() {
    $config_key = $this->getMode() . '_secret_key';
    $key_id = $this->config->get($config_key);
    if ($key_id) {
      return $key_id;
    }

    return NULL;
  }

  /**
   *
   */
  public function getPubKey() {
    $config_key = $this->getMode() . '_public_key';
    $key_id = $this->config->get($config_key);
    if ($key_id) {
      return $key_id;
    }

    return NULL;
  }
}
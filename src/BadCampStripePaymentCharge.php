<?php

namespace Drupal\badcamp_stripe_payment;

use Drupal\stripe_api\StripeApiService;
use Stripe\Charge;
use Stripe\Stripe;

/**
 * Class BadCampStripePaymentCharge.
 *
 * @package Drupal\badcamp_stripe_payment
 */
class BadCampStripePaymentCharge implements BadCampStripePaymentChargeInterface {

  /**
   * Drupal\stripe_api\StripeApiService definition.
   *
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;

  /**
   * @var \Stripe\Charge
   */
  protected $id;

  /**
   * @var \Stripe\Charge
   */
  protected $amount;

  /**
   * @var \Stripe\Charge
   */
  protected $currency;

  /**
   * @var \Stripe\Charge
   */
  protected $description;

  /**
   * @var \Stripe\Charge
   */
  protected $receipt_email;

  /**
   * @var \Stripe\Charge
   */
  protected $source;

  /**
   * @var \Stripe\Charge
   */
  protected $statement_descriptor;

  /**
   * Constructor.
   */
  public function __construct(StripeApiService $stripe_api_stripe_api) {
    $this->stripeApi = $stripe_api_stripe_api;
  }

  public function charge() {

    $charge = Charge::create($params);
  }

  /**
   * @return \Stripe\Charge
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @param \Stripe\Charge $id
   */
  public function setId($id) {
    $this->id = $id;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getAmount() {
    return $this->amount;
  }

  /**
   * @param \Stripe\Charge $amount
   */
  public function setAmount($amount) {
    $this->amount = $amount;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getCurrency() {
    return $this->currency;
  }

  /**
   * @param \Stripe\Charge $currency
   */
  public function setCurrency($currency) {
    $this->currency = $currency;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @param \Stripe\Charge $description
   */
  public function setDescription($description) {
    $this->description = $description;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getReceiptEmail() {
    return $this->receipt_email;
  }

  /**
   * @param \Stripe\Charge $receipt_email
   */
  public function setReceiptEmail($receipt_email) {
    $this->receipt_email = $receipt_email;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * @param \Stripe\Charge $source
   */
  public function setSource($exp_month, $exp_year, $cardholder_name, $number,
    $object = 'card', $cvc, $address_line1, $address_line2, $address_city,
    $address_state, $address_country, $address_zip, $statement_descriptor) {
    $source = [
      ''
    ];
    $this->source = $source;
  }

  /**
   * @return \Stripe\Charge
   */
  public function getStatementDescriptor() {
    return $this->statement_descriptor;
  }

  /**
   * @param \Stripe\Charge $statement_descriptor
   */
  public function setStatementDescriptor($statement_descriptor) {
    $this->statement_descriptor = $statement_descriptor;
  }



}

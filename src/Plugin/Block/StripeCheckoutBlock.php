<?php

namespace Drupal\badcamp_stripe_payment\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\stripe_api\StripeApiService;

/**
 * Provides a 'StripeCheckoutBlock' block.
 *
 * @Block(
 *  id = "stripe_checkout_block",
 *  admin_label = @Translation("Stripe checkout block"),
 * )
 */
class StripeCheckoutBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\stripe_api\StripeApiService definition.
   *
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StripeApiService $stripe_api
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stripeApi = $stripe_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stripe_api.stripe_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
         'turn_on_avs' => 1,
        ] + parent::defaultConfiguration();

 }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['turn_on_avs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Turn on street and zip/postal code checks 
      (address verification or AVS)?'),
      '#description' => $this->t('Address verification checks (also known as 
      Address Verification System or AVS) provide additional levels of 
      confirmation that the person using the card is the legitimate owner of 
      the card, useful to help identify and avoid fraud.'),
      '#default_value' => $this->configuration['turn_on_avs'],
      '#weight' => '1',
    ];

    $form['amount'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Amount'),
      '#description' => $this->t('ONLY NUMBERS ARE ALLOWED! The dollar amount 
      that should be charged IN CENTS, e.g:($20 = 2000)'),
      '#default_value' => $this->configuration['amount'],
      '#weight' => '2',
    ];

    $form['description'] = array(
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Description'),
      '#description' => $this->t('The description of the item you are asking 
      the buyer to pay for.'),
      '#default_value' => $this->configuration['description'],
      '#weight' => '3',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['turn_on_avs'] = $form_state->getValue('turn_on_avs');
    $this->configuration['amount'] = $form_state->getValue('amount');
    $this->configuration['description'] = $form_state->getValue('description');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $build['stripe_checkout'] = [
      '#theme' => 'stripe_checkout',
      '#data_key' => $this->stripeApi->getPubKey(),
      '#amount' => $this->configuration['amount'],
      '#org_name' => $this->label(),
      '#data_description' => $this->configuration['description'],
      '#data_zip_code' => $this->configuration['turn_on_avs']
    ];

    return $build;
  }

}

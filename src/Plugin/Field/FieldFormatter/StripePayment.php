<?php

namespace Drupal\badcamp_stripe_payment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\stripe_api\StripeApiService;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'stripe_payment' formatter.
 *
 * @FieldFormatter(
 *   id = "stripe_payment",
 *   module = "badcamp_stripe_payment",
 *   label = @Translation("Simple stripe payment"),
 *   field_types = {
 *     "stripe_payment"
 *   }
 * )
 */
class StripePayment extends FormatterBase implements ContainerFactoryPluginInterface {
  /**
   * Drupal\stripe_api\StripeApiService definition.
   *
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Drupal\Core\Entity\EntityTypeBundleInfo definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;
  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;
  /**
   * Array of Stripe Payment Bundles.
   *
   * @var array|mixed
   */
  protected $stripeBundles;

  /**
   * StripePayment constructor.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    StripeApiService $stripe_api,
    EntityTypeManager $entityManager,
    EntityTypeBundleInfo $bundleInfo,
    AccountProxy $account
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->stripeApi = $stripe_api;
    $this->entityTypeManager = $entityManager;
    $this->bundleInfo = $bundleInfo;
    $this->currentUser = $account;

    $this->stripeBundles = $this->bundleInfo->getBundleInfo('stripe_payment');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('stripe_api.stripe_api'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if (count($items) > 0) {
      $total_payments = $this->getNumberOfPayments($items->getEntity()->id());
      $item = $items->get(0);
      if (($total_payments < $item->max_payments || $item->max_payments == 0) && $item->enable) {
        $payment_label = $this->stripeBundles[$item->payment_type]['label'];
        $elements[] = [
          '#theme' => 'stripe_checkout',
          '#description' => $item->description_value,
          '#data_key' => $this->stripeApi->getPubKey(),
          '#amount' => $item->amount,
          "#email" => $this->currentUser->getEmail(),
          '#org_name' => $items->getEntity()->label(),
          '#data_description' => $payment_label,
          '#data_zip_code' => $item->avs,
          '#payment_type' => $item->payment_type,
          '#button_label' => $item->button_label,
          '#entity_type' => $items->getEntity()->getEntityType()->id(),
          '#entity_id' => $items->getEntity()->id(),
        ];
      }
      elseif ($total_payments >= $item->max_payments) {
        $elements[] = [
          '#markup' => t('Payment already made'),
        ];
      }
    }
    $elements['#cache']['max-age'] = 0;
    return $elements;
  }

  /**
   * Get the number of payments for the provided entity and it's ID.
   */
  private function getNumberOfPayments($entity_id) {
    return $this->entityTypeManager->getStorage('stripe_payment')->getQuery('AND')->condition('entity_id', $entity_id)->count()->execute();
  }

}

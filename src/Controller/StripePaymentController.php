<?php

namespace Drupal\badcamp_stripe_payment\Controller;

use Drupal\badcamp_stripe_payment\Entity\StripePayment;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\badcamp_stripe_payment\Entity\StripePaymentInterface;
use Drupal\stripe_api\StripeApiService;
use Stripe\Charge;
use Stripe\StripeObject;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\badcamp_stripe_payment\Form\SwagSelectorForm;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Class StripePaymentController.
 *
 *  Returns responses for Stripe payment routes.
 *
 * @package Drupal\badcamp_stripe_payment\Controller
 */
class StripePaymentController extends ControllerBase implements ContainerInjectionInterface {

  /*
    * Drupal\stripe_api\StripeApiService definition.
    *
    * @var \Drupal\stripe_api\StripeApiService
    */
  protected $stripeApi;

  /**
   * StripePaymentController constructor.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   */
  protected $request;

  protected $entityTypeManager;

  public function __construct(RequestStack $request,
    StripeApiService $stripeApiService,
    EntityTypeManager $entityTypeManager) {
    $this->request = $request;
    $this->stripeApi = $stripeApiService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('stripe_api.stripe_api'),
      $container->get('entity_type.manager')
    );
  }
  /**
   * Displays a Stripe payment  revision.
   *
   * @param int $stripe_payment_revision
   *   The Stripe payment  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($stripe_payment_revision) {
    $stripe_payment = $this->entityManager()->getStorage('stripe_payment')->loadRevision($stripe_payment_revision);
    $view_builder = $this->entityManager()->getViewBuilder('stripe_payment');

    return $view_builder->view($stripe_payment);
  }

  /**
   * Page title callback for a Stripe payment  revision.
   *
   * @param int $stripe_payment_revision
   *   The Stripe payment  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($stripe_payment_revision) {
    $stripe_payment = $this->entityManager()->getStorage('stripe_payment')->loadRevision($stripe_payment_revision);
    return $this->t('Revision of %id from %date', array('%id' => $stripe_payment->id(), '%date' => format_date($stripe_payment->getRevisionCreationTime())));
  }

  /**
   * Generates an overview table of older revisions of a Stripe payment .
   *
   * @param \Drupal\badcamp_stripe_payment\Entity\StripePaymentInterface $stripe_payment
   *   A Stripe payment  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(StripePaymentInterface $stripe_payment) {
    $account = $this->currentUser();
    $langcode = $stripe_payment->language()->getId();
    $langname = $stripe_payment->language()->getName();
    $languages = $stripe_payment->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $stripe_payment_storage = $this->entityManager()->getStorage('stripe_payment');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %id', ['@langname' => $langname, '%id' => $stripe_payment->id()]) : $this->t('Revisions for %title', ['%title' => $stripe_payment->id()]);
    $header = array($this->t('Revision'), $this->t('Operations'));

    $revert_permission = (($account->hasPermission("revert all stripe payment revisions") || $account->hasPermission('administer stripe payment entities')));
    $delete_permission = (($account->hasPermission("delete all stripe payment revisions") || $account->hasPermission('administer stripe payment entities')));

    $rows = array();

    $vids = $stripe_payment_storage->revisionIds($stripe_payment);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\badcamp_stripe_payment\StripePaymentInterface $revision */
      $revision = $stripe_payment_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->revision_timestamp->value, 'short');
        if ($vid != $stripe_payment->getRevisionId()) {
          $link = $this->l($date, new Url('entity.stripe_payment.revision', ['stripe_payment' => $stripe_payment->id(), 'stripe_payment_revision' => $vid]));
        }
        else {
          $link = $stripe_payment->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->revision_log_message->value, '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => Url::fromRoute('entity.stripe_payment.revision_revert', ['stripe_payment' => $stripe_payment->id(), 'stripe_payment_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.stripe_payment.revision_delete', ['stripe_payment' => $stripe_payment->id(), 'stripe_payment_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['stripe_payment_revisions_table'] = array(
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    );

    return $build;
  }

  /**
   * Handles the charging of the card via the token from Stripe.
   */
  public function checkoutCharge () {
    $build = [];
    $stripe_token = $this->request->getCurrentRequest()->get('stripeToken');
    $stripe_email = $this->request->getCurrentRequest()->get('stripeEmail');
    $stripe_token_type = $this->request->getCurrentRequest()->get('stripeTokenType');
    $amount = $this->request->getCurrentRequest()->get('amount');
    $payment_type = $this->request->getCurrentRequest()->get('payment_type');
    $receipt_email = $this->request->getCurrentRequest()->get('stripeEmail');
    $entity_type = $this->request->getCurrentRequest()->get('entity_type');
    $entity_id = $this->request->getCurrentRequest()->get('entity_id');
    $entity = NULL;

    if(!empty($entity_type) && !empty($entity_id)) {
      $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    }

    // Create the Customer in Stripe
/*
    $customer_params = [
      'email' => $stripe_email,
      'source'  => $stripe_token
    ];

    $customer = $this->stripeApi->call('Customer','create',$customer_params);
    // @todo This customer exists, look them up instead.
    if(is_null($customer)) {
      //$this->stripeApi->call('Customer','')
    }
      // @todo: store the stripe customer ID for this user in Drupal.
*/

    // Make sure we have a customer record from Stripe and that the amount sent
    // through is set and is more than zero dollars and is numeric.
    if (isset($amount) && is_numeric($amount) && $amount > 0) {
      //Create the Charge in Stripe
      $charge_params = [
        'amount'   => $amount,
        'description' => is_null($entity) ? 'BADCamp Sponsorship' : $entity->label(),
        'currency' => 'usd',
        'source' => $stripe_token,
        'receipt_email' => $receipt_email
      ];

      // Try to charge the card, if there is an error log it and output the
      // error to the screen.

      $charge = $this->stripeApi->callWithErrors('Charge','create', $charge_params);

      if (isset($charge->declineCode)) {
        drupal_set_message(t('There was a problem with your payment!'),'error');
        // Show the error on screen.
        $build = [
          '#theme' => 'stripe_checkout_error',
          '#message' => t('There was a problem processing your @type payment for 
          @amount.', [
            '@type' => $payment_type,
            '@amount' => $amount
          ]),
          '#error' => $charge->getMessage(),
          '#decline_code' => $charge->declineCode
        ];

        // disable caching for this block.
        $build['#cache']['max-age'] = 0;

        return $build;
      }

      // This payment was marked for review, notify the site owners
      if (isset($charge->review)) {
        // todo: set up configuration for module to collect a payment reviewer email address.
        // todo: Send email to the reviewer.
//        $module = "challenge_group_invite";
//        $key = "invitation_invitee";
//        // Specify 'to' and 'from' addresses.
//        $to = $this->config('badcamp_stripe_payment.payment_reviewer')->get('mail');
//        $from = $this->config('system.site')->get('mail');
//        $language_code = $this->languageManager->getDefaultLanguage()->getId();
//        $params = ['account' => $user, 'group' => $this->group];
//        $send_now = TRUE;
//        $result = $this->mailManager->mail($module, $key, $to, $language_code, $params, $from, $send_now);
      }

      // Store the payment in Drupal
      if ($charge->captured && $charge->paid) {
        $data = [];
        if ($entity_type == 'node' && $entity_id != '') {
          $data['entity_id'] = $entity_id;
        }
        $this->savePayment($payment_type, $charge, $data);

        // Show the payment complete regardless if the payment record is saved
        // to reduce user confusion.
        drupal_set_message(t('Your payment was successful'));

        // Set a generate RedirectResponse have it go to the homepage
        $response = new RedirectResponse('/');

        // Donations
        if($payment_type == 'donation') {
          // Redirect to the swag selector if they have donated at a qualifying
          // level.
          // @todo: make config for what qualifies for swag
          $qualified_for_swag = TRUE;

          $response = new RedirectResponse('/donation-complete');
          if ($qualified_for_swag) {
            $response = new RedirectResponse('/swag-selector');
          }
        }
        else {
          if($entity_id != '' && $entity_type != '') {
            $url = Url::fromRoute('entity.' . $entity_type . '.canonical', [$entity_type => $entity_id])
              ->getInternalPath();
            $response = new RedirectResponse('/' . $url);
          }
        }

        return $response;
      }
    }

    // disable caching for this block.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Handles thanking for the donation
   */
  public function donationComplete() {
    $ssf = new SwagSelectorForm;
    $payment = $ssf->getLatestPaymentByUser($this->currentUser()->id());
    $charge = (int)$payment->get('amount')->getString();
    setlocale(LC_MONETARY, 'en_US.UTF-8');
    $build = [
      '#title' => t('Payment Complete'),
      '#theme' => 'stripe_checkout_complete',
      '#message' => t('Thank you for your @type payment.',['@type' => $payment->get('type')->getString()]),
      '#amount' => money_format('%.2n',number_format($charge/100,"2",".",",")),
      '#statement_indicator' => 'BADCamp Sponsorship', //@todo: get statement indicator into config
    ];

    // disable caching for this block.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Handles getting a fake payment in the system for $0 so they won't get
   * bothered trying to go to their user page anymore.
   */
  public function donationDoNotBug() {
    $charge = new Charge(0);
    //$charge->id = 0;
    $charge->outcome = new StripeObject();
    $charge->outcome->type = 'do_not_bug';
    $charge->paid = 1;
    $charge->amount = 0;
    $charge->refunded = 0;
    $charge->status = 'succeeded';
    $this->savePayment('donation',$charge);

    $response = new RedirectResponse('/user');
    return $response;
  }
  /**
   * Save a payment record in the database.
   *
   * @param $payment_type
   * @param $charge
   */
  protected function savePayment($payment_type,$charge, $additional_data = []) {
    $stripe_payment_entity_storage = $this->entityTypeManager->getStorage('stripe_payment');
    $data = [
      'type' => $payment_type,
      'user_id' => $this->currentUser()->id(),
      'stripe_transaction_id' => $charge->id,
      'stripe_outcome_type' => $charge->outcome->type,
      'paid' => $charge->paid,
      'amount' => $charge->amount,
      'refunded' => $charge->refunded,
      'stripe_status' => $charge->status
    ] + $additional_data;
    $stripe_payment_entity = $stripe_payment_entity_storage->create($data);
    // Save the payment record. Log any error.
    try {

      $stripe_payment_entity_storage->save($stripe_payment_entity);

    } catch (Exception $dbe) {

      $db_error = $dbe->getMessage();
      // Log the error to watchdog log
      $this->getLogger('badcamp_stripe_payment')->error(t('The @type payment 
        submitted by @user for @stripe_id in the amount of @amount was not 
        stored in the stripe_payment table because: 
        @error', [
        '@type' => $payment_type,
        '@user' => $this->currentUser()->getAccountName(),
        '@stripe_id' => $charge->id,
        '@amount' => $charge->amount,
        '@error' => $db_error
      ]));

    }
  }
}

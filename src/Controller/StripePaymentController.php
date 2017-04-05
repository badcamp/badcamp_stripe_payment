<?php

namespace Drupal\badcamp_stripe_payment\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\badcamp_stripe_payment\Entity\StripePaymentInterface;

/**
 * Class StripePaymentController.
 *
 *  Returns responses for Stripe payment routes.
 *
 * @package Drupal\badcamp_stripe_payment\Controller
 */
class StripePaymentController extends ControllerBase implements ContainerInjectionInterface {

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

}

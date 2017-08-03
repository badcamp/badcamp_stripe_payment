<?php

namespace Drupal\badcamp_stripe_payment\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class DonateRedirectSubscriber.
 */
class DonateRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;



  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['kernel.response'] = ['routing_route_alter'];
    return $events;
  }

  /**
   * This method is called whenever the routing.route_alter event is
   * dispatched.
   *
   * @param GetResponseEvent $event
   */
  public function routing_route_alter(Event $event) {
    $req = $event->getRequest();

    if ($req->attributes->get('_route') == 'entity.user.canonical') {
      //get the user
      $account = User::load(\Drupal::currentUser()->id());
      //have they donated? If no, redirect to donate page
      $donations = $this->_get_donations($account->id());
      if (count($donations) < 1) {
        $redirect_url = URL::fromRoute('badcamp_stripe_payment.donation_page_controller');
        $response = new RedirectResponse($redirect_url->toString(), 301);
        $event->setResponse($response);
        return $event;
      }
      return;
    }
    else {
      return;
    }

  }

  /**
   * Get the donations for a given user ID
   *
   * @param $aid User account id
   * @return mixed
   */
  public function _get_donations($aid) {
    //@todo: make this a separate service
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $donateStorage = $entityTypeManager->getStorage('stripe_payment');
    $donations = $donateStorage->loadByProperties(['user_id' => $aid]);
    return $donations;
  }

}

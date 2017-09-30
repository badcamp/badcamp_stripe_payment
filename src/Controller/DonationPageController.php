<?php

namespace Drupal\badcamp_stripe_payment\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class DonationPageController.
 */
class DonationPageController extends ControllerBase {

  /**
   * Displaycontent.
   *
   * @return string
   *   Return Hello string.
   */
  public function displayContent() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('<div><p>Do you think BADCamp is awesome? Would you be 
willing to pay for your ticket?</p>

<p>If so, then you can give back to the camp by purchasing an individual 
sponsorship level most comfortable for you.</p>

<p>As a bonus, all individual sponsors will receive a BADCamp T-shirt.</p></div>')
    ];
  }

}

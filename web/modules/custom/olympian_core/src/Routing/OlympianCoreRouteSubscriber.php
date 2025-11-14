<?php

namespace Drupal\olympian_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class OlympianCoreRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change form for the system.site_information_settings route
    // to Drupal\describe_site\Form\DescribeSiteSiteInformationForm
    // First, we need to act only on the system.site_information_settings route.
    if ($route = $collection->get('system.site_information_settings')) {
      // Next, we need to set the value for _form to the form we have created.
      $route->setDefault('_form', 'Drupal\olympian_core\Form\SettingsForm');
    }

    $restricted_routes = [
      // Google Tag Manager settings.
      'google_tag.settings_form',
      // Global theme settings.
      'system.theme_settings',
    ];

    // Block route for non-admins.
    if ($route = $collection->get('google_tag.settings_form')) {
      $route->setRequirement('_olympian_core_access_check', 'TRUE');
    }
  }

}

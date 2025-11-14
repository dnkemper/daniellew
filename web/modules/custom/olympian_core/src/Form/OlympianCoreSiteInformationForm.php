<?php

namespace Drupal\olympian_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Form\SiteInformationForm;

/**
 * Configure site information settings for this site.
 *
 * @phpstan-ignore-next-line
 */
class OlympianCoreSiteInformationForm extends SiteInformationForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the system.site configuration.
    $site_config = $this->config('system.site');

    // Get the original form from the class we are extending.
    $form = parent::buildForm($form, $form_state);

    $form['site_information']['site_slogan']['#access'] = FALSE;

    $form['site_information']['has_parent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This site is part of a parent organization.'),
      '#default_value' => $site_config->get('has_parent'),
      '#description' => $this->t('Show additional options for setting the parent organization website. Note: this setting is not necessary to show that a site is part of A&S.'),
    ];

    $form['site_information']['parent'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    $form['site_information']['parent']['site_parent_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $site_config->get('parent.name'),
      '#description' => $this->t('The official name of the parent organization.'),
      '#states' => [
        'required' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    $form['site_information']['hubspot'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Global Hubspot Code'),
      '#format' => 'restricted_html',
      '#allowed_formats' => [
        'restricted_html',
      ],
      '#default_value' => $site_config->get('hubspot') ?? '<script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/44596675.js"></script>',
      '#description' => $this->t('The hubspot code loaded globally'),
    ];

    $form['#attached']['library'][] = 'linkit/linkit.autocomplete';
    $form['site_information']['contact_us_url'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Contact Us URL'),
      '#description' => $this->t('URL of Contact Us link on Resources Landing Page.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => $site_config->get('contact_us_url') ? $site_config->get('contact_us_url') : '/contact-us',
      '#required' => TRUE,
    ];
    $form['site_information']['paragraph_social'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph Social Media'),
      '#collapsible' => TRUE,
      '#open' => TRUE,
    ];
    $form['site_information']['paragraph_social']['twitter_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('X Username'),
      '#default_value' => $site_config->get('paragraph_social.twitter_username'),
    ];
    $form['site_information']['paragraph_social']['instagram_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram Username'),
      '#default_value' => $site_config->get('paragraph_social.instagram_username'),
    ];
    $form['site_information']['paragraph_youtube_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Youtube'),
      '#default_value' => $site_config->get('paragraph_youtube_username'),
      '#description' => $this->t('The URL after https://www.youtube.com/@'),
    ];
    $form['site_information']['paragraph_linkedin_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LinkedIn'),
      '#default_value' => $site_config->get('paragraph_linkedin_username'),
      '#description' => $this->t('part of URL after https://www.linkedin.com/'),
    ];
    $form['site_information']['paragraph_twitter_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('X'),
      '#default_value' => $site_config->get('paragraph_twitter_username'),
    ];
    $form['site_information']['paragraph_facebook_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook'),
      '#default_value' => $site_config->get('paragraph_facebook_username'),
    ];
    $form['site_information']['paragraph_instagram_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram'),
      '#default_value' => $site_config->get('paragraph_instagram_username'),
    ];
    $form['error_page']['custom_403_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Custom 403 Page'),
      '#default_value' => $site_config->get('custom_403.display'),
      '#attributes' => [
        'name' => 'custom_403_display',
      ],
      '#return_value' => TRUE,
    ];
    $form['error_page']['custom_alert_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Custom Alert Message'),
      '#format' => 'full_html',
      '#allowed_formats' => [
        'full_html',
      ],
      '#default_value' => $site_config->get('custom_403.message') ?? '<h3>Please click here to log in</h3><a class="button" href="/user">login here</a>',
      '#description' => $this->t('The message to be displayed. The first heading in the message will have an associated icon based on the alert level.'),
      '#states' => [
        'visible' => [
          ':input[name="custom_403_display"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="custom_403_display"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $insta = $form_state->getValue('instagram_username');
    $contact_us = $form_state->getValue('contact_us_url');
    if ($insta) {
      $form_state
        ->setValueForElement($form['site_information']['paragraph_social']['instagram_username'], strtolower($insta));
    }
    else {
      $form_state->setValueForElement($form['site_information']['paragraph_social']['instagram_username'], strtolower('washuartsci'));
    }
    if (!$contact_us) {
      $form_state->setValueForElement($form['site_information']['contact_us_url'], strtolower('/contact-us'));
    }
    if ($form_state->getValue('custom_403_display') && empty($form_state->getValue('custom_alert_message')['value'])) {
      $form_state->setErrorByName('custom_alert_message', 'This field is required.');
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save additional site information config.
    $this->config('system.site')
      ->set('paragraph.facebook_username', $form_state->getValue('site_facebook_username'))
      ->set('paragraph.instagram_username', $form_state->getValue('site_instagram_username'))
      ->set('paragraph_youtube_username', $form_state->getValue('paragraph_youtube_username'))
      ->set('paragraph_linkedin_username', $form_state->getValue('paragraph_linkedin_username'))
      ->set('paragraph_twitter_username', $form_state->getValue('paragraph_twitter_username'))
      ->set('paragraph_facebook_username', $form_state->getValue('paragraph_facebook_username'))
      ->set('paragraph_instagram_username', $form_state->getValue('paragraph_instagram_username'))
      ->set('paragraph_social.twitter_username', $form_state->getValue('twitter_username'))
      ->set('paragraph_social.instagram_username', $form_state->getValue('instagram_username'))
      ->set('hubspot', $form_state->getValue([
        'hubspot',
        'value',
      ]))
      ->set('has_parent', $form_state->getValue('has_parent'))
      ->set('custom_403.display', $form_state->getValue('custom_403_display'))
      ->set('custom_403.message', $form_state->getValue([
        'custom_alert_message',
        'value',
      ]))
      ->set('parent.name', $form_state->getValue('site_parent_name'))
     // ->set('parent.url', $form_state->getValue('site_parent_url'))
      ->set('contact_us_url', $form_state->getValue('contact_us_url'))
      ->set('site_slogan', 'Arts & Sciences at Washington University in St. Louis')
      // Make sure to save the configuration.
      ->save();
    parent::submitForm($form, $form_state);
  }

}

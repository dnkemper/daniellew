<?php

namespace Drupal\olympian_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Configure olympian_core settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator, RequestContext $request_context) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'olympian_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['olympian_core.settings', 'system.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('olympian_core.settings');
    $site_config = $this->config('system.site');
    $node_types = $this->replicateDisableNodeTypes();

    $form['site_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Site details'),
      '#open' => TRUE,
    ];

    $form['site_information']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name'),
      '#default_value' => $config->get('name'),
      '#required' => TRUE,
    ];

    $form['site_information']['site_slogan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slogan'),
      '#default_value' => $config->get('site_slogan'),
      '#description' => $this->t("How this is used depends on your site's theme."),
      '#maxlength' => 255,
    ];

    $form['site_information']['production_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Production URL'),
      '#default_value' => $config->get('production_url'),
      '#description' => $this->t('URL of production site.'),
    ];

    $form['site_information']['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => $config->get('mail'),
      '#description' => $this->t("The <em>From</em> address in automated emails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this email being flagged as spam.)"),
      '#required' => TRUE,
    ];

    $form['site_information']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#default_value' => $config->get('phone'),
      '#description' => $this->t('Site contact phone number.'),
    ];

    $form['organization'] = [
      '#type' => 'details',
      '#title' => $this->t('Organization Settings'),
      '#open' => TRUE,
    ];

    $form['organization']['has_parent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This site is part of a parent organization.'),
      '#default_value' => $config->get('has_parent'),
      '#description' => $this->t('Show additional options for setting the parent organization website.'),
    ];

    $form['organization']['parent'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['organization']['parent']['parent_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parent Organization Name'),
      '#default_value' => $config->get('parent.name'),
      '#description' => $this->t('The official name of the parent organization.'),
      '#states' => [
        'required' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['organization']['parent']['parent_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Parent Organization URL'),
      '#default_value' => $config->get('parent.url'),
      '#description' => $this->t('URL of parent organization website.'),
      '#states' => [
        'required' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['#attached']['library'][] = 'linkit/linkit.autocomplete';

    $form['front_page'] = [
      '#type' => 'details',
      '#title' => $this->t('Front page'),
      '#open' => TRUE,
    ];

    $form['front_page']['site_frontpage'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Default front page'),
      '#description' => $this->t('Specify a relative URL to display as the front page.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => $config->get('page.front') ?: $site_config->get('page.front'),
      '#required' => TRUE,
    ];
    $form['error_pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom Error Pages'),
      '#open' => TRUE,
    ];

    $form['error_pages']['custom_403_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Custom 403 Page'),
      '#default_value' => $config->get('custom_403_display'),
      '#return_value' => TRUE,
    ];

    $form['error_pages']['custom_403_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Custom 403 Alert Message'),
      '#format' => 'full_html',
      '#allowed_formats' => ['full_html'],
      '#default_value' => $config->get('custom_403_message') ?? '<h3>Please click here to log in</h3><a class="button" href="/user">login here</a>',
      '#description' => $this->t('The message to be displayed. The first heading in the message will have an associated icon based on the alert level.'),
      '#states' => [
        'visible' => [
          ':input[name="custom_403_display"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="custom_403_display"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['error_pages']['site_403'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default 403 (access denied) page'),
      '#default_value' => $config->get('page.403'),
      '#size' => 40,
      '#description' => $this->t('This page is displayed when the requested document is denied to the current user. Leave blank to display a generic "access denied" page.'),
    ];

    $form['error_pages']['site_404'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default 404 (not found) page'),
      '#default_value' => $config->get('page.404'),
      '#size' => 40,
      '#description' => $this->t('This page is displayed when no other content matches the requested document. Leave blank to display a generic "page not found" page.'),
    ];

    $form['contact'] = [
      '#type' => 'details',
      '#title' => $this->t('Contact Settings'),
      '#open' => TRUE,
    ];

    $form['contact']['contact_us_url'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Contact Us URL'),
      '#description' => $this->t('URL of Contact Us link on Resources Landing Page.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => $config->get('contact_us_url') ?? '/contact-us',
      '#required' => TRUE,
    ];

    $form['social_media'] = [
      '#type' => 'details',
      '#title' => $this->t('Social Media'),
      '#open' => TRUE,
    ];

    $form['social_media']['twitter_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('X (Twitter) Username'),
      '#default_value' => $config->get('social_media.twitter_username'),
      '#description' => $this->t('Username without the @ symbol.'),
    ];

    $form['social_media']['facebook_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook Username'),
      '#default_value' => $config->get('social_media.facebook_username'),
      '#description' => $this->t('Facebook page username or ID.'),
    ];

    $form['social_media']['instagram_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram Username'),
      '#default_value' => $config->get('social_media.instagram_username'),
      '#description' => $this->t('Username without the @ symbol.'),
    ];

    $form['social_media']['youtube_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('YouTube Channel'),
      '#default_value' => $config->get('social_media.youtube_username'),
      '#description' => $this->t('The part after https://www.youtube.com/@'),
    ];

    $form['social_media']['linkedin_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LinkedIn'),
      '#default_value' => $config->get('social_media.linkedin_username'),
      '#description' => $this->t('The part after https://www.linkedin.com/'),
    ];

    $form['replication'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Replication'),
      '#open' => TRUE,
    ];

    $form['replication']['olympian_core_replicate_allowed'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#size' => count($node_types),
      '#title' => $this->t('Allowed Content Types for Cloning'),
      '#default_value' => $config->get('olympian_core_replicate_allowed'),
      '#options' => $node_types,
      '#description' => $this->t('Select the available content types that can be "cloned".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Normalize Instagram username to lowercase.
    $instagram = $form_state->getValue('instagram_username');
    if ($instagram) {
      $form_state->setValue('instagram_username', strtolower($instagram));
    }
    else {
      // Set default if empty.
      $form_state->setValue('instagram_username', 'washuartsci');
    }

    // Ensure contact_us_url has a value.
    $contact_us = $form_state->getValue('contact_us_url');
    if (empty($contact_us)) {
      $form_state->setValue('contact_us_url', '/contact-us');
    }

    // Validate custom 403 message if display is enabled.
    if ($form_state->getValue('custom_403_display')) {
      $message = $form_state->getValue('custom_403_message');
      if (empty($message['value'])) {
        $form_state->setErrorByName('custom_403_message', $this->t('Custom 403 message is required when custom 403 page is enabled.'));
      }
    }

    // Get the normal path of the front page.
    $form_state->setValueForElement($form['front_page']['site_frontpage'], $this->aliasManager->getPathByAlias($form_state->getValue('site_frontpage')));

    // Get the normal paths of both error pages.
    if (!$form_state->isValueEmpty('site_403')) {
      $form_state->setValueForElement($form['error_pages']['site_403'], $this->aliasManager->getPathByAlias($form_state->getValue('site_403')));
    }
    if (!$form_state->isValueEmpty('site_404')) {
      $form_state->setValueForElement($form['error_pages']['site_404'], $this->aliasManager->getPathByAlias($form_state->getValue('site_404')));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Simple function to return a FAPI select options array.
   */
  public function replicateDisableNodeTypes() {
    $node_types = NodeType::loadMultiple();
    $options = [];
    foreach ($node_types as $node_type) {
      $options[$node_type->id()] = $node_type->label();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save to olympian_core.settings.
    $this->config('olympian_core.settings')
      ->set('name', $form_state->getValue('name'))
      ->set('site_slogan', $form_state->getValue('site_slogan'))
      ->set('mail', $form_state->getValue('mail'))
      ->set('phone', $form_state->getValue('phone'))
      ->set('production_url', $form_state->getValue('production_url'))
      ->set('page.front', $form_state->getValue('site_frontpage'))
      ->set('page.403', $form_state->getValue('site_403'))
      ->set('page.404', $form_state->getValue('site_404'))
      ->set('has_parent', $form_state->getValue('has_parent'))
      ->set('parent.name', $form_state->getValue('parent_name'))
      ->set('parent.url', $form_state->getValue('parent_url'))
      ->set('contact_us_url', $form_state->getValue('contact_us_url'))
      ->set('social_media.twitter_username', $form_state->getValue('twitter_username'))
      ->set('social_media.facebook_username', $form_state->getValue('facebook_username'))
      ->set('social_media.instagram_username', $form_state->getValue('instagram_username'))
      ->set('social_media.youtube_username', $form_state->getValue('youtube_username'))
      ->set('social_media.linkedin_username', $form_state->getValue('linkedin_username'))
      ->set('custom_403_display', $form_state->getValue('custom_403_display'))
      ->set('custom_403_message', $form_state->getValue(['custom_403_message', 'value']))
      ->set('olympian_core_replicate_allowed', $form_state->getValue('olympian_core_replicate_allowed'))
      ->save();

    // Also save core site values to system.site to keep them in sync.
    $this->config('system.site')
      ->set('name', $form_state->getValue('name'))
      ->set('slogan', $form_state->getValue('site_slogan'))
      ->set('mail', $form_state->getValue('mail'))
      ->set('page.front', $form_state->getValue('site_frontpage'))
      ->set('page.403', $form_state->getValue('site_403'))
      ->set('page.404', $form_state->getValue('site_404'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\olympian_media_wysiwyg\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure layout builder blocks styles.
 */
class MediaEmbedExtraForm extends ConfigFormBase {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * Constructs a StylesFilterConfigForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($config_factory);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_display.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('olympian_media_wysiwyg.settings');
    $view_mode_options = $this->entityDisplayRepository->getViewModeOptions('media');

    $form['mem_allowed_view_modes'] = [
      '#title' => $this->t("View modes width + height field should appear for"),
      '#type' => 'checkboxes',
      '#options' => $view_mode_options,
      '#default_value' => $config->get('mem_allowed_view_modes') ?? [],
      '#description' => $this->t("Select view modes that the width + height fields should appear on when embedding media. If none is selected all will be used."),
      '#element_validate' => [[static::class, 'validateOptions']],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form element validation handler.
   *
   * @param array $element
   *   The allowed_view_modes form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateOptions(array &$element, FormStateInterface $form_state) {
    // Filters the #value property so only selected values appear in the
    // config.
    $form_state->setValueForElement($element, array_filter($element['#value']));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('olympian_media_wysiwyg.settings');

    if (empty($form_state->getValue('mem_allowed_view_modes'))) {
      $view_modes = $form['mem_allowed_view_modes']['#options'];
    }
    else {
      $view_modes = $form_state->getValue('mem_allowed_view_modes');
    }
    $config->set('mem_allowed_view_modes', $view_modes);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'olympian_media_wysiwyg_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['olympian_media_wysiwyg.settings'];
  }

}

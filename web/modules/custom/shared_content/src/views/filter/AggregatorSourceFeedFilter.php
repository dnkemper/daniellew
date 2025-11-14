<?php

namespace Drupal\shared_content\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters aggregator feed items by the source feed field.
 *
 * @ViewsFilter("aggregator_source_feed_filter")
 */
class AggregatorSourceFeedFilter extends FilterPluginBase {

    /**
     * {@inheritdoc}
     */
    public function defineOptions() {
        $options = parent::defineOptions();
        $options['value'] = ['default' => NULL];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);

        $options = $this->getSourceFeedOptions();
        $form['value'] = [
            '#type' => 'select',
            '#title' => $this->t('Source Feed'),
            '#options' => $options,
            '#empty_option' => $this->t('- Any -'),
            '#default_value' => $this->options['value'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function query() {
        if (!empty($this->value)) {
            $this->query->addWhere(0, 'field_source_feed', $this->value, '=');
        }
    }

    /**
     * Fetches the available source feed options.
     *
     * @return array
     *   An array of source feed options.
     */
    protected function getSourceFeedOptions() {
        $feeds = \Drupal::entityTypeManager()
            ->getStorage('aggregator_feed')
            ->loadMultiple();

        $options = [];
        foreach ($feeds as $feed) {
            $options[$feed->id()] = $feed->label();
        }

        return $options;
    }
}

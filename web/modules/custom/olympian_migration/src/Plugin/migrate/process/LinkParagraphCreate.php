<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Site\Settings;

/**
 * Creates a links paragraph and returns its item_id and revision_id.
 *
 * Use in the process section of the migration YML:
 *
 * @code
 * field_destination:
 *   plugin: link_paragraph_create
 *   url_source: field_source_url
 *   title_source: field_source_title
 *   new_window_source: field_source_new_window
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "link_paragraph_create",
 *   handle_multiples = TRUE
 * )
 */
class LinkParagraphCreate extends ProcessPluginBase {

  /**
   *
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      // Get the URL from the source field.
      $value = is_array($value) ? $value[0]['value'] : $value;
      $url = $this->canonicalizeUri($row->getSourceProperty($this->configuration['url_source']));

      // Check if the URL is external.
      $is_external = $this->isExternalLink($url);

      // Create a new paragraph of type 'related_links'.
      $paragraph = Paragraph::create([
        'type' => 'related_links',
        'field_button_link' => [
          'uri' => $url,
          'title' => $row->getSourceProperty($this->configuration['title_source']),
          'options' => ['attributes' => []],
        ],
        'field_new_window' => [
            // Enable new window for external links.
          'value' => $is_external,
        ],
      ]);

      $paragraph->save();

      // Return the item_id and revision_id of the created paragraph.
      return [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
  }

  /**
   *
   */
  protected function isExternalLink($uri) {
    // Parse the URL and check if it has a scheme and domain that indicate it's external.
    $parsed_url = parse_url($uri);

    // Retrieve the D7_SITE URL from settings and extract its host.
    $source_uri = Settings::get('olympian_migration_source_uri_prefix');
    $expected_host = parse_url($source_uri, PHP_URL_HOST);

    // Check if the URL has a scheme and domain, which implies it's an external link.
    if (isset($parsed_url['scheme']) && isset($parsed_url['host']) && $parsed_url['host'] !== $expected_host) {
      return TRUE;
    }

    // For any other case, it's considered internal.
    return FALSE;
  }

  /**
   * Turn a Drupal 6/7 URI into a Drupal 8-compatible format.
   * Borrowed from field_link migration
   *
   * @param string $uri
   *   The 'url' value from Drupal 6/7.
   *
   * @return string
   *   The Drupal 8-compatible URI.
   *
   * @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUserEnteredStringAsUri()
   */
  protected function canonicalizeUri($uri) {
    // If the path starts with 2 slashes then it is always considered an.
    // @todo Remove this when https://www.drupal.org/node/2744729 lands.
    if (strpos($uri, '//') === 0) {
      return $this->configuration['uri_scheme'] . ltrim($uri, '/');
    }

    if (parse_url($uri, PHP_URL_SCHEME)) {
      return $uri;
    }

    // Empty URI and non-links are allowed.
    if (empty($uri) || in_array($uri, ['<nolink>', '<none>'])) {
      return 'route:<nolink>';
    }

    if (strpos($uri, '/') !== 0) {
      $domain = Settings::get('olympian_migration_source_uri_prefix');
      $uri = rtrim($domain, '/') . '/' . ltrim($uri, '/');
      return $uri;
    }

    // Remove the <front> component of the URL.
    if (strpos($uri, '<front>') === 0) {
      $uri = substr($uri, strlen('<front>'));
    }
    else {
      // List of unicode-encoded characters that were allowed in URLs,
      // and &#x00FF; (except × &#x00D7; and ÷ &#x00F7;) with the addition of
      // &#x0152;, &#x0153; and &#x0178;.
      // cSpell:disable-next-line
      $link_i_chars = '¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿŒœŸ';

      // Pattern specific to internal links.
      $internal_pattern = '/^(?:[a-z0-9' . $link_i_chars . '_\-+\[\] ]+)';

      $directories = '(?:\/[a-z0-9' . $link_i_chars . "_\-\.~+%=&,$'#!():;*@\[\]]*)*";
      // Yes, four backslashes == a single backslash.
      $query = '(?:\/?\?([?a-z0-9' . $link_i_chars . "+_|\-\.~\/\\\\%=&,$'():;*@\[\]{} ]*))";
      $anchor = '(?:#[a-z0-9' . $link_i_chars . "_\-\.~+%=&,$'():;*@\[\]\/\?]*)";

      // The rest of the path for a standard URL.
      $end = $directories . '?' . $query . '?' . $anchor . '?$/i';

      if (!preg_match($internal_pattern . $end, $uri)) {
        $link_domains = '[a-z][a-z0-9-]{1,62}';

        // Starting a parenthesis group with (?: means that it is grouped, but is not captured.
        $authentication = "(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=" . $link_i_chars . ']|%[0-9a-f]{2})+(?::(?:[\w' . $link_i_chars . "\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})*)?)?@)";
        $domain = '(?:(?:[a-z0-9' . $link_i_chars . ']([a-z0-9' . $link_i_chars . '\-_\[\]])*)(\.(([a-z0-9' . $link_i_chars . '\-_\[\]])+\.)*(' . $link_domains . '|[a-z]{2}))?)';
        $ipv4 = '(?:[0-9]{1,3}(\.[0-9]{1,3}){3})';
        $ipv6 = '(?:[0-9a-fA-F]{1,4}(\:[0-9a-fA-F]{1,4}){7})';
        $port = '(?::([0-9]{1,5}))';

        // Pattern specific to external links.
        $external_pattern = '/^' . $authentication . '?(' . $domain . '|' . $ipv4 . '|' . $ipv6 . ' |localhost)' . $port . '?';
        if (preg_match($external_pattern . $end, $uri)) {
          return $this->configuration['uri_scheme'] . $uri;
        }
      }
    }

    // Add the internal: scheme and ensure a leading slash.
    return 'internal:/' . ltrim($uri, '/');
  }

}

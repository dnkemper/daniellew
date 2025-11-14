<?php

namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Core\Site\Settings;

/**
 * @MigrateProcessPlugin(
 *   id = "migration_custom_check_url"
 * )
 */
class CheckExternalUrl extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Initialize $url.
    $url = '';

    // Ensure we have a value to process.
    if (!empty($value)) {
      if (is_array($value) && isset($value[0]['value'])) {
        $url = $this->canonicalizeUri(trim($value[0]['value']));
      }
      elseif (is_string($value)) {
        $url = $this->canonicalizeUri(trim($value));
      }

      // Check if the URL is external or internal
      $is_external = $this->isExternalLink($url);

      return $is_external ? '1' : '0'; // Return '1' for external, '0' for internal
    }

    return '0'; // Default to internal if no value is provided
  }

  /**
   * Check if a URI is an external link.
   *
   * @param string $uri
   *   The URI to check.
   *
   * @return bool
   *   TRUE if the URI is external, FALSE otherwise.
   */
  protected function isExternalLink($uri) {
    // Parse the incoming URI.
    $parsed_url = parse_url($uri);

    // Retrieve the D7_SITE URL from settings and extract its host.
    $source_uri = Settings::get('olympian_migration_source_uri_prefix');
    $expected_host = parse_url($source_uri, PHP_URL_HOST);

    // If the URL doesn't have a host (i.e. it's relative), treat it as internal.
    if (empty($parsed_url['host'])) {
      return FALSE;
    }

    // If the URL's host does not match the expected host, treat it as external.
    return ($parsed_url['host'] !== $expected_host);
  }

  /**
   * Canonicalize the URI (ensure it is in a proper format).
   *
   * @param string $uri
   *   The URI to canonicalize.
   *
   * @return string
   *   The canonicalized URI.
   */
  protected function canonicalizeUri($uri) {
    // If the URI starts with two slashes, consider it an external link
    if (str_starts_with($uri, '//')) {
      return \Drupal::config('system.site')->get('url') . ltrim($uri, '/');
    }

    // If the URI already has a scheme, return it as is
    if (parse_url($uri, PHP_URL_SCHEME)) {
      return $uri;
    }

    // Check for empty or non-link values
    if (empty($uri) || in_array($uri, ['<nolink>', '<none>'])) {
      return 'route:<nolink>';
    }

    // Ensure a scheme is present for internal links
    $domain = \Drupal::config('system.site')->get('url'); // Use the site's base URL as a prefix
    return rtrim($domain, '/') . '/' . ltrim($uri, '/');
  }
}

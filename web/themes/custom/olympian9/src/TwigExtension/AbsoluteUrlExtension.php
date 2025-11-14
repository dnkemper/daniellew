<?php

namespace Drupal\olympian_core\TwigExtension;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for absolute URL generation.
 */
class AbsoluteUrlExtension extends AbstractExtension {

  protected $fileUrlGenerator;

  public function __construct(FileUrlGeneratorInterface $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('absolute_url', [$this, 'makeAbsolute']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('absolute_url', [$this, 'makeAbsolute']),
    ];
  }

  /**
   * Convert any URL (relative or URI) to absolute URL.
   */
  public function makeAbsolute($input) {
    if (empty($input)) {
      return '';
    }

    $input_string = (string) $input;

    // If already absolute, return as-is
    if (strpos($input_string, 'http://') === 0 || strpos($input_string, 'https://') === 0) {
      return $input_string;
    }

    // If it's a URI (like public://), convert it
    if (strpos($input_string, '://') !== false) {
      return $this->fileUrlGenerator->generateAbsoluteString($input_string);
    }

    // If it's a relative URL (starts with /), make it absolute
    if (strpos($input_string, '/') === 0) {
      global $base_url;
      return $base_url . $input_string;
    }

    // Fallback
    return $input_string;
  }
}
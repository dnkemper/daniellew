<?php
namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\media\Entity\Media;

/**
 * Converts a SoundCloud URL or embed code into a 'soundcloud' media entity.
 *
 * @MigrateProcessPlugin(
 *   id = "soundcloud_url_to_media"
 * )
 */
class ExtractSoundcloudUrl extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $media_ids = [];

    if (is_array($value)) {
      foreach ($value as $url) {
        $media_id = $this->processUrl($url);
        if ($media_id) {
          $media_ids[] = $media_id;
        }
      }
    } else {
      return $this->processUrl($value);
    }

    return $media_ids;
  }

  /**
   * Process a single URL or embed code to create a media entity.
   *
   * @param string $url
   *   The SoundCloud URL or embed code.
   *
   * @return int|null
   *   The ID of the created media entity, or NULL if not created.
   */
  private function processUrl($url) {
    if (!empty($url)) {
      // Extract the SoundCloud track URL from the input.
      $track_url = $this->extractTrackUrl($url);

      if ($track_url) {
        // Create a media entity of type 'soundcloud'.
        $media = Media::create([
          'bundle' => 'soundcloud', // Ensure this matches your media bundle machine name
          'field_media_soundcloud' => $track_url, // Adjust field name as needed
          'name' => $track_url,
        ]);
        $media->save();
        return $media->id();
      }
    }
    return NULL;
  }

  /**
   * Extract the SoundCloud track URL from the input value.
   *
   * @param string $value
   *   The input SoundCloud URL or embed code.
   *
   * @return string|null
   *   The extracted track URL, or NULL if not found.
   */
  private function extractTrackUrl($value) {
    // Match the embed code and extract the URL.
    if (preg_match('/src="([^"]+)"/', $value, $matches)) {
      $url = $matches[1];
      // Remove the player prefix if present.
      if (strpos($url, 'https://w.soundcloud.com/player/?url=') === 0) {
        $url = str_replace('https://w.soundcloud.com/player/?url=', '', $url);
      }
      return $url;
    }

    // Check if the value is a direct SoundCloud URL.
    if (filter_var($value, FILTER_VALIDATE_URL) && strpos($value, 'soundcloud.com') !== FALSE) {
      return $value;
    }

    return NULL;
  }
}

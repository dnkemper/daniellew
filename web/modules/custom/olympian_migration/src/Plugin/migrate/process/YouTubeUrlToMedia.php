<?php
namespace Drupal\olympian_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\media\Entity\Media;

/**
 * Converts a YouTube URL into a 'remote_video' media entity.
 *
 * @MigrateProcessPlugin(
 *   id = "youtube_url_to_media"
 * )
 */
class YouTubeUrlToMedia extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Initialize an empty array to store media entity IDs
    $media_ids = [];
    // Check if the value is an array
    if (is_array($value)) {
      foreach ($value as $url) {
        // Process each URL in the array
        $media_id = $this->processUrl($url);
        if ($media_id) {
          $media_ids[] = $media_id;
        }
      }
    } else {
      // Process a single URL
      return $this->processUrl($value);
    }

    // Return the array of media entity IDs
    return $media_ids;
  }

  /**
   * Process a single URL to create a media entity.
   *
   * @param string $url
   *   The YouTube URL.
   *
   * @return int|null
   *   The ID of the created media entity, or NULL if not created.
   */
  private function processUrl($url) {
    if (!empty($url)) {
      // Convert embed URL to standard watch URL
      $url = $this->convertEmbedUrlToWatchUrl($url);

      // Create a media entity of type 'remote_video' with the YouTube URL
      $media = Media::create([
        'bundle' => 'remote_video', // Ensure this matches your media bundle machine name
        'field_media_oembed_video' => $url,
        'name' => $url,
      ]);
      $media->save();
      return $media->id();
    }
    return NULL;
  }

/**
 * Convert YouTube URLs to the standard watch URL format.
 *
 * @param string $url
 *   The YouTube URL.
 *
 * @return string
 *   The converted URL.
 */
private function convertEmbedUrlToWatchUrl($url) {
  // Parse the URL to extract components
  $parsed_url = parse_url($url);
  
  // Handle URLs like 'youtube.com/watch' with path and query params
  if (strpos($parsed_url['path'], '/watch') !== FALSE && !empty($parsed_url['query'])) {
    parse_str($parsed_url['query'], $query_params);
    if (isset($query_params['v'])) {
      return "https://www.youtube.com/watch?v=" . $query_params['v'];
    }
  }

  // Handle shortened YouTube URLs like 'youtu.be'
  if (strpos($parsed_url['host'], 'youtu.be') !== FALSE) {
    $videoId = ltrim($parsed_url['path'], '/');
    return "https://www.youtube.com/watch?v=" . $videoId;
  }

  // Handle embed URLs
  if (strpos($parsed_url['path'], '/embed/') !== FALSE) {
    $videoId = ltrim(str_replace('/embed/', '', $parsed_url['path']), '/');
    return "https://www.youtube.com/watch?v=" . $videoId;
  }

  // Handle non-standard watch URLs (e.g., 'watch/<id>' instead of 'watch?v=<id>')
  if (strpos($parsed_url['path'], '/watch/') !== FALSE) {
    $videoId = ltrim(str_replace('/watch/', '', $parsed_url['path']), '/');
    return "https://www.youtube.com/watch?v=" . $videoId;
  }

  // Default case: return the original URL
  return $url;
}

  

}

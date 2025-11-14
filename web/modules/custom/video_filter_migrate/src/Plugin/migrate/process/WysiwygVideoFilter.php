<?php

namespace Drupal\video_filter_migrate\Plugin\migrate\process;

use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Masterminds\HTML5;

/**
 * Processes YouTube video embeds in WYSIWYG content and uses UUIDs.
 *
 * @MigrateProcessPlugin(
 *   id = "wysiwyg_video_filter"
 * )
 */
class WysiwygVideoFilter extends ProcessPluginBase implements MigrateProcessInterface {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $htmlContent = is_array($value) && isset($value['value']) ? $value['value'] : $value;
    if (is_string($htmlContent)) {
      $processedHtml = $this->processHtmlContent($htmlContent, $row);

      if (is_array($value)) {
        $value['value'] = $processedHtml;
        return $value;
      } else {
        return $processedHtml;
      }
    }

    return $value;
  }

  protected function processHtmlContent($htmlContent, Row $row) {

     // Check if $htmlContent is just plain text without HTML tags
     if (strip_tags($htmlContent) === $htmlContent) {
      // If it's plain text, process it directly
      return $this->processText($htmlContent, $row);
    }

    $html5 = new HTML5();
    $dom = $html5->loadHTML($htmlContent);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//text()') as $textNode) {
        $newNodeValue = $this->processText($textNode->nodeValue, $row);

        if ($newNodeValue !== $textNode->nodeValue) {
            // Create a document fragment to hold the new HTML.
            $fragment = $dom->createDocumentFragment();
            // Append raw HTML to the fragment.
            $fragment->appendXML($newNodeValue);
            // Replace the text node with the fragment.
            $textNode->parentNode->replaceChild($fragment, $textNode);
        }
    }

    return $html5->saveHTML($dom->documentElement);
}


  protected function processText($text, Row $row) {
    // Match and process [video:...] tags.
    // Ignore any parameters given after the URL, for example: [video: https://youtu.be/4_MJC-iT7IY width:500 height:500].

    // Match and process YouTube URLs
    $text = preg_replace_callback('/\[video:.*?(?:(?:youtu\.?be(?:\.com)?\/)(?:embed\/|watch\?v=)?)([a-zA-Z0-9_-]+)\??\s*?.*?\]/', function ($matches) use ($row) {
      // The regex match will grab the YouTube video ID from strings like the following.  You can test it out on https://regex101.com/.
      //   [video:https://www.youtube.com/watch?v=KIsL7F17zPo]
      //   [video:https://www.youtube.com/watch?v=KIsL7F17zPo&feature=youtu.be]
      //   [video:https://youtu.be/FdeioVndUhs]
      //   [video:https://www.youtube.com/embed/5nuZIgL2-VU]
      //   [video:https://www.youtube.com/embed/5nuZIgL2-VU  width:500 height:500]
      //   [video: https://youtu.be/4_MJC-iT7IY  width:500 height:500]
      // Convert into a full YouTube URL
      $youtubeUrl = 'https://www.youtube.com/watch?v=' . $matches[1];
      $mediaUuid = $this->createOrGetMediaUuid($youtubeUrl, $row);
      return $this->generateEmbedCode($mediaUuid);
    }, $text);

    // Match and process Vimeo URLs
    $text = preg_replace_callback('/(?:\[video:\s*?(https:\/\/(?:www\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|)(\d+)(?:|\/\?).*?)\s*?.*?\])/', function ($matches) use ($row) {
      // Convert any Vimeo URL to a simple Vimeo URL.
      // (regex based on https://github.com/regexhq/vimeo-regex?tab=readme-ov-file)
      // $matches[1] is the original URL, $matches[2] is the video ID.
      // Examples for $matches[2]:
      //   [video:https://vimeo.com/62092214] ==> 62092214
      //   [video:https://www.vimeo.com/62092214] ==> 62092214
      //   [video:https://vimeo.com/channels/documentaryfilm/128373915] ==> 128373915
      //   [video:https://vimeo.com/groups/musicvideo/videos/126199390] ==> 126199390
      //   [video:https://vimeo.com/62092214?query=foo] ==> 62092214
      $vimeoUrl = 'https://vimeo.com/' . $matches[2];
      $mediaUuid = $this->createOrGetMediaUuid($vimeoUrl, $row);
      return $this->generateEmbedCode($mediaUuid);
  }, $text);

    return $text;
}


  /**
   * Creates a new media entity or returns the UUID of an existing one for the given URL.
   */
  protected function createOrGetMediaUuid($url, Row $row) {
    $media_type = $this->configuration['target_media_type'];
    $url_field = $this->configuration['url_field'];  // Use the configured URL field

    $media_ids = \Drupal::entityQuery('media')
      ->condition($url_field, $url)
      ->condition('bundle', $media_type)
      ->accessCheck(FALSE)
      ->execute();

    if ($media_ids) {
      $media = Media::load(reset($media_ids));
      return $media->uuid();
    }

    $media = Media::create([
      'bundle' => $media_type,
      $url_field => $url,  // Use the configured URL field
      'name' => $url,
      'status' => TRUE,
    ]);
    $media->save();

    return $media->uuid();
  }

  /**
   * Generates the media embed code using the UUID of the media entity.
   */
  protected function generateEmbedCode($mediaUuid) {
    // Generate embed code using UUID.
    // Adjust this markup according to your site's media embedding method.
    return '<drupal-media data-entity-type="media" data-entity-uuid="' . $mediaUuid . '"></drupal-media>';
  }

}

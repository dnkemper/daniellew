<?php

namespace Drupal\olympian_migration\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\file\Plugin\migrate\source\d7\File as D7File;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Source plugin for migrating files and mapping field_folder to field_media_directory.
 *
 * @MigrateSource(
 *   id = "filtered_file_d7",
 *   source_module = "file"
 * )
 */
class FilteredFileD7 extends D7File {
  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Fetch the taxonomy term ID for field_folder in D7.
    $row->setSourceProperty('fid', $row->getSourceProperty('fid'));
    $folder_tid = $this->getFolderTid($row->getSourceProperty('fid'));

    // Process file metadata for images.
    $fileType = explode('/', $row->getSourceProperty('filemime'))[0];
    if ($fileType === 'image') {
      $meta = $this->fetchMeta($row);
      $row->setSourceProperty('meta', $meta);
    }

    // Make sure uri is lowercase
    // (to prevent separate folders for things like "/Events" and "/events")
    $lowercaseUri = strtolower($row->getSourceProperty('uri'));
    $row->setSourceProperty('uri', $lowercaseUri);

    // If uri did not have an extension, then set it, and also use the filename source property for the name
    $uri_parts = pathinfo($row->getSourceProperty('uri'));
    if (!array_key_exists('extension', $uri_parts)) {

      // Figure out the extension from filename or filemime.
      $filename_parts = pathinfo($row->getSourceProperty('filename'));
      if (array_key_exists('extension', $filename_parts)) {
        // Filename looks good, just use it
        $newName = $row->getSourceProperty('filename');
      } else {
        // Assemble name from filename and mimetype
        // (Note: using the mimetype info, the extension might end up being something like "octet-stream".)
        $newName = $row->getSourceProperty('filename') . '.' . explode('/', $row->getSourceProperty('filemime'))[1];
      }

      // On the uri, the dirname is the scheme, like "public:"
      $dirname = $uri_parts['dirname'] ?? '';
      if ($dirname != '') {
        if (substr($dirname, -1) == ':' and strpos($dirname, "://") === false) {
          // If the uri is "public://blahblah", then pathinfo returns dirname of "public:",
          // so here we're checking if there's a ":" at the end of dirname.
          // Then assemble the new name as: "public:" + "//" + "blahblah.thetype"
          $newName = $dirname . '//' . $newName;
        } else {
          // If the uri is "public://Events/blahblah", then pathinfo returns dirname of "public://Events",
          // Then assemble the new name as: "public://Events" + "/" + "blahblah.thetype".
          $newName = $dirname . '/' . $newName;
        }
      }

      $this->getLogger('olympian_migration')->notice('Renaming uri due to missing extension: [fid:@fid] "@oldUri" -> "@newUri"', [
        '@fid' => $row->getSourceProperty('fid'),
        '@oldUri' => $row->getSourceProperty('uri'),
        '@newUri' => $newName,
      ]);

      $row->setSourceProperty('uri', $newName);
    }

    if ($folder_tid) {
      $row->setSourceProperty('field_media_directory', $folder_tid);
    }

    return TRUE;
  }

  /**
   * Get the taxonomy term ID for the field_folder field in Drupal 7.
   *
   * @param int $fid
   *   The file ID from Drupal 7.
   *
   * @return int|null
   *   The term ID or NULL if not found.
   */
  protected function getFolderTid($fid) {
    $query = $this->select('field_data_field_folder', 'f')
      ->fields('f', ['field_folder_tid'])
      ->condition('f.entity_id', $fid, '=')
      ->condition('f.entity_type', 'file', '=')
      ->condition('f.deleted', 0, '=');

    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    if (!empty($this->configuration['filemime'])) {
      $query->condition('f.filemime', $this->configuration['filemime'], 'IN');
    }
    if (!empty($this->configuration['filetype'])) {
      $query->condition('f.type', $this->configuration['filetype'], 'IN');
    }

    // Join the field_folder table to fetch the taxonomy term ID.
    $query->leftJoin('field_data_field_folder', 'fld', 'fld.entity_id = f.fid AND fld.entity_type = :entity_type', [
      ':entity_type' => 'file',
    ]);
    $query->addField('fld', 'field_folder_tid', 'field_media_directory');
    // Filter by file type, if configured.
    // if (isset($this->configuration['type'])) {
    //   $query->condition('f.type', $this->configuration['type']);
    // }

    // Get the alt text, if configured.
    if (isset($this->configuration['get_alt'])) {
      $alt_alias = $query->addJoin('left', 'field_data_field_file_image_alt_text', 'alt', 'f.fid = %alias.entity_id');
      $query->addField($alt_alias, 'field_file_image_alt_text_value', 'alt');
    }

    // Get the title text, if configured.
    if (isset($this->configuration['get_title'])) {
      $title_alias = $query->addJoin('left', 'field_data_field_file_image_title_text', 'title', 'f.fid = %alias.entity_id');
      $query->addField($title_alias, 'field_file_image_title_text_value', 'title');
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['field_media_directory'] = $this->t('The taxonomy term ID of the media directory from field_folder.');
// $fields['type'] = $this->t('The type of file.');
    $fields['alt'] = $this->t('Alt text of the file (if present)');
    $fields['title'] = $this->t('Title text of the file (if present)');
    return $fields;
  }

  // /**
  //  * {@inheritdoc}
  //  */
  // protected function initializeIterator() {
  //   $this->privatePath = $this->variableGet('file_private_path', NULL);
  //   $publicPath = $this->variableGet('file_public_path', NULL);
  //   if (is_null($publicPath)) {
  //     $publicPath = \Drupal::config('migrate_plus.migration_group.olympian_d7')
  //       ->get('shared_configuration.source.constants.public_file_path');
  //   }
  //   $this->publicPath = $publicPath;
  //   // We need to skip over the direct parent and go directly
  //   // to its parent to avoid re-setting the publicPath.
  //   return DrupalSqlBase::initializeIterator();
  // }
  /**
   * Fetch image metadata for alt and title text.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row.
   *
   * @return array
   *   An array containing metadata values.
   */
  public function fetchMeta($row) {
    if (!$this->database->schema()->tableExists('field_data_field_file_image_alt_text') ||
      !$this->database->schema()->tableExists('field_data_field_file_image_title_text')) {
      return [];
    }
    $query = $this->select('file_managed', 'f');
    $query->join('field_data_field_file_image_alt_text', 'a', 'a.entity_id = f.fid');
    $query->join('field_data_field_file_image_title_text', 't', 't.entity_id = f.fid');

    $result = $query->fields('a', [
      'field_file_image_alt_text_value',
    ])
      ->fields('t', [
        'field_file_image_title_text_value',
      ])
      ->condition('f.fid', $row->getSourceProperty('fid'))
      ->execute();

    return $result->fetchAssoc();
  }
  /**
   * Pre-rollback event to delete the migration-created media entities.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   The migration event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function preRollback(MigrateRollbackEvent $event) {
    $migration_id = $event->getMigration()->id();
    $migrate_map = 'm_map_' . $migration_id;
    // Get our destination file ids.
    $connection = Database::getConnection();
    $query = $connection->select($migrate_map, 'mm')
      ->fields('mm', ['destid1']);
    $fids = $query->execute()->fetchCol();

    // Grab our image media entities that reference files to be removed.
    $query1 = $connection->select('media__field_media_image', 'm_image')
      ->fields('m_image', ['entity_id'])
      ->condition('m_image.field_media_image_target_id', $fids, 'in');
    // Grab our file media entities that reference files to be removed.
    $query2 = $connection->select('media__field_media_file', 'm_file')
      ->fields('m_file', ['entity_id'])
      ->condition('m_file.field_media_file_target_id', $fids, 'in');
    $results = $query1->execute()->fetchCol();
    $results = array_merge($results, $query2->execute()->fetchCol());

    $entityManager = \Drupal::service('entity_type.manager')
      ->getStorage('media');
    $mediaEntities = $entityManager->loadMultiple($results);
    $entityManager->delete($mediaEntities);
  }
}

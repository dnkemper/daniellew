<?php

namespace Drupal\olympian_migration\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;
use Drupal\Core\Database\Database;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Drush commandfile for Olympian Migration.
 */
final class OlympianMigrationDrushCommands extends DrushCommands {

  /**
   * Writes migration mappings for specified migrations.
   *
   * @command olympian_migration:write_mappings
   * @aliases om-write-mappings
   * @usage olympian_migration:write_mappings
   *   Writes migration mappings for specified migrations.
   */
  #[CLI\Command(name: 'olympian_migration:write_mappings', aliases: ['om-write-mappings'])]
  public function writeMappings() {
    $this->output()->writeln("Writing migration mappings...");

    $migration_ids = ['olympian_d7_node_complete_article', 'olympian_d7_node_complete_faculty_staff', 'olympian_d7_node_complete_book', 'olympian_d7_node_complete_events'];
    foreach ($migration_ids as $migration_id) {
      $this->writeMigrationMappingsToFile($migration_id);
    }

    $this->output()->writeln("Mappings for all migrations written successfully.");
  }

  /**
   * Function to write migration mappings to a file.
   *
   * @param string $migration_id The migration ID.
   */
  protected function writeMigrationMappingsToFile($migration_id) {
    $database = Database::getConnection();
    $query = $database->select('m_map_' . $migration_id, 'mm');
    $query->join('node', 'n', 'mm.destid1 = n.nid');
    $query->fields('mm', ['sourceid1'])
          ->fields('n', ['nid', 'nid']);

    $result = $query->execute();

    $directory = DRUPAL_ROOT . '/shared_content_exports';
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
    

    $current_remote = $_ENV['REMOTE'];
    $file_path = $directory . '/' . $current_remote . '_.csv';
    $fp = fopen($file_path, 'w');

    foreach ($result as $record) {
      $line = [
        'old_nid' => $record->sourceid1,
        'new_nid' => $record->nid,
        'new_uuid' => $record->uuid,
      ];
      fputcsv($fp, $line);
    }

    fclose($fp);

    $this->output()->writeln("Mappings for $migration_id written to $file_path.");
  }

}

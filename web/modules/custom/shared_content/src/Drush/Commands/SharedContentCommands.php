<?php

namespace Drupal\shared_content\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Utility\Token;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\aggregator\Entity\Feed;
use Drupal\Core\Database\Connection;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Importer\ConfigImporterBatch;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\shared_content\Service\SharedContentFeedRefresher;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;


/**
 * A Drush commandfile.
 */
final class SharedContentCommands extends DrushCommands {
  /**
   * The feed refresher service.
   *
   * @var \Drupal\shared_content\Service\SharedContentFeedRefresher
   */
  protected $feedRefresher;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;
  /**
   * The ServiceOPMLImporter service.
   *
   * @var use Drupal\shared_content\Service\ServiceOPMLImporter;
   */
  private $ServiceOPMLImporter;
  /**
   * The ServiceOPMLImporter service.
   *
   * @var use Drupal\shared_content\Service\SharedContentUpdater;
   */
  private $SharedContentUpdater;  /**
   * Constructs a SharedContentCommands object.
   */
  public function __construct(
    SharedContentFeedRefresher $feedRefresher,
    QueueFactory $queueFactory,
    QueueWorkerManagerInterface $queueWorkerManager,
    private readonly Token $token,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly MessengerInterface $messenger,
    private readonly ClientInterface $httpClient,
    private readonly Connection $connection,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly AssetCollectionOptimizerInterface $cssCollectionOptimizer,
    private readonly AssetCollectionOptimizerInterface $jsCollectionOptimizer,
    private readonly StorageInterface $activeStorage,
    private readonly StorageInterface $syncStorage,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ConfigManagerInterface $configManager,
    private readonly LockBackendInterface $lock,
    private readonly TypedConfigManagerInterface $typedConfig,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ModuleInstallerInterface $moduleInstaller,
    private readonly ThemeHandlerInterface $themeHandler,
    private readonly TranslationInterface $stringTranslation,
    private readonly ModuleExtensionList $extensionListModule,
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ?AssetQueryStringInterface $assetQueryString = NULL,
  ) {
    parent::__construct();
    $this->feedRefresher = $feedRefresher;
    $this->queueFactory = $queueFactory;
    $this->queueWorkerManager = $queueWorkerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      // ⚠️ CRITICAL: These three services must be first to match constructor order
      $container->get('shared_content.feed_refresher'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),

      // Original services
      $container->get('token'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('database'),

      // Cache/Asset services
      $container->get('cache_tags.invalidator'),
      $container->get('asset.css.collection_optimizer'),
      $container->get('asset.js.collection_optimizer'),

      // Config services
      $container->get('config.storage'),
      $container->get('config.storage.sync'),
      $container->get('event_dispatcher'),
      $container->get('config.manager'),
      $container->get('lock'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('theme_handler'),
      $container->get('string_translation'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
      $container->get('config.factory'),

      // Optional asset query string service (Drupal 10.2+)
      $container->has('asset.query_string') ? $container->get('asset.query_string') : NULL,
    );
  }

  // ============================================================================
  // SECTION: NEW HASH POPULATION COMMANDS
  // ============================================================================

  /**
   * Populate content hash for all existing shared content nodes.
   *
   * @command shared-content:populate-hashes
   * @aliases sc-populate-hash,sc-hash
   * @option dry-run Preview without saving.
   * @option type Limit to specific content type(s).
   * @usage shared-content:populate-hashes
   *   Calculate and save content hashes for all shared content nodes.
   * @usage shared-content:populate-hashes --dry-run
   *   Preview what would be updated without saving.
   * @usage shared-content:populate-hashes --type=article
   *   Only process article nodes.
   */
  public function populateHashes(array $options = ['dry-run' => FALSE, 'type' => NULL]) {
    $this->output()->writeln('🔧 Populating content hashes for shared content nodes...');

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');

    if (!empty($options['type'])) {
      $types = array_map('trim', explode(',', $options['type']));
      $query->condition('type', $types, 'IN');
    } else {
      $query->condition('type', ['faculty_staff', 'events', 'article', 'book'], 'IN');
    }

    $nids = $query->execute();

    if (empty($nids)) {
      $this->output()->writeln('⚠️  No shared content nodes found.');
      return;
    }

    $total = count($nids);
    $this->output()->writeln("📊 Found {$total} nodes to process.\n");

    $updated = 0;
    $skipped = 0;
    $errors = 0;

    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      $nid = $node->id();
      $url = $node->get('field_shared_content_xml')->value;

      // Skip if no URL
      if (empty($url)) {
        $this->output()->writeln("⏭️  Node {$nid}: No XML URL, skipping");
        $skipped++;
        continue;
      }

      // Check if hash already exists
      if ($node->hasField('field_content_hash') && !$node->get('field_content_hash')->isEmpty()) {
        $existing_hash = $node->get('field_content_hash')->value;
        $this->output()->writeln("✓ Node {$nid}: Hash already exists (" . substr($existing_hash, 0, 16) . "...)");
        $skipped++;
        continue;
      }

      try {
        // Fetch XML content
        $response = $this->httpClient->request('GET', $url, [
          'timeout' => 120,
          'http_errors' => FALSE,
        ]);

        if ($response->getStatusCode() !== 200) {
          $this->output()->writeln("❌ Node {$nid}: HTTP {$response->getStatusCode()} from {$url}");
          $errors++;
          continue;
        }

        $content = $response->getBody()->getContents();

        // Verify it's valid XML
        $xml = @simplexml_load_string($content);
        if (!$xml) {
          $this->output()->writeln("❌ Node {$nid}: Invalid XML from {$url}");
          $errors++;
          continue;
        }

        // Calculate hash
        $hash = hash('sha256', $content);
        $hash_display = substr($hash, 0, 16) . '...';

        if ($options['dry-run']) {
          $this->output()->writeln("🔍 Node {$nid}: Would set hash to {$hash_display}");
          $updated++;
        } else {
          // Save hash to node
          if ($node->hasField('field_content_hash')) {
            $node->set('field_content_hash', $hash);

            // Also set last fetch time
            if ($node->hasField('field_last_fetch')) {
              $node->set('field_last_fetch', time());
            }

            $node->save();
            $this->output()->writeln("✅ Node {$nid}: Hash saved ({$hash_display})");
            $updated++;
          } else {
            $this->output()->writeln("⚠️  Node {$nid}: Missing field_content_hash field");
            $errors++;
          }
        }
      }
      catch (\Exception $e) {
        $this->output()->writeln("❌ Node {$nid}: Error - {$e->getMessage()}");
        $errors++;
      }
    }

    // Summary
    $this->output()->writeln("\n" . str_repeat('=', 50));
    $this->output()->writeln("📊 Summary:");
    $this->output()->writeln("   Total nodes: {$total}");
    $this->output()->writeln("   ✅ Updated: {$updated}");
    $this->output()->writeln("   ⏭️  Skipped: {$skipped}");
    $this->output()->writeln("   ❌ Errors: {$errors}");

    if ($options['dry-run']) {
      $this->output()->writeln("\n💡 This was a dry run. Use without --dry-run to save changes.");
    }
  }

  /**
   * Recalculate and update content hash for specific node(s).
   *
   * @command shared-content:update-hash
   * @aliases sc-update-hash
   * @param string $nids Comma-separated node IDs or ranges (e.g., "123,456,100-110")
   * @option force Force update even if hash exists
   * @usage shared-content:update-hash 123
   *   Update hash for node 123
   * @usage shared-content:update-hash "123,456,789"
   *   Update hash for multiple nodes
   * @usage shared-content:update-hash "100-200"
   *   Update hash for nodes 100 through 200
   * @usage shared-content:update-hash 123 --force
   *   Force recalculate hash even if one exists
   */
  public function updateHash($nids, array $options = ['force' => FALSE]) {
    $ids = $this->parseIdList($nids);

    if (empty($ids)) {
      $this->logger()->error('No valid node IDs provided.');
      return self::EXIT_FAILURE;
    }

    $this->output()->writeln("🔧 Updating hashes for " . count($ids) . " node(s)...\n");

    $nodes = Node::loadMultiple($ids);
    $updated = 0;
    $errors = 0;

    foreach ($nodes as $node) {
      $nid = $node->id();

      // Check if node has shared content XML
      if (!$node->hasField('field_shared_content_xml') || $node->get('field_shared_content_xml')->isEmpty()) {
        $this->output()->writeln("⚠️  Node {$nid}: Not a shared content node");
        $errors++;
        continue;
      }

      // Skip if hash exists and not forcing
      if (!$options['force'] && $node->hasField('field_content_hash') && !$node->get('field_content_hash')->isEmpty()) {
        $existing = $node->get('field_content_hash')->value;
        $this->output()->writeln("⏭️  Node {$nid}: Hash exists (" . substr($existing, 0, 16) . "...), use --force to recalculate");
        continue;
      }

      $url = $node->get('field_shared_content_xml')->value;

      try {
        $response = $this->httpClient->request('GET', $url, [
          'timeout' => 120,
          'http_errors' => FALSE,
        ]);

        if ($response->getStatusCode() !== 200) {
          $this->output()->writeln("❌ Node {$nid}: HTTP {$response->getStatusCode()}");
          $errors++;
          continue;
        }

        $content = $response->getBody()->getContents();
        $hash = hash('sha256', $content);

        if ($node->hasField('field_content_hash')) {
          $node->set('field_content_hash', $hash);

          if ($node->hasField('field_last_fetch')) {
            $node->set('field_last_fetch', time());
          }

          $node->save();
          $this->output()->writeln("✅ Node {$nid}: Hash updated to " . substr($hash, 0, 16) . "...");
          $updated++;
        } else {
          $this->output()->writeln("⚠️  Node {$nid}: Missing field_content_hash field");
          $errors++;
        }
      }
      catch (\Exception $e) {
        $this->output()->writeln("❌ Node {$nid}: {$e->getMessage()}");
        $errors++;
      }
    }

    $this->output()->writeln("\n✅ Updated: {$updated}, ❌ Errors: {$errors}");
    return $updated > 0 ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  /**
   * Manually create the hash and fetch timestamp fields.
   *
   * @command shared-content:create-fields
   * @aliases sc-create-fields
   * @usage shared-content:create-fields
   */
  public function createFields() {
    $content_types = ['faculty_staff', 'events', 'article', 'book'];

    $this->output()->writeln('🔧 Creating field storage...');

    // Create field_content_hash storage
    if (!\Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_content_hash')) {
      \Drupal\field\Entity\FieldStorageConfig::create([
        'field_name' => 'field_content_hash',
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
        'settings' => [
          'max_length' => 64,
        ],
      ])->save();
      $this->output()->writeln('✅ Created field_content_hash storage');
    } else {
      $this->output()->writeln('ℹ️  field_content_hash storage already exists');
    }

    // Create field_last_fetch storage
    if (!\Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_last_fetch')) {
      \Drupal\field\Entity\FieldStorageConfig::create([
        'field_name' => 'field_last_fetch',
        'entity_type' => 'node',
        'type' => 'timestamp',
        'cardinality' => 1,
      ])->save();
      $this->output()->writeln('✅ Created field_last_fetch storage');
    } else {
      $this->output()->writeln('ℹ️  field_last_fetch storage already exists');
    }

    $this->output()->writeln('🔧 Adding fields to content types...');

    // Add fields to each content type
    foreach ($content_types as $content_type) {
      // Add field_content_hash
      if (!\Drupal\field\Entity\FieldConfig::loadByName('node', $content_type, 'field_content_hash')) {
        \Drupal\field\Entity\FieldConfig::create([
          'field_name' => 'field_content_hash',
          'entity_type' => 'node',
          'bundle' => $content_type,
          'label' => 'Content Hash',
          'description' => 'SHA-256 hash of remote XML content for change detection.',
          'required' => FALSE,
        ])->save();
        $this->output()->writeln("✅ Added field_content_hash to {$content_type}");
      }

      // Add field_last_fetch
      if (!\Drupal\field\Entity\FieldConfig::loadByName('node', $content_type, 'field_last_fetch')) {
        \Drupal\field\Entity\FieldConfig::create([
          'field_name' => 'field_last_fetch',
          'entity_type' => 'node',
          'bundle' => $content_type,
          'label' => 'Last Fetch Time',
          'description' => 'Timestamp of last successful fetch from remote source.',
          'required' => FALSE,
        ])->save();
        $this->output()->writeln("✅ Added field_last_fetch to {$content_type}");
      }
    }

    // Clear cache
    drupal_flush_all_caches();

    // Initialize state tracking
    $state = \Drupal::state();
    if (!$state->get('shared_content.active_nodes')) {
      $state->set('shared_content.active_nodes', []);
      $this->output()->writeln('✅ Initialized active nodes tracking');
    }

    $this->output()->writeln('🎉 All fields created successfully!');
  }

  // ============================================================================
  // SECTION: CONFIG IMPORT/EXPORT COMMANDS
  // ============================================================================

  /**
   * Flushes all caches just like the Admin Toolbar Flush-all button.
   *
   * @command flush:all
   * @aliases flush-all,fa
   * @usage drush flush:all
   * @validate-module-enabled shared_content
   */
  public function flushAll(): void {
    $this->output()->writeln('<info>⏳ Flushing all Drupal caches…</info>');

    // Core "Flush all".
    drupal_flush_all_caches();

    // Clear CSS/JS aggregates and invalidate library_info
    $this->cacheTagsInvalidator->invalidateTags(['library_info']);
    $this->cssCollectionOptimizer->deleteAll();
    $this->jsCollectionOptimizer->deleteAll();

    // Reset cache-busting query string in Drupal 10.2+
    if ($this->assetQueryString) {
      $this->assetQueryString->reset();
    }

    // Extra safety: bump state-based cache-buster
    \Drupal::state()->set('system.css_js_query_string', (string) time());

    $this->output()->writeln('<info>✅ All caches flushed.</info>');
  }

  /**
   * Import a single configuration object from a YAML file.
   *
   * @command config:single:import
   * @aliases csi,config-single-import
   * @param string $name Config name.
   * @option dir Directory where the YAML file is located.
   * @option file Full path to the YAML file.
   * @option preview Show the changes without applying them.
   * @usage drush csi system.site
   * @usage drush csi system.site --file=/tmp/system.site.yml
   * @validate-module-enabled shared_content
   */
  public function importSingle(string $name, array $options = ['dir' => NULL, 'file' => NULL, 'preview' => FALSE]): int {
    $inputPath = NULL;

    if (!empty($options['file'])) {
      $inputPath = (string) $options['file'];
      if (!str_ends_with(strtolower($inputPath), '.yml')) {
        $inputPath .= '.yml';
      }
    }
    elseif (!empty($options['dir'])) {
      $dir = rtrim((string) $options['dir'], '/');
      $inputPath = Path::join($dir, $name . '.yml');
    }

    $is_file = $inputPath && is_file($inputPath) && is_readable($inputPath);
    $data = NULL;

    if ($is_file) {
      $data = Yaml::decode((string) file_get_contents($inputPath));
      if (!is_array($data)) {
        $this->logger()->error("❌ Could not parse YAML file: {$inputPath}");
        return self::EXIT_FAILURE;
      }
    }
    else {
      $data = $this->syncStorage->read($name);
      if (!is_array($data)) {
        $this->logger()->error("❌ Config '{$name}' was not found in the sync directory.");
        return self::EXIT_FAILURE;
      }
    }

    $source = new StorageReplaceDataWrapper($this->activeStorage);
    $source->replaceData($name, $data);
    $comparer = new StorageComparer($source, $this->activeStorage);
    $comparer->createChangelist();

    if (!$comparer->hasChanges()) {
      $this->logger()->notice("ℹ️ No changes detected for '{$name}'.");
      return self::EXIT_SUCCESS;
    }

    if (!empty($options['preview'])) {
      $this->printChangelist($comparer, $name);
      return self::EXIT_SUCCESS;
    }

    $ok = $this->runConfigImport($comparer);
    if ($ok) {
      $this->configFactory->reset();
      $this->logger()->success("✅ Successfully imported '{$name}'.");
      return self::EXIT_SUCCESS;
    }

    $this->logger()->error("❌ Failed importing '{$name}'.");
    return self::EXIT_FAILURE;
  }

  /**
   * Export a single configuration object to YAML.
   *
   * @command config:single:export
   * @aliases cse,config-single-export
   * @param string $name Config name.
   * @option dir Destination directory.
   * @option file Exact destination file path.
   * @usage drush cse system.site
   * @validate-module-enabled shared_content
   */
  public function exportSingle(string $name, array $options = ['dir' => NULL, 'file' => NULL]): int {
    $data = $this->activeStorage->read($name);
    if (!is_array($data)) {
      $this->logger()->error(sprintf('Config "%s" does not exist.', $name));
      return self::EXIT_FAILURE;
    }

    $yaml = Yaml::encode($data);

    if (!empty($options['file'])) {
      $path = (string) $options['file'];
      if (!str_ends_with(strtolower($path), '.yml')) {
        $path .= '.yml';
      }
      $this->ensureDirectoryWritable(Path::getDirectory($path));
      file_put_contents($path, $yaml);
      $this->logger()->success(sprintf('Exported "%s" to %s', $name, $path));
      return self::EXIT_SUCCESS;
    }

    if (!empty($options['dir'])) {
      $dir = rtrim((string) $options['dir'], '/');
      $this->ensureDirectoryWritable($dir);
      $path = Path::join($dir, $name . '.yml');
      file_put_contents($path, $yaml);
      $this->logger()->success(sprintf('Exported "%s" to %s', $name, $path));
      return self::EXIT_SUCCESS;
    }

    $this->syncStorage->write($name, $data);
    $this->logger()->success(sprintf('Exported "%s" to sync directory.', $name));
    return self::EXIT_SUCCESS;
  }

  /**
   * Run the config import with a batch.
   */
  private function runConfigImport(StorageComparer $comparer): bool {
    $importer = new ConfigImporter(
      $comparer,
      $this->eventDispatcher,
      $this->configManager,
      $this->lock,
      $this->typedConfig,
      $this->moduleHandler,
      $this->moduleInstaller,
      $this->themeHandler,
      $this->stringTranslation,
      $this->extensionListModule,
      $this->themeExtensionList
    );

    if ($importer->alreadyImporting()) {
      $this->logger()->warning('A configuration import is already running.');
      return FALSE;
    }

    if (!$importer->validate()) {
      $this->logger()->error('Configuration import did not validate.');
      return FALSE;
    }

    try {
      $steps = $importer->initialize();
      $batch = [
        'operations' => [],
        'init_message' => t('Initializing cleanup...'),
        'progress_message' => t('Processing @current of @total nodes...'),
        'error_message' => t('An error occurred during the cleanup process.'),
        'finished' => [ConfigImporterBatch::class, 'finish'],
        'title' => $this->stringTranslation->translate('Importing configuration'),
      ];

      foreach ($steps as $step) {
        $batch['operations'][] = [
          [ConfigImporterBatch::class, 'process'],
          [$importer, $step],
        ];
      }

      batch_set($batch);
      drush_backend_batch_process();
      return TRUE;
    }
    catch (ConfigImporterException $e) {
      $this->logger()->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Pretty-print a changelist for preview mode.
   */
  private function printChangelist(StorageComparer $comparer, string $name): void {
    $ops = [
      'create' => $comparer->getChangelist('create'),
      'update' => $comparer->getChangelist('update'),
      'delete' => $comparer->getChangelist('delete'),
      'rename' => $comparer->getChangelist('rename'),
    ];
    $this->io()->title(sprintf('Preview for "%s"', $name));
    foreach ($ops as $op => $list) {
      if (!empty($list)) {
        $this->io()->writeln(strtoupper($op) . ':');
        foreach ($list as $item) {
          $this->io()->writeln("  - {$item}");
        }
      }
    }
    $this->io()->newLine();
  }

  /**
   * Ensure directory exists & is writable.
   */
  private function ensureDirectoryWritable(string $dir): void {
    if ($dir === '' || $dir === '.' || $dir === '/') {
      return;
    }
    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0775, TRUE) && !is_dir($dir)) {
        throw new \RuntimeException(sprintf('Failed to create directory: %s', $dir));
      }
    }
    if (!is_writable($dir)) {
      throw new \RuntimeException(sprintf('Directory is not writable: %s', $dir));
    }
  }

  // ============================================================================
  // SECTION: EXISTING SHARED CONTENT COMMANDS
  // ============================================================================

  /**
   * Delete m_map* and m_message* migration mapping tables.
   *
   * @command drop-migrate-tables
   * @aliases dmt
   */
  public function dropMigrateTables() {
    $schema = $this->connection->schema();
    $database = $this->connection->getConnectionOptions()['database'];

    $query = $this->connection->query("
      SELECT table_name
      FROM information_schema.tables
      WHERE table_schema = :schema
        AND (table_name LIKE 'm_map%' OR table_name LIKE 'm_message%')
    ", [':schema' => $database]);

    $tables = $query->fetchCol();

    if (empty($tables)) {
      $this->output()->writeln("❌ No matching migration tables found.");
      return;
    }

    foreach ($tables as $table) {
      $this->output()->writeln("🗑 Dropping table: $table");
      try {
        $schema->dropTable($table);
      } catch (\Exception $e) {
        $this->output()->writeln("❌ Error dropping $table: " . $e->getMessage());
      }
    }

    $this->output()->writeln("✅ Done. All matching tables have been dropped.");
  }

  /**
   * Update existing nodes with field values from their XML feed.
   *
   * @command shared-content:update-existing
   * @aliases scu-existing
   */
  public function updateExistingNodesFromXml() {
    $this->output()->writeln("🔄 Updating existing shared content nodes...");

    $types = ['faculty_staff', 'article', 'book', 'events'];
    foreach ($types as $type) {
      $nids = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $type)
        ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
        ->execute();

      if (empty($nids)) {
        $this->loggerFactory->get('shared_content')->notice("No nodes of type $type with shared content URLs.");
        continue;
      }

      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        $sharedURL = $node->get('field_shared_content_xml')->value;
        $xml = $this->fetchXmlContent($sharedURL, TRUE);
        $node->setNewRevision(TRUE);
        $node->setChangedTime(\Drupal::time()->getRequestTime());
        if ($xml) {
          $xmlElement = $xml->node;

          if (empty($xmlElement) || count((array) $xmlElement) === 0) {
            $this->loggerFactory->get('shared_content')->notice("🗑 Deleting node {$node->id()} — empty XML content.");
            $node->delete();
            continue;
          }

          $this->applyXmlValuesToNode($node, $type, $xmlElement, $xml, $sharedURL);
          $node->save();

          $this->loggerFactory->get('shared_content')->info("✅ Node {$node->id()} updated from XML feed.");
        }
        else {
          $this->loggerFactory->get('shared_content')->warning("⚠️ Failed to fetch XML for node {$node->id()}.");
          $node->delete();
        }
      }
    }

    $this->messenger->addStatus("✅ All existing nodes updated from their shared content XML.");
  }

  /**
   * Delete shared content nodes with empty or invalid XML.
   *
   * @command shared-content:cleanup-empty-xml
   * @aliases scu-cleanup
   */
  public function cleanupEmptyXmlNodes() {
    $this->output()->writeln("🧹 Starting cleanup of nodes with empty XML...");

    $content_types = ['faculty_staff', 'article', 'book', 'events'];

    $batch = [
      'title' => t('Deleting nodes with empty or invalid shared content XML.'),
      'operations' => [],
      'init_message' => t('Initializing cleanup...'),
      'progress_message' => t('Processing @current of @total nodes...'),
      'error_message' => t('An error occurred during the cleanup process.'),
      'finished' => [static::class, 'cleanupBatchFinished'],
    ];

    foreach ($content_types as $type) {
      $nids = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', $type)
        ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
        ->execute();

      foreach ($nids as $nid) {
        $batch['operations'][] = [[static::class, 'cleanupNodeBatch'], [$nid]];
      }
    }

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Batch operation callback: Delete node if XML is empty.
   */
//   public static function cleanupNodeBatch($nid, &$context) {
//     $node = Node::load($nid);
//
//     if ($node && $node->hasField('field_shared_content_xml') && !$node->get('field_shared_content_xml')->isEmpty()) {
//       $sharedURL = $node->get('field_shared_content_xml')->value;
//
//       try {
//         $httpClient = \Drupal::service('http_client');
//         $response = $httpClient->request('GET', $sharedURL, ['timeout' => 30]);
//
//         if ($response->getStatusCode() === 200) {
//           $xml = @simplexml_load_string($response->getBody()->getContents());
//
//           if (!$xml) {
//             \Drupal::logger('shared_content')->notice("🗑 Deleting node {$nid}: Invalid XML.");
//             $node->delete();
//           }
//           else {
//             $xmlElement = str_contains($sharedURL, 'artsci.washu.edu') ? $xml->item : $xml->node;
//             if (empty($xmlElement) || count((array) $xmlElement) === 0) {
//               \Drupal::logger('shared_content')->notice("🗑 Deleting node {$nid}: Empty XML element.");
//               $node->delete();
//             }
//           }
//         } else {
//           \Drupal::logger('shared_content')->warning("❌ Failed to fetch XML for node {$nid}.");
//           $node->delete();
//         }
//       }
//       catch (\Exception $e) {
//         \Drupal::logger('shared_content')->error("❌ Exception fetching XML for node {$nid}: " . $e->getMessage());
//         $node->delete();
//       }
//     }
//   }
public static function cleanupNodeBatch($nid, &$context) {
  $node = Node::load($nid);

  if (!$node) {
    \Drupal::logger('shared_content')->warning("⚠️ Node {$nid} no longer exists.");
    print "⚠️ Node {$nid} no longer exists.\n";
    return;
  }

  $title  = $node->label();
  $bundle = $node->bundle();

  if ($node->hasField('field_shared_content_xml') && !$node->get('field_shared_content_xml')->isEmpty()) {
    $sharedURL = (string) $node->get('field_shared_content_xml')->value;

    try {
      $httpClient = \Drupal::service('http_client');
      $logger = \Drupal::logger('shared_content');

      $response = $httpClient->request('GET', $sharedURL, ['timeout' => 30]);

      if ($response->getStatusCode() === 200) {
        $xml = @simplexml_load_string($response->getBody()->getContents());

        if (!$xml) {
          $logger->notice("🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Invalid XML.");
          print "🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Invalid XML.\n";
          $node->delete();
          $context['message'] = "Deleted node {$nid} '{$title}'";
        }
        else {
          $xmlElement = $xml->node;
          if (empty($xmlElement) || count((array) $xmlElement) === 0) {
            $logger->notice("🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Empty XML element.");
            print "🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Empty XML element.\n";
            $node->delete();
            $context['message'] = "Deleted node {$nid} '{$title}'";
          }
        }
      }
      else {
        $logger->warning("❌ Fetch XML failed for node {$nid} '{$title}' [{$bundle}] (HTTP {$response->getStatusCode()}). Deleting.");
        print "❌ Fetch XML failed for node {$nid} '{$title}' [{$bundle}] (HTTP {$response->getStatusCode()}). Deleting.\n";
        $node->delete();
        $context['message'] = "Deleted node {$nid} '{$title}'";
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('shared_content')->error("❌ Exception fetching XML for node {$nid} '{$title}' [{$bundle}]: " . $e->getMessage() . " — Deleting.");
      print "❌ Exception for node {$nid} '{$title}' [{$bundle}]: " . $e->getMessage() . " — Deleting.\n";
      $node->delete();
      $context['message'] = "Deleted node {$nid} '{$title}'";
    }
  }
  else {
    \Drupal::logger('shared_content')->notice("🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Missing field_shared_content_xml.");
    print "🗑 Deleting node {$nid} '{$title}' [{$bundle}]: Missing field_shared_content_xml.\n";
    $node->delete();
    $context['message'] = "Deleted node {$nid} '{$title}'";
  }
}
  /**
   * Batch finish callback.
   */
  public static function cleanupBatchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus(t('✅ Cleanup complete.'));
    } else {
      \Drupal::messenger()->addError(t('❌ Cleanup encountered errors.'));
    }
  }

  /**
   * Applies field values to an existing node based on XML data.
   */
  private function applyXmlValuesToNode(Node $node, string $type, \SimpleXMLElement $xmlElement, \SimpleXMLElement $xml, string $sharedURL): void {
    $node->set('field_shared_content', base64_encode($xml->asXML()));
    $node->set('field_shared_content_xml', $sharedURL);

    if ($type == 'events' && isset($xmlElement->eventDate)) {
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
        }
      }
      $eventTBD = (int) $xmlElement->eventTBD;
      $node->set('field_event_date_tbd', $eventTBD);

      $dateStart = isset($xmlElement->eventDateStart) ? (string) $xmlElement->eventDateStart : NULL;
      $dateEnd = isset($xmlElement->eventDateEnd) ? (string) $xmlElement->eventDateEnd : NULL;

      if ($dateStart) {
        $start = strtotime($dateStart);
        $end = $dateEnd ? strtotime($dateEnd) : NULL;
        if ($start) {
          $node->set('field_show_end_date', TRUE);
          $node->set('field_event_smart_date', ['value' => $start, 'end_value' => $end]);
        }
      }
      else {
        $dateField = (string) $xmlElement->eventDate;
        $dates = explode(' to ', $dateField);
        if (count($dates) === 2) {
          $start = strtotime(trim($dates[0]));
          $end = strtotime(trim($dates[1]));
          if ($start) {
            $node->set('field_show_end_date', TRUE);
            $node->set('field_event_smart_date', ['value' => $start, 'end_value' => $end ?: NULL]);
          }
        }
      }
    }

    if ($type === 'article' && isset($xmlElement->postDate)) {
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
        }
      }
      $date = \DateTime::createFromFormat('n.j.y', (string) $xmlElement->postDate);
      $node->setCreatedTime($date ? $date->getTimestamp() : \Drupal::time()->getCurrentTime());
    }

    if ($type === 'faculty_staff') {
      if ($node->hasField('field_external_link')) {
        if (isset($xmlElement->externalURL) && !empty((string) $xmlElement->externalURL)) {
          $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
        }
      }
      $firstName = isset($xmlElement->firstName) ? (string) $xmlElement->firstName : '';
      $lastName = isset($xmlElement->lastName) ? (string) $xmlElement->lastName : '';

      if (!empty($firstName) || !empty($lastName)) {
        $node->set('field_first_name', $firstName);
        $node->set('field_last_name', $lastName);
        $node->setTitle(trim("$firstName $lastName"));
      }
      else {
        $node->setTitle((string) $xmlElement->title);
      }
    }
  }

  /**
   * Imports OPML feeds manually.
   *
   * @command shared_content:opml
   * @aliases sc-opml
   */
  public function importOpml() {
    $form = \Drupal::service('shared_content.remote_form');
    if ($form instanceof \Drupal\shared_content\Form\RemoteForm) {
      $form->importFeedsFromOpml();
    }
    $this->logger()->success('OPML feeds imported successfully.');
  }

  /**
   * Delete aggregator feeds with URLs containing a specific domain.
   *
   * @command shared-content:delete-feeds
   * @aliases sc-delete-feeds
   * @option url The URL pattern to match.
   * @usage drush sc-delete-feeds --url="https://publicscholarship.wustl.edu"
   */
  public function deleteAggregatorFeeds(array $options = ['url' => NULL]): int {
    $target = $options['url'] ?? NULL;

    if (empty($target)) {
      $this->logger()->error('You must provide a --url option.');
      return self::EXIT_FAILURE;
    }

    $storage = $this->entityTypeManager->getStorage('aggregator_feed');
    $ids = \Drupal::entityQuery('aggregator_feed')->accessCheck(FALSE)->execute();

    if (empty($ids)) {
      $this->logger()->notice('No aggregator feeds found.');
      return self::EXIT_SUCCESS;
    }

    $feeds = $storage->loadMultiple($ids);
    $count = 0;

    foreach ($feeds as $feed) {
      if (str_contains($feed->get('url')->value, $target)) {
        $this->logger()->notice("🗑 Deleting feed '{$feed->label()}'");
        $feed->delete();
        $count++;
      }
    }

    $this->logger()->success("✅ Deleted {$count} feed(s).");
    return self::EXIT_SUCCESS;
  }

  /**
   * Delete specific aggregator feeds by title or URL.
   *
   * @command shared-content:delete-feeds-by-filter
   * @aliases sc-edel
   * @option title Feed title.
   * @option url Feed URL.
   * @usage drush sc-edel --title="Digital Commons"
   */
  public function deleteFeeds(array $options = ['title' => NULL, 'url' => NULL]): int {
    $matchTitle = trim((string) ($options['title'] ?? ''));
    $matchUrl = trim((string) ($options['url'] ?? ''));

    if ($matchTitle === '' && $matchUrl === '') {
      $this->logger()->error('❌ Please provide at least one of --title or --url.');
      return self::EXIT_FAILURE;
    }

    $titlePattern = $matchTitle !== '' ? '/' . preg_quote($matchTitle, '/') . '/i' : NULL;
    $urlPattern = $matchUrl !== '' ? '/' . preg_quote($matchUrl, '/') . '/i' : NULL;

    $feedStorage = $this->entityTypeManager->getStorage('aggregator_feed');
    $feeds = $feedStorage->loadMultiple(
      \Drupal::entityQuery('aggregator_feed')->accessCheck(FALSE)->execute()
    );

    $deletedFeeds = 0;
    $deletedItems = 0;
    $deletedNodes = 0;

    foreach ($feeds as $feed) {
      $title = (string) $feed->label();
      $url = (string) $feed->get('url')->value;

      $titleMatch = $titlePattern ? preg_match($titlePattern, $title) : TRUE;
      $urlMatch = $urlPattern ? preg_match($urlPattern, $url) : TRUE;

      if ($titleMatch && $urlMatch) {
        // Delete aggregator_items
        $itemIds = \Drupal::entityQuery('aggregator_item')
          ->accessCheck(FALSE)
          ->condition('fid', $feed->id())
          ->execute();
        if ($itemIds) {
          $items = $this->entityTypeManager->getStorage('aggregator_item')->loadMultiple($itemIds);
          foreach ($items as $item) {
            $item->delete();
            $deletedItems++;
          }
        }

        // Delete nodes
        $nodeIds = \Drupal::entityQuery('node')
          ->accessCheck(FALSE)
          ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
          ->execute();
        if ($nodeIds) {
          $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nodeIds);
          foreach ($nodes as $node) {
            $shared = (string) $node->get('field_shared_content_xml')->value;
            if ($urlPattern && preg_match($urlPattern, $shared)) {
              $node->delete();
              $deletedNodes++;
              $this->output()->writeln("🗑 Deleted shared content node {$node->id()} for feed URL: {$shared}");
            }
          }
        }

        $this->output()->writeln("🗑 Deleting feed: '{$title}' ({$url})");
        $feed->delete();
        $deletedFeeds++;
      }
    }

    if ($deletedFeeds === 0) {
      $this->logger()->warning('⚠️ No matching feeds found.');
    } else {
      $this->logger()->success("✅ Deleted {$deletedFeeds} feed(s), {$deletedItems} item(s), {$deletedNodes} node(s).");
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Refresh all aggregator feeds.
   *
   * @command shared_content:refresh-service
   * @aliases sc-refresh-service sc-refreshserv
   */
  public function refreshService() {
    $refreshService = \Drupal::service('shared_content.feed_refresher');
    $refreshService->run();
  }

  /**
   * Refresh all aggregator feeds.
   *
   * @command shared_content:refresh-feeds
   * @aliases sc-refresh artsci:feeds
   */
  public function refreshFeeds() {
    $batch = [
      'title' => t('Refreshing Aggregator Feeds'),
      'operations' => [],
      'init_message' => t('Initializing cleanup...'),
      'progress_message' => t('Processing @current of @total nodes...'),
      'error_message' => t('An error occurred during the cleanup process.'),
      'finished' => [static::class, 'batchFinished'],
    ];

    $feed_ids = $this->entityTypeManager->getStorage('aggregator_feed')->getQuery()->accessCheck()->execute();

    if (empty($feed_ids)) {
      $this->logger()->notice('No aggregator feeds found to refresh.');
      return;
    }

    foreach ($feed_ids as $feed_id) {
      $batch['operations'][] = [[static::class, 'refreshFeedBatch'], [$feed_id]];
    }

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Batch process callback to refresh a single feed.
   */
  public static function refreshFeedBatch($feed_id, &$context) {
    $feed = Feed::load($feed_id);
    if ($feed) {
      try {
        $feed->refreshItems();
        $context['message'] = t('Refreshing feed: @title', ['@title' => $feed->label()]);
        \Drupal::logger('aggregator')->notice('Feed @title refreshed.', ['@title' => $feed->label()]);
      }
      catch (\Exception $e) {
        \Drupal::logger('aggregator')->error('Error refreshing feed @title: @message', [
          '@title' => $feed->label(),
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Batch finish callback for refreshFeeds.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('All aggregator feeds have been refreshed.'));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred while refreshing feeds.'));
    }
  }

  /**
   * Update shared content XML field for all specified content types.
   *
   * @command shared-content:update-xml
   * @aliases scu
   */
  public function updateSharedContentXml() {
    $this->output()->writeln("🔄 Starting shared content XML field updates...");

    $content_types = ['faculty_staff', 'book', 'article', 'events'];
    foreach ($content_types as $type) {
      $this->output()->writeln("📌 Updating nodes of type: $type");
      $this->updateNodesByContentType($type);
    }

    $this->messenger->addStatus("✅ Shared content XML field update completed.");
  }

  /**
   * Helper: update nodes of a specific content type.
   */
  private function updateNodesByContentType($content_type) {
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $content_type)
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
      ->execute();

    if (empty($nids)) {
      return;
    }

    $nodes = Node::loadMultiple($nids);
    foreach ($nodes as $node) {
      if ($node->hasField('field_shared_content_xml') && !$node->get('field_shared_content_xml')->isEmpty()) {
        $current_value = $node->get('field_shared_content_xml')->value;
        $updated_value = $this->replaceOldDomains($current_value);

        if ($current_value !== $updated_value) {
          $node->set('field_shared_content_xml', $updated_value);
          $xml = $this->fetchXmlContent($updated_value, FALSE);

          if ($xml) {
            $xml_string = $xml->asXML();
            if ($xml_string) {
              $encoded_xml = base64_encode($xml_string);
              $node->set('field_shared_content', $encoded_xml);
              $node->save();
            }
          } else {
            $node->delete();
          }
        }
      }
    }
  }

  /**
   * Update nodes from their shared-content XML.
   *
   * @command shared-content:update-one
   * @aliases scuo
   * @option nids Comma/ranges.
   * @option type Comma list.
   * @option limit Limit.
   * @option force Bypass caches.
   * @option dry-run Preview changes.
   */
  public function updateOne(array $options = [
    'nids' => '',
    'type' => NULL,
    'limit' => 0,
    'force' => FALSE,
    'dry-run' => FALSE,
  ]): int {
    $ids = $this->parseIdList((string) ($options['nids'] ?? ''));

    if (empty($ids)) {
      $q = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');

      if (!empty($options['type'])) {
        $bundles = array_map('trim', explode(',', (string) $options['type']));
        $q->condition('type', $bundles, 'IN');
      }
      if ((int) ($options['limit'] ?? 0) > 0) {
        $q->range(0, (int) $options['limit']);
      }
      $ids = $q->execute();
    }

    if (empty($ids)) {
      $this->logger()->notice('No nodes matched.');
      return self::EXIT_SUCCESS;
    }

    $noFilters = empty($options['nids']) && empty($options['type']) && (int) ($options['limit'] ?? 0) === 0;
    if ($noFilters) {
      $count = is_array($ids) ? count($ids) : 0;
      if (!$this->io()->confirm("This will update ALL {$count} shared-content nodes. Continue?", FALSE)) {
        $this->logger()->warning('Aborted.');
        return self::EXIT_FAILURE;
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->loadMultiple($ids);
    $changed = 0;

    foreach ($nodes as $node) {
      if ($node->get('field_shared_content_xml')->isEmpty()) {
        continue;
      }

      $url = (string) $node->get('field_shared_content_xml')->value;
      $xml = $this->fetchXmlContent($url, (bool) $options['force']);
      if (!$xml) {
              $this->io()->writeln("❌ {$node->id()} failed to fetch XML");
              $node->delete();

        continue;
      }

      $xmlElement = $xml->node;
      if (empty($xmlElement) || count((array) $xmlElement) === 0) {
              $this->io()->writeln("⚠️  {$node->id()} XML element empty");

        continue;
      }

      if ($options['dry-run']) {
        $clone = $node->createDuplicate();
        $this->applyXmlValuesToNode($clone, $clone->bundle(), $xmlElement, $xml, $url);
        $diff = $this->diffNode($node, $clone, [
          'title','field_external_link','field_event_smart_date','field_first_name','field_last_name'
        ]);
        $this->printDiff($node->id(), $diff);
        continue;
      }

      $this->applyXmlValuesToNode($node, $node->bundle(), $xmlElement, $xml, $url);
      $node->setNewRevision(TRUE);
          $node->setChangedTime(\Drupal::time()->getRequestTime());

      $node->save();
      $changed++;
          $this->io()->writeln("✅ Updated node {$node->id()}");

    }

    $this->logger()->notice("Done. Updated {$changed} node(s).");
    return self::EXIT_SUCCESS;
  }

  private function diffNode(Node $a, Node $b, array $fields): array {
    $diff = [];
    foreach ($fields as $f) {
      if (!$a->hasField($f) || !$b->hasField($f)) { continue; }
      $va = $a->get($f)->toArray();
      $vb = $b->get($f)->toArray();
      if ($va !== $vb) {
        $diff[$f] = ['from' => $va, 'to' => $vb];
      }
    }
    if ($a->label() !== $b->label()) {
      $diff['title'] = ['from' => $a->label(), 'to' => $b->label()];
    }
    return $diff;
  }

  private function printDiff(int $nid, array $diff): void {
    if (!$diff) {
      $this->io()->writeln("= NID {$nid}: no changes");
      return;
    }
    $this->io()->writeln("= NID {$nid} changes:");
    foreach ($diff as $k => $v) {
      $from = json_encode($v['from']);
      $to = json_encode($v['to']);
      $this->io()->writeln("  - {$k}: {$from} ⇒ {$to}");
    }
  }

  /**
   * Show field-level diffs for nodes vs their XML.
   *
   * @command shared-content:diff
   * @aliases scdiff
   * @option nids Comma/ranges.
   * @option type Comma list.
   * @option limit Limit.
   * @option force Bypass caches.
   */
  public function diff(array $options = [
    'nids' => '',
    'type' => NULL,
    'limit' => 0,
    'force' => FALSE,
  ]): int {
    $ids = $this->parseIdList((string) ($options['nids'] ?? ''));

    if (empty($ids)) {
      $q = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');

      if (!empty($options['type'])) {
        $bundles = array_map('trim', explode(',', (string) $options['type']));
        $q->condition('type', $bundles, 'IN');
      }
      if ((int) ($options['limit'] ?? 0) > 0) {
        $q->range(0, (int) $options['limit']);
      }
      $ids = $q->execute();
    }

    if (empty($ids)) {
      $this->logger()->notice('No nodes to diff.');
      return self::EXIT_SUCCESS;
    }

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    foreach ($nodes as $node) {
      if ($node->get('field_shared_content_xml')->isEmpty()) { continue; }
      $url = (string) $node->get('field_shared_content_xml')->value;
      $xml = $this->fetchXmlContent($url, (bool) $options['force']);
    if (!$xml) { $this->io()->writeln("❌ {$node->id()} failed to fetch XML"); $node->delete(); continue; }
      $xmlElement = $xml->node;
      $clone = $node->createDuplicate();
      $this->applyXmlValuesToNode($clone, $clone->bundle(), $xmlElement, $xml, $url);
      $diff = $this->diffNode($node, $clone, [
        'title','field_external_link','field_event_smart_date','field_first_name','field_last_name'
      ]);
      $this->printDiff($node->id(), $diff);
    }
    return self::EXIT_SUCCESS;
  }

  /**
   * Audit shared-content health.
   *
   * @command shared-content:audit
   * @aliases scaudit
   * @option type Restrict to bundle.
   * @option fix-domains Run domain replacements.
   * @option limit Max nodes.
   */
  public function audit(array $options = ['type' => NULL, 'fix-domains' => FALSE, 'limit' => 0]): int {
    $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');
    if (!empty($options['type'])) {
      $query->condition('type', $options['type']);
    }
    if ((int)$options['limit'] > 0) {
      $query->range(0, (int)$options['limit']);
    }
    $nids = $query->execute();
    if (!$nids) {
      $this->io()->writeln('No nodes to audit.');
      return self::EXIT_SUCCESS;
    }

    $bad = 0;
    $ok = 0;

    foreach (Node::loadMultiple($nids) as $n) {
      $url = (string) $n->get('field_shared_content_xml')->value;
      $suggested = $options['fix-domains'] ? $this->replaceOldDomains($url) : $url;

      try {
        $res = $this->httpClient->request('GET', $suggested, ['timeout' => 20]);
        $status = $res->getStatusCode();
        $xml = $status === 200 ? @simplexml_load_string((string)$res->getBody()) : NULL;
        $okxml = $xml && (isset($xml->item) || isset($xml->node));
        if ($status === 200 && $okxml) { $ok++; }
        else { $bad++; }
        $this->io()->writeln(sprintf(
          "%s NID %d: HTTP %s XML:%s %s",
          ($status === 200 && $okxml ? '✅' : '❌'),
          $n->id(),
          $status,
          $okxml ? 'OK' : 'BAD',
          $url !== $suggested ? "(suggest: $suggested)" : ''
        ));
      } catch (\Exception $e) {
        $bad++;
        $this->io()->writeln("❌ NID {$n->id()}: {$e->getMessage()}");
      }
    }
    $this->io()->writeln("Summary: OK={$ok}, BAD={$bad}");
    return self::EXIT_SUCCESS;
  }

  /**
   * Refresh a single Aggregator feed.
   *
   * @command shared-content:refresh-feed-one
   * @aliases sc-refresh-one
   * @option id Feed ID.
   * @option url Feed URL.
   */
  public function refreshOne(array $options = ['id' => NULL, 'url' => NULL]): int {
    $feed = NULL;
    if (!empty($options['id'])) {
      $feed = Feed::load((int)$options['id']);
    }
    elseif (!empty($options['url'])) {
      $ids = \Drupal::entityQuery('aggregator_feed')->accessCheck(FALSE)->condition('url', $options['url'])->execute();
      if ($ids) { $feed = Feed::load(reset($ids)); }
    }

    if (!$feed) {
      $this->logger()->error('Feed not found.');
      return self::EXIT_FAILURE;
    }

    try {
      $feed->refreshItems();
      $this->logger()->success('Feed refreshed: ' . $feed->label());
      return self::EXIT_SUCCESS;
    } catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Apply domain replacements.
   *
   * @command shared-content:fix-domains
   * @aliases sc-fix-domains
   * @option type Restrict to bundle.
   * @option dry-run Preview only.
   */
  public function fixDomains(array $options = ['type' => NULL, 'dry-run' => FALSE]): int {
    $q = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');
    if (!empty($options['type'])) { $q->condition('type', $options['type']); }
    $nids = $q->execute();
    if (!$nids) {
      $this->io()->writeln('No nodes to process.');
      return self::EXIT_SUCCESS;
    }

    $count = 0;
    foreach (Node::loadMultiple($nids) as $node) {
      $cur = (string) $node->get('field_shared_content_xml')->value;
      $upd = $this->replaceOldDomains($cur);
      if ($upd !== $cur) {
        $this->io()->writeln("NID {$node->id()}: $cur  =>  $upd");
        if (!$options['dry-run']) {
          $node->set('field_shared_content_xml', $upd);
          $node->setNewRevision(TRUE);
          $node->save();
        }
        $count++;
      }
    }
    $this->logger()->notice("Changed {$count} node(s).");
    return self::EXIT_SUCCESS;
  }

  /**
   * Enqueue shared-content updates.
   *
   * @command shared-content:queue-updates
   * @aliases scq
   * @option nids Comma/ranges.
   * @option type Comma list.
   * @option limit Limit.
   * @option force Bypass caches.
   * @option purge Purge existing queue.
   */
  public function queueUpdates(array $options = [
    'nids' => '',
    'type' => NULL,
    'limit' => 0,
    'force' => FALSE,
    'purge' => FALSE,
  ]): int {
    $queue = $this->queueFactory->get('shared_content_update');

    if (!empty($options['purge'])) {
      $queue->deleteQueue();
      $this->io()->writeln('🧹 Purged existing queue items.');
    }

    $ids = $this->parseIdList((string) ($options['nids'] ?? ''));
    if (empty($ids)) {
      $q = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('field_shared_content_xml', NULL, 'IS NOT NULL');

      if (!empty($options['type'])) {
        $bundles = array_map('trim', explode(',', (string) $options['type']));
        $q->condition('type', $bundles, 'IN');
      }
      if ((int) ($options['limit'] ?? 0) > 0) {
        $q->range(0, (int) $options['limit']);
      }
      $ids = $q->execute();
    }

    if (empty($ids)) {
      $this->logger()->notice('No nodes to queue.');
      return self::EXIT_SUCCESS;
    }

    $noFilters = empty($options['nids']) && empty($options['type']) && (int) ($options['limit'] ?? 0) === 0;
    if ($noFilters) {
      $count = is_array($ids) ? count($ids) : 0;
      if (!$this->io()->confirm("This will enqueue ALL {$count} shared-content nodes. Continue?", FALSE)) {
        $this->logger()->warning('Aborted.');
        return self::EXIT_FAILURE;
      }
    }

    $force = (bool) $options['force'];
    foreach ($ids as $nid) {
      $queue->createItem(['nid' => (int) $nid, 'force' => $force, 'attempts' => 0]);
    }

    $this->logger()->success("Queued ".count($ids)." node(s).");
    return self::EXIT_SUCCESS;
  }

  /**
   * Helper: parse "1,2,10-20" into [1,2,10,...,20]
   */
  private function parseIdList(string $list): array {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $list))) as $part) {
      if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
        $out = array_merge($out, range((int) $m[1], (int) $m[2]));
      }
      elseif (ctype_digit($part)) {
        $out[] = (int) $part;
      }
    }
    return array_values(array_unique($out));
  }

  /**
   * Replace old domains with new ones.
   */
  private function replaceOldDomains(string $value): string {
    $replacements = [
      'https://computing.artsci.wustl.edu' => 'https://it.artsci.wustl.edu',
      'https://eps.wustl.edu' => 'https://eeps.wustl.edu',
      'https://artsci.wustl.edu' => 'https://artsci.washu.edu',
      'https://quantumsensors.wustl.edu' => 'https://quantumleaps.wustl.edu',
      'https://complit.wustl.edu' => 'https://complitandthought.wustl.edu',
      'https://german.wustl.edu' => 'https://complitandthought.wustl.edu',
      'https://graduateschool.wustl.edu' => 'https://gradstudies.artsci.wustl.edu',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $value);
  }

  private function fetchXmlContent(string $sharedURL, bool $force = FALSE): ?\SimpleXMLElement {
    $options = ['timeout' => 50];
    if ($force) {
      $options['query'] = ['_ts' => \Drupal::time()->getRequestTime()];
      $options['headers'] = [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
      ];
    }
    try {
      $response = $this->httpClient->request('GET', $sharedURL, $options);
      if ($response->getStatusCode() === 200) {
        return simplexml_load_string((string) $response->getBody());
      }
    } catch (\Exception $e) {
      $this->loggerFactory->get('shared_content')
        ->error("Error fetching XML from {$sharedURL}: " . $e->getMessage());
    }
    return NULL;
  }

  /**
   * Resave all shared content nodes.
   *
   * @command shared-content:save-shared-content
   * @aliases scsss
   */
  public function saveSharedContent() {
    $nids = $this->getNodesWithSharedContent();

    if (empty($nids)) {
      $this->logger()->notice('No shared content nodes found.');
      return;
    }

    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      $sharedURL = $node->get('field_shared_content_xml')->value;
      if (empty($sharedURL)) {
        continue;
      }

      $xml = $this->sharedContentGetXml($sharedURL);

      if (!$xml) {
        \Drupal::logger('shared_content')->warning("⚠️ Failed to load XML for node {$node->id()}");
        $node->delete();
        continue;
      }
      $xmlElement = $xml->node;

      if (empty($xmlElement) || count((array) $xmlElement) === 0) {
        \Drupal::logger('shared_content')->notice("🗑 Skipping node {$node->id()} — empty or invalid XML element.");
        $node->delete();
        continue;
      }

      $xml_string = $xml->asXML();
      $encoded_xml = base64_encode($xml_string);
      $emptyValues = [
        base64_encode('<?xml version="1.0" encoding="utf-8"?><nodes><node/></nodes>'),
        base64_encode('<?xml version="1.0" encoding="UTF-8"?><response><item/></response>'),
      ];

      if (!in_array($encoded_xml, $emptyValues, true)) {
        $this->applyXmlValuesToNode($node, $node->bundle(), $xmlElement, $xml, $sharedURL);
        $node->save();
      }
    }
  }

  /**
   * Update node fields.
   */
  public function updateNodeFields(Node $node, \SimpleXMLElement $xmlElement) {
    $type = $node->bundle();

    if ($type === 'events' && isset($xmlElement->eventDate)) {
      if ($node->hasField('field_external_link') && isset($xmlElement->externalURL)) {
        $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
      }
      $node->setTitle((string) $xmlElement->title);
      $node->set('field_event_date_tbd', (int) $xmlElement->eventTBD);

      $dateStart = isset($xmlElement->eventDateStart) ? (string) $xmlElement->eventDateStart : NULL;
      $dateEnd = isset($xmlElement->eventDateEnd) ? (string) $xmlElement->eventDateEnd : NULL;

      if ($dateStart) {
        $dateStartTimestamp = strtotime($dateStart);
        $dateEndTimestamp = $dateEnd ? strtotime($dateEnd) : $dateStartTimestamp;

        if ($dateStartTimestamp) {
          $node->set('field_event_smart_date', [
            'value' => $dateStartTimestamp,
            'end_value' => $dateEndTimestamp,
          ]);
        }
      }
    }

    if ($type === 'article' && isset($xmlElement->postDate)) {
      if ($node->hasField('field_external_link') && isset($xmlElement->externalURL)) {
        $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
      }
      $node->setTitle((string) $xmlElement->title);
      $dateTime = \DateTime::createFromFormat('n.j.y', (string) $xmlElement->postDate);

      if ($dateTime !== FALSE) {
        $node->setCreatedTime($dateTime->getTimestamp());
      }
    }

    if ($type === 'faculty_staff') {
      if ($node->hasField('field_external_link') && isset($xmlElement->externalURL)) {
        $node->set('field_external_link', ['uri' => (string) $xmlElement->externalURL]);
      }
      $firstName = isset($xmlElement->firstName) ? trim((string) $xmlElement->firstName) : '';
      $lastName = isset($xmlElement->lastName) ? trim((string) $xmlElement->lastName) : '';

      if (!empty($firstName) || !empty($lastName)) {
        $node->set('field_first_name', $firstName);
        $node->set('field_last_name', $lastName);
        $node->setTitle(trim("$firstName $lastName"));
      }
      else {
        $node->setTitle((string) $xmlElement->title);
      }
    }
  }

  /**
   * Load nodes with shared content XML.
   */
  public function getNodesWithSharedContent() {
    return \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['faculty_staff', 'events', 'article', 'book'], 'IN')
      ->condition('field_shared_content_xml', NULL, 'IS NOT NULL')
      ->execute();
  }

  /**
   * Fetch XML content from a URL.
   */
  public function sharedContentGetXml($url) {
    try {
      $response = $this->httpClient->get($url, ['timeout' => 30]);
      if ($response->getStatusCode() === Response::HTTP_OK) {
        return simplexml_load_string($response->getBody()->getContents());
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('shared_content')->error('Error: @message', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * Set private field value.
   *
   * @command shared-content:set-private
   * @aliases sc-set-private
   * @param int $value 1 or 0.
   * @usage shared-content:set-private 1
   */
  public function setFieldPrivate(int $value): void {
    if (!in_array($value, [0, 1], TRUE)) {
      $this->output()->writeln("❌ Invalid value.");
      return;
    }

    $nids = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(FALSE)->execute();

    if (empty($nids)) {
            $this->output()->writeln("⚠️ No nodes found.");

      return;
    }

    $nodes = Node::loadMultiple($nids);
    $count = 0;

    foreach ($nodes as $node) {
      if ($node->hasField('private')) {
        $node->set('private', $value);
        $node->save();
        $count++;
      }
    }

    $this->output()->writeln("✅ Updated {$count} nodes.");
  }

  /**
   * Process events.
   *
   * @command shared-content:events
   * @aliases sc-events
   */
  public function processEvents() {
    $now = new \DateTimeImmutable();
    $cutoff_date = $now->sub(new \DateInterval('P1Y'));

    $deleted = 0;
    $updated = 0;

    $entity_ids = \Drupal::entityQuery('node')
      ->condition('type', 'events')
      ->accessCheck(FALSE)
      ->execute();

    if (!$entity_ids) {
      return;
    }

    $nodes = Node::loadMultiple($entity_ids);

    foreach ($nodes as $node) {
      $nid = $node->id();

      if ($node->get('field_event_date_tbd')->value == 1) {
                \Drupal::logger('shared_content')->notice("🛑 Skipping TBD node {$nid}.");

        continue;
      }

      $smart_date_field = $node->get('field_event_smart_date')->first();
      $smart_date_value = $smart_date_field?->get('value')->getString();

      if (!$smart_date_value) {
                \Drupal::logger('shared_content')->warning("⚠️ Still missing Smart Date on node {$nid}. Skipping.");

        continue;
      }

      try {
        $event_date = ctype_digit($smart_date_value)
          ? (new \DateTimeImmutable())->setTimestamp((int) $smart_date_value)
          : new \DateTimeImmutable($smart_date_value);
      }
      catch (\Exception $e) {
        \Drupal::logger('shared_content')->error("❌ Invalid Smart Date '{$smart_date_value}' on node {$nid}: " . $e->getMessage());
        continue;
      }

      if ($event_date < $cutoff_date) {
        \Drupal::logger('shared_content')->notice("🗑 Deleting node {$nid} — event date {$event_date->format('Y-m-d H:i:s')} over 1 year old.");
        $node->delete();
        $deleted++;
      }
      else {
        $field_value = $node->get('field_event_date_alias')->value;
        $new_value = str_replace('current event', 'past event', $field_value);
        $node->set('field_event_date_alias', $new_value);
        $node->setNewRevision(TRUE);
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node->setRevisionUserId(1);
        $node->setRevisionLogMessage('Replaced "current event" with "past event"');
        $node->save();
        \Drupal::logger('shared_content')->notice("✏️ Updated alias for node {$nid} to '{$new_value}'.");
        $updated++;
      }
    }

    \Drupal::logger('shared_content')->notice("✅ Cron complete. Deleted: {$deleted}, Updated: {$updated}.");
  }

  /**
   * Queue all shared content nodes for refresh.
   *
   * @command shared-content:queue
   * @aliases sc-queue
   */
  public function queueNodes() {
    $this->feedRefresher->run();
    $this->output()->writeln('Shared content nodes queued for refresh.');
  }

  /**
   * Process the shared content queue.
   *
   * @command shared-content:process
   * @aliases sc-process
   * @option limit Maximum number of items.
   * @option time-limit Maximum execution time.
   * @usage shared-content:process --limit=100
   */
  public function processQueue(array $options = ['limit' => NULL, 'time-limit' => NULL]) {
    $queue = $this->queueFactory->get('shared_content_refresh');
    $queue_worker = $this->queueWorkerManager->createInstance('shared_content_refresh');

    $limit = $options['limit'] ?? NULL;
    $timeLimit = $options['time-limit'] ?? NULL;
    $startTime = time();
    $processed = 0;
    $failed = 0;

    $this->output()->writeln(sprintf('Processing queue with %d items...', $queue->numberOfItems()));

    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;

        if ($processed % 10 === 0) {
          $this->output()->writeln(sprintf('Processed %d items...', $processed));
        }
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        $queue->deleteItem($item);
        $failed++;
      }

      if ($limit && $processed >= $limit) {
        break;
      }

      if ($timeLimit && (time() - $startTime) >= $timeLimit) {
        break;
      }
    }

    $this->output()->writeln(sprintf(
      'Completed. Processed: %d, Failed: %d, Remaining: %d',
      $processed,
      $failed,
      $queue->numberOfItems()
    ));
  }

  /**
   * Process a specific node by ID.
   *
   * @command shared-content:process-node
   * @aliases sc-node
   * @param int $nid The node ID.
   */
  public function processNode($nid) {
    $this->output()->writeln(sprintf('Processing node %d...', $nid));

    try {
      $this->feedRefresher->processNode($nid);
      $this->output()->writeln('Node processed successfully.');
    }
    catch (\Exception $e) {
      $this->logger()->error('Error: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Check for and unpublish deleted nodes.
   *
   * @command shared-content:check-deleted
   * @aliases sc-deleted
   */
  public function checkDeleted() {
    $this->output()->writeln('Checking for deleted nodes...');
    $this->feedRefresher->processDeletedNodes();
    $this->output()->writeln('Deleted node check complete.');
  }

  /**
   * Clear the shared content queue.
   *
   * @command shared-content:clear-queue
   * @aliases sc-clear
   */
  public function clearQueue() {
    $queue = $this->queueFactory->get('shared_content_refresh');
    $count = $queue->numberOfItems();
    $queue->deleteQueue();
    $this->output()->writeln(sprintf('Cleared %d items from queue.', $count));
  }

}

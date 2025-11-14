<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\user\Entity\User;
use Drush\Boot\DrupalBootLevels;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Users command class.
 */
class CustomCommands extends DrushCommands implements SiteAliasManagerAwareInterface, ProcessManagerAwareInterface, SanitizePluginInterface {
  use SiteAliasManagerAwareTrait;
  use ProcessManagerAwareTrait;

  /**
   * Configuration that should be sanitized.
   *
   * @var array
   */
  protected $sanitizedConfig = [];

  /**
   * Add additional fields to status command output.
   *
   * @param mixed $result
   *   The command result.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook alter core:status
   *
   * @return array
   *   The altered command result.
   */
  public function alterStatus($result, CommandData $commandData) {
    if ($app = getenv('AH_SITE_GROUP')) {
      $result['application'] = $app;
    }

    return $result;
  }

  /**
   * Add custom field labels to the status command annotation data.
   *
   * @hook init core:status
   */
  public function initStatus(InputInterface $input, AnnotationData $annotationData) {
    $fields = explode(',', $input->getOption('fields'));
    $defaults = $annotationData->getList('default-fields');

    // If no specific fields were requested, add ours to the defaults.
    if ($fields == $defaults) {
      $annotationData->append('field-labels', "\n application: Application");
      array_unshift($defaults, 'application');
      $annotationData->set('default-fields', $defaults);
      $input->setOption('fields', $defaults);
    }
  }

  /**
   * Invoke BLT update command after sql:sync for remote targets only.
   *
   * @param mixed $result
   *   The command result.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook post-command sql:sync
   *
   * @throws \Exception
   */
  public function postSqlSync($result, CommandData $commandData) {
    $record = $this->siteAliasManager()->getAlias($commandData->input()->getArgument('target'));

    if ($record->isRemote()) {
      $process = $this->processManager()->drush($record, 'cache:rebuild');
      $process->run($process->showRealtime());

      $process = $this->processManager()->siteProcess(
        $record,
        [
          './vendor/bin/blt',
          'drupal:update',
        ],
        [
          'site' => $record->uri(),
        ]
      );

      $process->setWorkingDirectory($record->root() . '/..');
      $process->run($process->showRealtime());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    $record = $this->siteAliasManager()->getSelf();

    foreach ($this->sanitizedConfig as $config) {
      /** @var \Consolidation\SiteProcess\SiteProcess $process */
      $process = $this->processManager()->drush($record, 'config:delete', [
        $config,
      ]);

      $process->run();

      if ($process->isSuccessful()) {
        $this->logger()->success(dt('Deleted @config configuration.', [
          '@config' => $config,
        ]));
      }
      else {
        $this->logger()->warning(dt('Unable to delete @config configuration.'), [
          '@config' => $config,
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input) {
    $record = $this->siteAliasManager()->getSelf();

    $configs = [
      'migrate_plus.migration_group.olympian_migration',
      'sitenow_dispatch.settings',
    ];

    foreach ($configs as $config) {
      /** @var \Consolidation\SiteProcess\SiteProcess $process */
      $process = $this->processManager()->drush($record, 'config:get', [
        $config,
      ]);

      $process->run();

      if ($process->isSuccessful()) {
        $this->sanitizedConfig[] = $config;

        $messages[] = dt('Delete the @config configuration.', [
          '@config' => $config,
        ]);
      }
    }
  }

  /**
   * See:
   *
   * @hook pre-command config:import
   */
  public function setUuid() {
    // Clear cache in order to prevent errors after upgrading drupal.
    drupal_flush_all_caches();
    // Sets a hardcoded site uuid right before `drush config:import`.
    $staticUuidIsSet = \Drupal::state()->get('static_uuid_is_set');
    if (!$staticUuidIsSet) {
      $config_factory = \Drupal::configFactory();
      $config_factory->getEditable('olympian_core.settings')
        ->set('uuid', '0ceced7f-d4cb-41b3-bbec-56eb5e14d767')
        ->save();
      Drush::output()
        ->writeln('Setting the correct UUID for this project: done.');
      \Drupal::state()->set('static_uuid_is_set', 1);
    }
    $entity_type_manager = \Drupal::entityTypeManager();
    $permissions = array_keys(\Drupal::service('user.permissions')
      ->getPermissions());
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $entity_type_manager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      $role_permissions = $role->getPermissions();
      $differences = array_diff($role_permissions, $permissions);
      if ($differences) {
        foreach ($differences as $permission) {
          $role->revokePermission($permission);
        }
        $role->save();
      }
    }
  }

  /**
   * Show the database size.
   *
   * @command artsci:database:size
   *
   * @aliases ads
   *
   * @field-labels
   *   table: Table
   *   size: Size
   *
   * @return string
   *   The size of the database in megabytes.
   */
  public function databaseSize() {
    $selfRecord = $this->siteAliasManager()->getSelf();

    /** @var \Consolidation\SiteProcess\SiteProcess $process */
    $process = $this->processManager()->drush($selfRecord, 'core-status', [], [
      'fields' => 'db-name',
      'format' => 'json',
    ]);

    $process->run();
    $result = $process->getOutputAsJson();

    if (isset($result['db-name'])) {
      $db = $result['db-name'];
      $args = ["SELECT SUM(ROUND(((data_length + index_length) / 1024 / 1024), 2)) AS \"Size\" FROM information_schema.TABLES WHERE table_schema = \"$db\";"];
      $options = ['yes' => TRUE];
      $process = $this->processManager()->drush($selfRecord, 'sql:query', $args, $options);
      $process->mustRun();
      $output = trim($process->getOutput());
      return "{$output} MB";
    }
  }

  /**
   * Show tables larger than the input size.
   *
   * @param int $size
   *   The size in megabytes of table to filter on. Defaults to 1 MB.
   * @param mixed $options
   *   The command options.
   *
   * @command artsci:table:size
   *
   * @aliases ats
   *
   * @field-labels
   *   table: Table
   *   size: Size
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Tables in RowsOfFields output formatter.
   */
  public function tableSize(int $size = 1, $options = ['format' => 'table']) {
    $size = $this->input()->getArgument('size') * 1024 * 1024;
    $selfRecord = $this->siteAliasManager()->getSelf();
    $args = ["SELECT table_name AS \"Tables\", ROUND(((data_length + index_length) / 1024 / 1024), 2) \"Size in MB\" FROM information_schema.TABLES WHERE table_schema = DATABASE() AND (data_length + index_length) > $size ORDER BY (data_length + index_length) DESC;"];
    $options = ['yes' => TRUE];
    $process = $this->processManager()->drush($selfRecord, 'sql:query', $args, $options);
    $process->mustRun();
    $output = $process->getOutput();

    $rows = [];

    $output = explode(PHP_EOL, $output);
    foreach ($output as $line) {
      if (!empty($line)) {
        [$table, $table_size] = explode("\t", $line);

        $rows[] = [
          'table' => $table,
          'size' => $table_size . ' MB',
        ];
      }
    }

    $data = new RowsOfFields($rows);
    $data->addRendererFunction(function ($key, $cellData) {
      if ($key == 'first') {
        return "<comment>$cellData</>";
      }

      return $cellData;
    });

    return $data;
  }

  /**
   * Display a list of Drupal users.
   *
   * @param array $options
   *   An associative array of options.
   *
   * @command users:list
   *
   * @option status Filter by status of the account. Can be active or blocked.
   * @option roles Filter by accounts having a role. Use a comma-separated list for more than one.
   * @option no-roles Filter by accounts not having a role. Use a comma-separated list for more than one.
   * @option last-login Filter by last login date. Can be relative.
   * @usage users:list
   *   Display all users on the site.
   * @usage users:list --status=blocked
   *   Displays a list of blocked users.
   * @usage users:list --roles=admin
   *   Displays a list of users with the admin role.
   * @usage users:list --last-login="1 year ago"
   *   Displays a list of users who have logged in within a year.
   * @aliases user-list, list-users
   * @bootstrap full
   * @field-labels
   *   uid: User ID
   *   name: Username
   *   pass: Password
   *   mail: User mail
   *   theme: User theme
   *   signature: Signature
   *   signature_format: Signature format
   *   user_created: User created
   *   created: Created
   *   user_access: User last access
   *   access: Last access
   *   user_login: User last login
   *   login: Last login
   *   user_status: User status
   *   status: Status
   *   timezone: Time zone
   *   picture: User picture
   *   init: Initial user mail
   *   roles: User roles
   *   group_audience: Group Audience
   *   langcode: Language code
   *   uuid: Uuid
   * @table-style default
   * @default-fields uid,name,mail,roles,status,login
   *
   * @throws \Exception
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The users as a RowsOfFields.
   */
  public function listAll(array $options = [
    'status' => InputOption::VALUE_REQUIRED,
    'roles' => InputOption::VALUE_REQUIRED,
    'no-roles' => InputOption::VALUE_REQUIRED,
    'last-login' => InputOption::VALUE_REQUIRED,
  ]) {
    // Use an entityQuery to dynamically set property conditions.
    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('uid', 0, '!=');

    if (isset($options['status'])) {
      $query->condition('status', $options['status'], '=');
    }

    if (isset($options['roles'])) {
      $query->condition('roles', $options['roles'], 'IN');
    }

    if (isset($options['no-roles'])) {
      $query->condition('roles', $options['no-roles'], 'NOT IN');
    }

    if (isset($options['last-login'])) {
      $timestamp = strtotime($options['last-login']);
      $query->condition('login', 0, '!=');
      $query->condition('login', $timestamp, '>=');
    }

    $ids = $query->execute();

    if ($users = User::loadMultiple($ids)) {
      $rows = [];

      foreach ($users as $id => $user) {
        $rows[$id] = $this->infoArray($user);
      }

      $result = new RowsOfFields($rows);
      $result->addRendererFunction(function ($key, $cellData, FormatterOptions $options) {
        if (is_array($cellData)) {
                return implode("\n", $cellData);
        }
          return $cellData;
      });

      return $result;
    }
    else {
      throw new \Exception(dt('No users found.'));
    }
  }

  /**
   * Validate the users:list command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook validate users:list
   *
   * @throws \Exception
   */
  public function validateList(CommandData $commandData) {
    $input = $commandData->input();

    $options = [
      'blocked',
      'active',
    ];

    if ($status = $input->getOption('status')) {
      if (!in_array($status, $options)) {
        throw new \Exception(dt('Unknown status @status. Status must be one of @options.', [
          '@status' => $status,
          '@options' => implode(', ', $options),
        ]));
      }

      // Set the status to the key of the options array.
      $input->setOption('status', array_search($status, $options));
    }

    // Set the (no-)roles options to an array but validate each one exists.
    $actual = user_roles(TRUE);

    foreach (['roles', 'no-roles'] as $option) {
      if ($roles = $input->getOption($option)) {
        $roles = explode(',', $roles);

        // Throw an exception for non-existent roles.
        foreach ($roles as $role) {
          if (!isset($actual[$role])) {
            throw new \Exception(dt('Role @role does not exist.', [
              '@role' => $role,
            ]));
          }
        }

        $input->setOption($option, $roles);
      }
    }

    // Validate the last-login option.
    if ($last = $input->getOption('last-login')) {
      if (strtotime($last) === FALSE) {
        throw new \Exception(dt('Unable to convert @last to a timestamp.', [
          '@last' => $last,
        ]));
      }
    }
  }

  /**
   * Block and unblock users while keeping track of previous state.
   *
   * @command users:toggle
   * @usage users:toggle
   *   Block/unblock all users on the site. Based on previous state.
   * @aliases utog
   * @bootstrap full
   */
  public function toggle() {
    // Get all users.
    $ids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('uid', 0, '!=')
      ->execute();

    if ($users = User::loadMultiple($ids)) {
      // The toggle status is determined by the last command run.
      $status = \Drupal::state()->get('utog_status', 'unblocked');
      $previous = \Drupal::state()->get('utog_previous', []);

      $this->logger()->notice(dt('Toggle status: @status', [
        '@status' => $status,
      ]));

      if ($status == 'unblocked') {
        if (\Drupal::configFactory()->getEditable('user.settings')->get('notify.status_blocked')) {
          $this->logger()->warning(dt('Account blocked email notifications are currently enabled.'));
        }

        $block = [];

        foreach ($users as $user) {
          $name = $user->getAccountName();

          if ($user->isActive() == FALSE) {
            $previous[] = $name;
          }
          else {
            $block[] = $name;
          }
        }

        $block_list = implode(', ', $block);

        if (!$this->io()->confirm(dt(
          'You will block @names. Are you sure?',
          ['@names' => $block_list]
          ))) {
          throw new UserAbortException();
        }

        if (Drush::drush($this->siteAliasManager()->getSelf(), 'user:block', [$block_list])->mustRun()) {
          \Drupal::state()->set('utog_previous', $previous);
          \Drupal::state()->set('utog_status', 'blocked');
        }
      }
      else {
        if (\Drupal::configFactory()->getEditable('user.settings')->get('notify.status_activated')) {
          $this->logger()->warning(dt('Account activation email notifications are currently enabled.'));
        }

        if (empty($previous)) {
          $this->logger()->notice(dt('No previously-blocked users.'));
        }
        else {
          $this->logger()->notice(dt('Previously blocked users: @names.', ['@names' => implode(', ', $previous)]));
        }

        $unblock = [];

        foreach ($users as $user) {
          if (!in_array($user->getAccountName(), $previous)) {
            $unblock[] = $user->getAccountName();
          }
        }

        $unblock_list = implode(', ', $unblock);

        if (!$this->io()->confirm(dt('You will unblock @unblock. Are you sure?', ['@unblock' => $unblock_list]))) {
          throw new UserAbortException();
        }

        if (Drush::drush($this->siteAliasManager()->getSelf(), 'user:unblock', [$unblock_list])->mustRun()) {
          \Drupal::state()->set('utog_previous', []);
          \Drupal::state()->set('utog_status', 'unblocked');
        }
      }
    }
  }

  /**
   * A flatter and simpler array presentation of a Drupal $user object.
   *
   * @param \Drupal\user\Entity\User $account
   *   A user account object.
   *
   * @return array
   *   An array of user information.
   */
  protected function infoArray(User $account) {
    /** @var \Drupal\Core\Datetime\DateFormatter $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');

    return [
      'uid' => $account->id(),
      'name' => $account->getAccountName(),
      'pass' => $account->getPassword(),
      'mail' => $account->getEmail(),
      'user_created' => $account->getCreatedTime(),
      'created' => $date_formatter->format($account->getCreatedTime()),
      'user_access' => $account->getLastAccessedTime(),
      'access' => $date_formatter->format($account->getLastAccessedTime()),
      'user_login' => $account->getLastLoginTime(),
      'login' => $date_formatter->format($account->getLastLoginTime()),
      'user_status' => $account->get('status')->value,
      'status' => $account->isActive() ? 'active' : 'blocked',
      'timezone' => $account->getTimeZone(),
      'roles' => $account->getRoles(),
      'langcode' => $account->getPreferredLangcode(),
      'uuid' => $account->uuid->value,
    ];
  }

  /**
   * Prepare a site to run update hooks.
   *
   * @command artsci:debug:update-hook
   *
   * @aliases adu
   */
  public function setupSiteForDebuggingUpdate() {
    $selfRecord = $this->siteAliasManager()->getSelf();

    // This doesn't actually make a difference at this point, but is good to
    // have in case they eventually make it so that commands run inside another
    // command can actually respond to interaction.
    $options = [
      'yes' => TRUE,
    ];

    // Clear drush cache.
    /** @var \Consolidation\SiteProcess\SiteProcess $process */
    $process = $this->processManager()->drush($selfRecord, 'cache-clear', ['drush'], $options);
    $process->mustRun($process->showRealtime());

    // Sync from prod.
    $prod_alias = str_replace('.local', '.prod', $selfRecord->name());
    $process = $this->processManager()->drush($selfRecord, 'sql-sync', [
      $prod_alias,
      '@self',
    ], [
      ...$options,
      'create-db' => TRUE,
    ]);
    $process->mustRun($process->showRealtime());

    // Rebuild cache.
    $process = $this->processManager()->drush($selfRecord, 'cr', [], $options);
    $process->mustRun($process->showRealtime());

    // Sanitize SQL.
    $process = $this->processManager()->drush($selfRecord, 'sql-sanitize', [], $options);
    $process->mustRun($process->showRealtime());
  }

  /**
   * Query a site for information needed for compliance reporting.
   *
   * @command artsci:get:gtm-containers
   *
   * @aliases agetgtm
   *
   * @throws \Exception
   */
  public function getGtmContainerIds() {
    // Bootstrap Drupal so that we can query entities.
    if (!Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL)) {
      throw new \Exception(dt('Unable to bootstrap Drupal.'));
    }

    // Get a list of container ID's for GTM.
    $container_ids = [];

    $containers = \Drupal::entityTypeManager()
      ?->getStorage('google_tag_container')
      ?->loadMultiple();

    foreach ($containers as $container) {
      $container_ids[] = $container->container_id;
    }

    return implode(', ', $container_ids);
  }

}

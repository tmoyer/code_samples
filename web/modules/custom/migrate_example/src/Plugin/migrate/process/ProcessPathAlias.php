<?php

namespace Drupal\migrate_example_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes the path alias for the received row.
 *
 * Processes the received row so that it properly handles the source url aliases
 * to transform them into the destination path aliases.
 *
 * Example of use:
 *
 * @code
 *  path:
 *    - plugin: process_path_alias
 *      source: source
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "process_path_alias"
 * )
 */
class ProcessPathAlias extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  const NODE_MIGRATIONS = [
    'migrate_example_node_basic_page',
    'migrate_example_node_home_page',
    'migrate_example_node_space',
    'migrate_example_node_tenant',
    'migrate_example_node_webform',
  ];
  const USER_MIGRATIONS = [
    'migrate_example_user',
  ];
  const TAXONOMY_MIGRATIONS = [
    'migrate_example_taxonomy_term_space_types',
  ];
  const FILE_MIGRATIONS = [
    'migrate_example_managed_public_files',
  ];

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * The migration that is being executed.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface|null
   */
  protected ?MigrationInterface $migration;

  /**
   * {@inheritdoc}
   */
  public function __construct(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->container = $container;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\migrate\MigrateException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL): ProcessPathAlias|ContainerFactoryPluginInterface|static {
    if (!$migration) {
      throw new MigrateException('The process_path_alias process plugin needs access to the MigrationInterface');
    }

    return new static(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\migrate\MigrateSkipProcessException
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Split the received URL alias into the entity type and its id.
    $source = explode('/', $value);

    if (!empty($source)) {
      // Get the destination id of the received 'url_alias'.
      $destinationEntityId = $this->getDestinationEntityId(reset($source), end($source), $migrate_executable, $row);

      // Generate the destination 'path_alias' based in the destination id.
      if (!empty($destinationEntityId)) {
        // Remove the last element from the source array; the source id.
        array_pop($source);
        $path = "";
        foreach ($source as $path_chunk) {
          $path .= "/" . $path_chunk;
        }
        // Return the generated path.
        return $path . "/" . $destinationEntityId;
      }
    }

    // Throw an exception and skip the row if the 'path_alias' can't be created.
    throw new MigrateSkipRowException(sprintf("The 'url_alias' with path '%s' is not valid.", $value));
  }

  /**
   * Performs a lookup in the migration tables to retrieve the mid.
   *
   * @param string $entityType
   *   The received entity type.
   * @param string $sourceEntityId
   *   The received source entity id.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migrate executable interface.
   * @param \Drupal\migrate\Row $row
   *   The migration row being processed.
   *
   * @return int|array|null
   *   Returns the destination id for the received entity type and source id.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\migrate\MigrateSkipProcessException
   */
  protected function getDestinationEntityId(string $entityType, string $sourceEntityId, MigrateExecutableInterface $migrate_executable, Row $row): int|array|null {
    $destinationEntityId = [];

    // Get the migrations for the received entity type.
    $migrations = $this->getEntityMigrations($entityType);

    if (!empty($migrations)) {
      // Prepare the configuration for the MigrationLookup process plugin.
      $configuration = [
        'migration' => $migrations,
        'no_stub' => TRUE,
      ];

      // Use the MigrationLookup process plugin to obtain the destination id.
      $migrationLookup = MigrationLookup::create($this->container, $configuration, '', [], $this->migration);
      $destinationEntityId = $migrationLookup->transform($sourceEntityId, $migrate_executable, $row, '');
    }
    return $destinationEntityId;
  }

  /**
   * Returns the migrations for the received entityType.
   *
   * @param string $entityType
   *   The received entity type.
   *
   * @return array
   *   Returns the migrations array for the received entity type.
   */
  protected function getEntityMigrations(string $entityType): array {
    $entityMigrations = [];

    // Return the file migrations for the received entity type.
    switch ($entityType) {
      case 'file':
        $entityMigrations = self::FILE_MIGRATIONS;
        break;

      case 'node':
        $entityMigrations = self::NODE_MIGRATIONS;
        break;

      case 'user':
        $entityMigrations = self::USER_MIGRATIONS;
        break;

      case 'taxonomy':
        $entityMigrations = self::TAXONOMY_MIGRATIONS;
        break;

    }
    return $entityMigrations;
  }

}

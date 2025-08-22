<?php

namespace Drupal\migrate_example_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\Condition;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Source plugin that allows to retrieve media items and their fields from D7.
 *
 * Configuration keys:
 * - type: (optional) Filter the files for a particular type.
 * - scheme: (optional) Filter files according to their scheme.
 *
 * Usage example:
 *
 * @code
 *  source:
 *    plugin: media
 *    type: image
 *    scheme:
 *      - public
 *      - private
 * @endcode
 *
 * @MigrateSource(
 *   id = "media",
 *   source_module = "file"
 * )
 */
class Media extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'fid' => $this->t('File ID'),
      'uid' => $this->t('The {users}.uid who added the file. If set to 0, this file was added by an anonymous user.'),
      'filename' => $this->t('File name'),
      'filepath' => $this->t('File path'),
      'filemime' => $this->t('File MIME Type'),
      'status' => $this->t('The published status of a file.'),
      'timestamp' => $this->t('The time that the file was added.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['fid']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Prepare the main query to retrieve the files.
    $query = $this->select('file_managed', 'f')
      ->fields('f')
      ->orderBy('f.timestamp');

    // If the type is provided via the configuration, filter by type.
    if (isset($this->configuration['type'])) {

      // Check if the file_entity is enabled in order to use the `type` column
      // to discriminate the file types.
      if ($this->moduleExists('file_entity')) {
        $query->condition('f.type', (array) $this->configuration['type'], 'IN');
      }
    }

    // If the scheme is provided via the configuration, filter by scheme.
    if (isset($this->configuration['scheme'])) {
      $schemes = [];
      // Avoid retrieving items from the 'temporary' scheme.
      $valid_schemes = array_diff((array) $this->configuration['scheme'], ['temporary']);
      // Accept either a single scheme, or an array of schemes.
      foreach ((array) $valid_schemes as $scheme) {
        $schemes[] = rtrim($scheme) . '://';
      }
      $schemes = array_map([$this->getDatabase(), 'escapeLike'], $schemes);
      // Add the conditions: uri LIKE 'public://%' OR uri LIKE 'private://%'.
      $conditions = new Condition('OR');
      foreach ($schemes as $scheme) {
        $conditions->condition('f.uri', $scheme . '%', 'LIKE');
      }
      $query->condition($conditions);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function prepareRow(Row $row) {
    $fid = $row->getSourceProperty('fid');
    $type = $row->getSourceProperty('type');

    // Get Field API field values.
    foreach ($this->getFields('file', $type) as $field_name => $field) {
      $row->setSourceProperty($field_name, $this->getFieldValues('file', $field_name, $fid));
    }

    return parent::prepareRow($row);
  }

}

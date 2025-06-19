<?php

namespace Drupal\center_closures_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class closureImporter.
 *
 * @package Drupal\center_closures_import\Form
 */
class ImportForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ImporterForm class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'center_closures_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = [
      '#attributes' => array('enctype' => 'multipart/form-data'),
    ];

    $validators = [
      'file_validate_extensions' => ['csv'],
    ];

    $form['csv'] = [
      '#type' => 'managed_file',
      '#name' => 'csv',
      '#title' => t('File'),
      '#size' => 20,
      '#description' => t('CSV format only'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://',
      '#required' => TRUE,
      '#autoupload' => TRUE,
      '#weight' => 26,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get field values.
    $csv = current($form_state->getValue('csv'));
    $type = $form_state->getValue('import_type');

    // Load file.
    $csv_file = $this->entityTypeManager->getStorage('file')->load($csv);

    // Read file.
    $file = fopen($csv_file->getFileUri(), "r");
    $file = fopen($csv_file->getFileUri(), "r");
    while (!feof($file)) {
      $csvData[] = fgetcsv($file);
    }
    fclose($file);
    unset($csvData[0]);
    $total = count($csvData);
    $node_to_be_deleted = [];
    if (isset($csvData) && !empty($csvData)) {
      //Delete existing test_center_closure nodes
      $node_to_be_deleted = \Drupal::entityQuery('node')
        ->condition('type','test_center_closure')
        ->execute();
    }
    // Set batch.
    $batch = [
      'title' => t('Importing...'),
      'operations' => [],
      'init_message' => t('Import process is starting.'),
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.'),
    ];

    $deleteCount = 0;
    if(isset($node_to_be_deleted) && !empty($node_to_be_deleted)) {
      $deleteCount = count($node_to_be_deleted);
      //$operations[] = array('delete_test_closure_existing', array($node_to_be_deleted));
      foreach($node_to_be_deleted as $nids) {
        $batch['operations'][] = [
          ['\Drupal\center_closures_import\Form\ImportForm', 'deleteTestClosure'],
          [$nids],
        ];
      }
    }
    foreach($csvData as $csvData) {
      $batch['operations'][] = [
        ['\Drupal\center_closures_import\Form\ImportForm', 'importData'],
        [$csvData],
      ];
    }
    batch_set($batch);
    // Set success message.
    \Drupal::messenger()->addMessage('Imported ' . $total . ' data and deleted previous ' . $deleteCount . ' data!');
  }

  /**
   * To delete existing closuredata.
   *
   * @param: $item item.
   *   item array.
   *
   * @param: $context context.
   *    context array.
   *
   * To create closure data.
   */
  public static function deleteTestClosure($item, &$context) {

    //Get Current user to pass that UID for content creation user.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($item);
    // Check if node exists with the given nid.
    if ($node) {
      $node->delete();
    }
    $context['results'][] = $item;
    $context['message'] = t('Deleted NID - @title', array('@title' => $item));
  }
  /**
   * To create closuredata.
   *
   * @param: $item item.
   *   item array.
   *
   * @param: $context context.
   *    context array.
   *
   * To create closure data.
   */
  public static function importData($item, &$context) {

    //Get Current user to pass that UID for content creation user.
    $user_id = \Drupal::currentUser()->id();
    $start_date = date('Y-m-d\TH:i:s', strtotime(str_replace('/', '-', $item[0] . '00:00:00')));
    $end_date = date('Y-m-d\TH:i:s', strtotime(str_replace('/', '-', $item[1] . '23:59:59')));
    $start_date = strtotime($start_date);
    $end_date = strtotime($end_date);

    //Create Closure publish Date Entity
    $schedule_publish_entity = \Drupal::entityTypeManager()->getStorage('scheduled_update');
    $publish_entity = $schedule_publish_entity->create([
      'type' => 'schedule_publish',
      'user_id' => $user_id,
      'update_timestamp' => $start_date,
      'status' => 1,
    ]);
    $publish_entity->save();
    $publish_date_id = $publish_entity->id();

    //Create Closure unpublish Date Entity
    $schedule_unpublish_entity = \Drupal::entityTypeManager()->getStorage('scheduled_update');
    $unpublish_entity = $schedule_unpublish_entity->create([
      'type' => 'schedule_unpublish',
      'user_id' => $user_id,
      'update_timestamp' => $end_date,
      'status' => 1,
    ]);
    $unpublish_entity->save();
    $unpublish_date_id = $unpublish_entity->id();

    //Create an array to combine address field values.
    $addressArray = [
      'locality' => $item['3'],
      'administrative_area' => $item['4'],
      'country_code' => $item['5'],
      'address_line1' => $item['6'],
      'postal_code' => $item['7'],
    ];

    // Check for existing test_center node.
    $center_id = $item['2'];
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['field_id' => $center_id]);
    if ($node = reset($nodes)) {
      // Found $node that matches the test center id.
      $test_center_NID = $node->id();
    } else {
      //It is required to create the test_center content type
      //As NID for this node would be referenced to the test_center_closure.
      $test_center_node = Node::create(['type' => 'test_center']);
      $test_center_node->set('field_id', $item['2']);
      $test_center_node->set('field_test_center_location', $addressArray);
      $test_center_node->status = 1;
      $test_center_node->uid = $user_id;
      $test_center_node->save();
      //Get the NID for Text Center Content.
      $test_center_NID = $test_center_node->id();
    }

    //Create node for test_center_closre content type.
    //Add test_center NID asa reference on this.
    $node = Node::create(['type' => 'test_center_closure']);
    $node->set('schedule_publish', $publish_date_id);
    $node->set('schedule_unpublish', $unpublish_date_id);
    $node->set('field_test_center', $test_center_NID);
    $node->status = 1;
    $node->uid = $user_id;
    $node->save();

    // Add to closures_to_be_published & closures_to_be_unpublished states for cron.
    $closures_to_be_unpublished = \Drupal::state()->get('closures_to_be_unpublished');
    if (isset($end_date) && $end_date > 0) {
      $closures_to_be_unpublished[$node->id()] = $end_date;
      \Drupal::state()->set('closures_to_be_unpublished', $closures_to_be_unpublished);
    }

    $context['results'][] = $item['2'];
    $context['message'] = t('Created @title', array('@title' => $item['2']));
  }
}

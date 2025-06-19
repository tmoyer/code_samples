<?php

namespace Drupal\example_chat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\example_chat\RocketChatService;

/**
 * Defines a form that configures chat settings.
 */
class RocketChatSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'example_chat_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'example_chat.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('example_chat.settings');

    $form['rocketchat_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rocket.Chat URL'),
      '#description' => $this->t('The base URL of the Rocketchat instance.'),
      '#default_value' => $config->get('rocketchat_url'),
    ];

    $form['rocketchat_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rocket.Chat Admin Username'),
      '#default_value' => $config->get('rocketchat_username'),
    ];

    $form['rocketchat_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Rocket.Chat Admin Password'),
      '#default_value' => $config->get('rocketchat_password'),
    ];

    $form['rocketchat_cleanup'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cleanup Rocket.Chat groups'),
      '#suffix' => $this->t('<div style="margin-left: 1rem; margin-bottom: 1rem;">CAUTION: Will remove any groups not associated with a conference on this site. ONLY RUN ON PROD.</div>'),
    ];

    $form['rocketchat_bulk_create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check for missing Rocket.Chat groups'),
      '#suffix' => $this->t('<div style="margin-left: 1rem; margin-bottom: 1rem;">CAUTION: Will add a chat group to any conferences not yet associated with group. ONLY RUN ON PROD.</div>'),
    ];

    $form['rocketchat_bulk_join'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add missing Rocket.Chat members to groups'),
      '#suffix' => $this->t('<div style="margin-left: 1rem; margin-bottom: 1rem;">Make sure all conference registrants are added as members to corresponding RocketChat channels.</div>'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $op = (string) $form_state->getValue('op');

    /** @var RocketChatService $chat_service */
    $chat_service = \Drupal::service('example_chat.chat');
    $config = $this->config('example_chat.settings');
    $passed_pw = $form_state->getValue('rocketchat_password');
    $pw = (!empty($passed_pw)) ? $passed_pw : $config->get('rocketchat_password'); 

    $this->config('example_chat.settings')
      ->set('rocketchat_url', $form_state->getValue('rocketchat_url'))
      ->set('rocketchat_username', $form_state->getValue('rocketchat_username'))
      ->set('rocketchat_password', $pw)
      ->save();

    // Attempt to authenticate.
    if ($chat_service->storeAdminAuthToken()) {
      \Drupal::messenger()->addMessage('Rocket.Chat successfully authenticated.');
    }
    else {
      \Drupal::messenger()->addMessage('Could not authenticate with Rocket.Chat.');
    }

    // Handle cleanup.
    if ($op == $this->t('Cleanup Rocket.Chat groups')) {
      \Drupal::logger('example_chat')->notice('Rocket.Chat group cleanup initiated.');
      $chat_service->cleanup();
    }

    // Handle bulk create.
    if ($op == $this->t('Check for missing Rocket.Chat groups')) {
      \Drupal::logger('example_chat')->notice('Rocket.Chat bulk creation of group chats (for conferences that do not have one) initiated.');
      $chat_service->bulkCreate();
    }

    // Handle bulk join members.
    if ($op == $this->t('Add missing Rocket.Chat members to groups')) {
      \Drupal::logger('example_chat')->notice('Rocket.Chat bulk adding of members to channels initiated.');
      $chat_service->bulkJoinMembers();
    }

    // Handle bulk conference save.
    if ($op == $this->t('Add all attendees to conferences (resave conferences)')) {
      \Drupal::logger('example_chat')->notice('Bulk resaving of conference entities to ensure members are added');

      // Re-save conferences as a batch operation to avoid timeout.
      $batch = [
        'title' => $this->t('Re-saving conferences...'),
        'operations' => [],
        'finished' => '\Drupal\example_chat\Form\RocketChatSettingsForm::batchResaveFinished',
      ];

      $entities = $chat_service->loadConferences();

      foreach ($entities as $entity) {
        $operations = [[ '\Drupal\example_chat\Form\RocketChatSettingsForm::batchResave', [$entity, $max = count($entities)] ]];
      }
      if (!empty($operations)) {
        $batch['operations'] = $operations;
        $batch['max'] = count($operations);
        batch_set($batch);
      }
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Resaves conference entities. Batch operation handler.
   *
   * @param string $entity
   *   Entity object to be saved.
   * @param array $context
   *   Context batch array.
   */
  public static function batchResave($entity, $max, &$context) {
    $message = 'Resaving all conference nodes ...';
    $results = [];

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $max;
    }

    // Track progress.
    $context['sandbox']['progress'] += $entity->save();

    $context['message'] = t('Resaving @count of @total conferences…', ['@count' => $context['sandbox']['progress'], '@total' => $context['sandbox']['max']]);
    $context['results'][] = $entity;

    // Track finished.
    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Resaves conference entities. Batch completion handler.
   *
   * @param bool $success
   *   Boolean if operations were successful.
   * @param array $results
   *   Results of batch operations.
   * @param array $operations
   *   List of batch operations run.
   */
  public static function batchResaveFinished($success = FALSE, array $results = [], array $operations = []) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One conference resaved.', '@count conferences resaved.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::logger('example_chat')->notice('Batch finished: ' . $message);
    \Drupal::messenger()->addMessage($message);
  }
}

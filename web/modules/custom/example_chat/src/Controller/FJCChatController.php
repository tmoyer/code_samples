<?php
namespace Drupal\example_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\example_chat\RocketChatService;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

define('ROCKETCHAT_SUBDOMAIN_DEFAULT', 'example_chat_dev');

/**
 * Provides route responses for the Example module.
 */
class RocketChatController extends ControllerBase {

  /**
   * Returns a conference chat page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function conferenceChatPage(NodeInterface $node = NULL) {
    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
        '#attached' => [
          'library' => ['example_chat/example_chat.iframe-login']
        ]
    ];

    $token = '';
    /** @var RocketChatService $chat_service */
    $chat_service = \Drupal::service('example_chat.chat');
    $auth_data = $chat_service->getAuthToken();
    $token = (isset($auth_data['authToken']) && !empty($auth_data['authToken'])) ? $auth_data['authToken'] : '';

    if ($node && $node->hasField('field_rocketchat_channel') && $channel = $node->field_rocketchat_channel->value) {
      $rocketchat_url = \Drupal::config('example_chat.settings')->get('rocketchat_url');

      $build[] = [
        '#type' => 'markup',
        '#markup' => Markup::create("<iframe id='rc-embed' class='rc' src='{$rocketchat_url}/group/{$channel}?resumeToken={$token}'></iframe>"),
      ];
    }

    return $build;
  }

  /**
   * Returns the iframe login page, as requested by Rocket.Chat.
   * NOT USED but keeping in the codebase for now.
   */
  public function iframeLogin($conference) {
    $build = [];

    if ($user = User::load(\Drupal::currentUser()->id())) {
      /** @var RocketChatService $chat_service */
      $chat_service = \Drupal::service('example_chat.chat');

      // If we are logged in as an admin, we can fetch the Rocket.Chat admin creds.
      // TODO: This may have averse affects if there are administrator speakers.
      $token = '';
      $rc_user_id = '';
      if ($user->hasRole('administrator')) {
        $chat_service->storeAdminAuthToken();
        $token = \Drupal::state()->get('example_chat.authToken');
      }
      else {
        if ($auth_data = $chat_service->getAuthToken($user)) {
          $rc_user_id = $auth_data['userId'];
          $token = $auth_data['authToken'];
        }
      }

      // Ensure this user is added to the conference group.
      if (!empty($rc_user_id) && $conference->field_rocketchat_channel_id->value) {
        /** TODO: check rocket.chat to see if user is already a member before trying to join again. **/
        $chat_service->joinConference($rc_user_id, $conference->field_rocketchat_channel_id->value);
      }

      $build[] = [
        '#cache' => [
          'max-age' => 0,
        ],
        'login-data' => [
          '#type' => 'markup',
          '#markup' => '<div id="rc-login-data" style="display: none;" data-token="' . $token . '" data-rc-url="' . \Drupal::config('example_chat.settings')->get('rocketchat_url', '') . '" data-iframe-id="rc-embed"></div>',
        ],
        '#attached' => [
          'library' => ['example_chat/example_chat.iframe-login']
        ]
      ];
    }

    return $build;
  }

  /**
   * Custom access check for chat.
   */
  public function chatAccess(AccountInterface $account, NodeInterface $node) {
    return conference_node_access($node, 'view', $account);
  }
}

<?php

namespace Drupal\example_chat;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\openid_connect\OpenIDConnectAuthmap;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Drupal\Core\Config\ConfigFactory;
use Microsoft\Graph\Model;
use Microsoft\Graph\Graph;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class EqrxiAPIService.
 */
class RocketChatService {

  use MessengerTrait;

  /**
   * Configuration Factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Gets chat settings from Drupal config.
   */
  private function getChatSettings() {
    return $this->configFactory->get('example_chat.settings');
  }

  /**
   * Stores authentication tokens for the admin user.
   */
  public function storeAdminAuthToken() {
    $chat_settings = $this->getChatSettings();

    // Skip request for new adminAuthToken if < 2 minutes since last.
    $request_time = \Drupal::time()
      ->getRequestTime();
    $last_stored = \Drupal::state()->get('example_chat.authTimeStored');
    if (!empty($last_stored) && $request_time - $last_stored < 120) {
      return true;
    }

    $guzzle = new Client();
    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/login', [
        'body' => \GuzzleHttp\json_encode([
          'username' => $chat_settings->get('rocketchat_username'),
          'password' => $chat_settings->get('rocketchat_password'),
        ])
      ])->getBody()->getContents());

      if ($result->status == "success") {
        \Drupal::state()->set('example_chat.userId', $result->data->userId);
        \Drupal::state()->set('example_chat.authToken', $result->data->authToken);
        \Drupal::state()->set('example_chat.authTimeStored', $request_time);
        return true;
      }
      else {
        return false;
      }
    } catch (\Exception $e) {
      \Drupal::logger('example_chat')->notice('admin auth token exception: ' . $e->getMessage());
      \Drupal::messenger()->addWarning("We could not connect you to chat, please contact a system administrator 1: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Gets an auth token and userId for a user.
   *
   * @param UserInterface|null $user
   * @return array|false
   */
  public function getAuthToken(UserInterface $user = NULL, $method = 'token') {
    if (!$user) {
      $user = User::load(\Drupal::currentUser()->id());
    }

    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    /** TODO: add a check against rocket.chat to see if user exists instead of just relying on whether the user fields for rocketchat username and pw exist. **/

    if (!$user->field_rocketchat_username->value) {
      $new_password = '';
      // Try to login by guessing creds.
      $auth_data = $this->attemptLogin($user);
      if (!empty($auth_data)) {
        return $auth_data;
      }

      // If that fails, reset password.
      $new_password = $this->resetUserPassword($user);
      if (!empty($new_password)) {
        $auth_data = $this->attemptLogin($user, $new_password);
        return $auth_data;
      }
      try {
        // Create RocketChat user.
        $user_created = $this->createChatUser($user);
      } catch (\Exception $e) {
        \Drupal::messenger()->addWarning("We could not connect you to chat, please contact a system administrator 2: " . $e->getMessage());
        return false;
      }
    }
    else {
      $user_created = true;
    }

    if (empty($user_created)) {
      \Drupal::messenger()->addWarning("We could not connect you to chat, please contact a system administrator.");
      return false;
    }

    try {
      $u_authdata = $user->field_rocketchat_auth_data->value;
      $auth_data = (!empty($u_authdata)) ? @unserialize($u_authdata) : '';
      if ($u_authdata === 'b:0;' || $auth_data === false) {
        $auth_data = '';
      }
      if (is_array($auth_data)
        && !empty($auth_data)
        && isset($auth_data['authToken'])
        && $auth_data['authToken'] != ''
        && !empty($auth_data['authToken'])
        && $method == 'token'
        ) {
        $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/login', [
          'body' => \GuzzleHttp\json_encode([
            'resume' => $auth_data['authToken'],
          ])
        ])->getBody()->getContents());
      } else {
        $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/login', [
          'body' => \GuzzleHttp\json_encode([
            'username' => $user->field_rocketchat_username->value,
            'password' => $user->field_rocketchat_password->value,
          ])
        ])->getBody()->getContents());
      }

      if ($result->status == "success") {
        $auth_data = [
          'userId' => $result->data->userId,
          'authToken' => $result->data->authToken,
        ];

        $user->set('field_rocketchat_auth_data', serialize($auth_data));
        $user->save();

        return $auth_data;
      }
      else {
        \Drupal::logger('example_chat')->notice('rocketchat login failed for RocketChat username @user, Drupal uid @uid', [
          '@user' => $user->field_rocketchat_username->value,
          '@uid' => $user->get('uid')->value
        ]);
        $user->set('field_rocketchat_auth_data', '');
        $user->save();
        return false;
      }
    } catch (\Exception $e) {
      $url = Url::fromUri('internal:/user/' . $user->get('uid')->value . '/edit');
      $link = $url->toString();
      \Drupal::messenger()->addMessage("We could not connect you to chat, please contact a system administrator. If you have manually reset your chat password you will need to update that by editing your account: $link");
      \Drupal::logger('example_chat')->notice('rocketchat login try failed for RocketChat username @user, Drupal uid @uid, rchat pw @rpwd: @error', [
        '@user' => $user->field_rocketchat_username->value,
        '@uid' => $user->get('uid')->value,
        '@rpwd' => $user->field_rocketchat_password->value,
        '@error' => $e->getMessage()
      ]);

      if ($method == 'token') {
        $this->getAuthToken($user, $method = 'creds');
      } else {
        \Drupal::logger('example_chat')->notice('creds login method failed');
        /* TODO: Are we here because they already logged in as a different user?
           Maybe try logging out and back in as the current Drupal user. */
        // Try resetting password as current password may be wrong.
        $new_password = $this->resetUserPassword($user);
        if (!empty($new_password)) {
          $auth_data = $this->attemptLogin($user, $new_password);
          return $auth_data;
        }
        return false;
      }
    }
  }

  /**
   * Utility function for generating headers for authenticated Rocket.Chat calls.
   */
  public function chatHeaders() {
    $this->storeAdminAuthToken();

    return [
      'X-Auth-Token' => \Drupal::state()->get('example_chat.authToken'),
      'X-User-Id' => \Drupal::state()->get('example_chat.userId'),
      'Content-type' => 'application/json',
    ];
  }

  /**
   * Creates a Rocket.Chat user for a given Drupal user.
   *
   * @param UserInterface $user
   * @return false|mixed
   */
  public function createChatUser(UserInterface $user) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // Skip if we already have a username for this user.
    if ($user->field_rocketchat_username->value) {
      return false;
    }

    $username = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $user->getAccountName())));
    $pw_stub = $chat_settings->get('rocketchat_pw_stub');
    $password = $user->getPassword() ?? crypt(str_replace('-', '', $username), $pw_stub);
    try {

      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/users.create', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'email' => $user->getEmail() ?? $username . '@example.com',
          'name' => $user->field_mt_full_name->value ?? $user->getAccountName(),
          'password' => $password,
          'username' => $username,
        ])
      ])->getBody()->getContents());

      $user->set('field_rocketchat_username', $username);
      $user->set('field_rocketchat_password', $password);
      $user->save();
      return true;
    }
    catch (\Exception $e) {
      // Check response for whether username already exists & try to reset password.
      $check_msg = $username . ' is already in use';
      if (strstr($e->getMessage(), $check_msg)) {
        $new_password = $this->resetUserPassword($user);
        if (!empty($new_password)) {
          $auth_data = $this->attemptLogin($user, $new_password);
          if ($auth_data) {
            return true;
          }
        }
      }
      \Drupal::messenger()->addWarning("We could not connect you to chat(1): " . $e->getMessage());
      return false;
    }
  }

  /**
   * Checks for an existing RocketChat userId.
   *
   * @param UserInterface $user
   * @return null|$userId
   */
  public function checkForExistingUser(UserInterface $user, $username) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // Request userId.
    // For some reason passing query in request body for this
    // request fails, so passing it directly in the passed url.
    try {
      $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/users.info?username=' . $username, [
        'headers' => $this->chatHeaders(),
      ])->getBody()->getContents());
      if (isset($result->success) && $result->success == FALSE) {
        return;
      }
      $userId = $result->user->_id ?? '';
      return $userId;
    }
    catch (\Exception $e) {
      $user_not_found = 'User not found';
      if (strstr($e->getMessage(), $user_not_found)) {
        $user_email = $user->getEmail();
        if ($userId = $this->checkUsersList($user_email)) {
          if ($this->updateUsername($userId, $username)) {
            return $userId;
          }
        }
        return;
      }
    }
  }

  /**
   * Get list of RocketChat group members.
   *
   * @param $roomId
   * @return null|array $members
   */
  public function getGroupMembers($roomId) {
    if (empty($roomId) or $roomId == '') {
      return FALSE;
    }
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // Request group members.
    // For some reason passing query in request body for this
    // request fails, so passing it directly in the passed url.
    try {
      $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/groups.members?roomId=' . $roomId, [
        'headers' => $this->chatHeaders(),
      ])->getBody()->getContents());

      if (isset($result->success) && $result->success == TRUE) {
        return $result->members;
      }
      return FALSE;
    }
    catch (\Exception $e) {
      \Drupal::logger('example_chat')->error('Unable to get list of group members. ' . $e->getMessage());
      \Drupal::logger('example_chat')->error($e->getMessage());
    }
  }

  /**
   * Get full list of users from RocketChat.
   *
   * @return false|array of users.
   */
  public function fullUsersList() {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/users.list', [
      'headers' => $this->chatHeaders(),
    ])->getBody()->getContents());
    if (isset($result->success) && $result->success == TRUE) {
      return $result->users;
    }
    return FALSE;
  }

  /**
   * Check RocketChat users list for a specific email address.
   *
   * @param $user_email
   * @return false|$user_id (RocketChat user id)
   */
  public function checkUsersList($user_email) {
    $users = $this->fullUsersList();
    foreach ($users as $user) {
      if ($user->emails[0]->address == $user_email) {
        return $user->_id;
      }
    }
    return FALSE;
  }

  /**
   * Updates RocketChat username for specific user id.
   *
   * @param $userId (RocketChat user id)
   * @param $username
   * @return true|false
   */
  private function updateUsername($userId, $username) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();
      \Drupal::logger('example_chat')->notice('Will try to update username for ' . $username);
    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/users.update', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'userId' => $userId,
          'data' => [
            'username' => $username,
          ],
        ])
      ])->getBody()->getContents());

      if (isset($result->success) && $result->success == 1) {
        return TRUE;
      }

      if (property_exists($result, 'error')) {
        throw new \Exception($result->error ?? "No error given.");
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('example_chat')->notice('Unable to update username ' . $username . ': ' . $e->getMessage());
      \Drupal::messenger()->addWarning('Unable to update username ' . $username . ': ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Attempts RocketChat login with guessed username & password.
   *
   * @param UserInterface $user
   * @param $password (guessed RocketChat password)
   * @return false|$auth_data
   */
  public function attemptLogin(UserInterface $user, $password = NULL) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    $username = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $user->getAccountName())));
    if (empty($password)) {
      $pw_stub = $chat_settings->get('rocketchat_pw_stub');
      $password = $user->getPassword() ?? crypt(str_replace('-', '', $username), $pw_stub);
    }
    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/login', [
        'body' => \GuzzleHttp\json_encode([
          'username' => $username,
          'password' => $password,
        ])
      ])->getBody()->getContents());

      if (isset($result->success) && $result->success == 1) {
        $auth_data = [
          'userId' => $result->data->userId,
          'authToken' => $result->data->authToken,
        ];

        $user->set('field_rocketchat_username', $username);
        $user->set('field_rocketchat_password', $password);
        $user->set('field_rocketchat_auth_data', serialize($auth_data));
        $user->save();
        return $auth_data;
      }

      if (property_exists($result, 'error')) {
        throw new \Exception($result->error ?? "No error given.");
      }

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("We could not connect you to chat(2): " . $e->getMessage());
      return false;
    }
    return false;
  }

  /**
   * Resets user's RocketChat password.
   *
   * @param UserInterface $user
   * @return false|mixed
   */
  public function resetUserPassword(UserInterface $user) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    $username = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $user->getAccountName())));
    $pw_stub = $chat_settings->get('rocketchat_pw_stub');
    $password = $user->getPassword() ?? crypt(str_replace('-', '', $username), $pw_stub);

    $userId = $this->checkForExistingUser($user, $username);

    // User exists, so let's reset the password.
    if (!empty($userId)) {
      try {
        $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/users.update', [
          'headers' => $this->chatHeaders(),
          'body' => \GuzzleHttp\json_encode([
            'userId' => $userId,
            'data' => [
              'password' => $password,
            ],
          ])
        ])->getBody()->getContents());

        if ($result->success == 1) {
          $user->set('field_rocketchat_password', $password);
          $user->set('field_rocketchat_username', $username);
          $user->save();
          $this->getAuthToken($user, $method = 'creds');
          return $password;
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('example_chat')->notice('could not connect user ' . $user->getAccountName() . ' to chat(3)');
        \Drupal::messenger()->addWarning("We could not connect you to chat(3)): " . $e->getMessage());
        return false;
      }

    }
  }

  /**
   * Creates a Rocket.Chat group for a conference.
   *
   * @param NodeInterface $conference
   * @return false|mixed
   */
  public function createChatGroup(NodeInterface $conference, $rename = FALSE) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // Skip if we already have a channel for this conference.
    if ($conference->field_rocketchat_channel->value) {
      $channel_id = $conference->field_rocketchat_channel_id->value;

      // If channel exists, don't try to create one.
      if ($this->chatGroupExists($channel_id)) {
        return false;
      }
    }

    $channel = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $conference->label()), '-'));
    if ($rename) {
      $channel = substr($channel, 0, 60) . '-' . $conference->id();
    }
    $exists = $this->nameExists($channel);
    if ($exists) {
      // Make sure the conference chat room is unique.
      $channel = substr($channel, 0, 46) . '-' . $conference->id();
    }

    $members = [];
    foreach ($conference->get('field_registrants')->getValue() as $data) {
      if ($user = user_load_by_mail($data['value'])) {
        $members[] = $user->getAccountName();
      }
    }

    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/groups.create', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'name' => $channel,
          'members' => $members,
        ])
      ])->getBody()->getContents());

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      return [
        'channel' => $channel,
        'channel_id' => $result->group->_id,
      ];
    }
    catch (\Exception $e) {
      // Check response for whether group name already exists & try again with new name.
      $check_msg = "A channel with name '$channel' exists";
      if (strstr($e->getMessage(), $check_msg)) {
        $channel_details = $this->createChatGroup($conference, $rename = TRUE);
        if (!empty($channel_details)) {
          return $channel_details;
        }
      }
      $url = Url::fromUri('route:example_chat.chat_settings');
      $admin_link = Link::fromTextAndUrl(t('Rocket Chat Settings'), $url);
      \Drupal::messenger()->addWarning("This conference was created, but a Rocket.Chat group could not be created. Most likely cause is that a group already exists with the same name. Try renaming the conference and then check the 'Check for missing Rocket.Chat groups' box in " . $admin_link->toString()->getGeneratedLink() . ": " . $e->getMessage());
      \Drupal::logger('example_chat')->error('A Rocket.Chat group could not be created for ' . $conference->getTitle() . ': ' . $e->getMessage());
      \Drupal::logger('example_chat')->notice('response: ' . $e->getMessage());;
      return false;
    }
  }

  /**
   * Deletes the Rocket.Chat group for a conference.
   *
   * @param NodeInterface $conference
   * @return false|true
   */
  public function deleteChatGroup(NodeInterface $conference) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // Skip if we don't have a channel for this confence.
    if (!$conference->field_rocketchat_channel->value) {
      return false;
    }

    $channel_id = $conference->field_rocketchat_channel_id->value;
    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/groups.delete', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'roomId' => $channel_id,
        ])
      ])->getBody()->getContents());

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      return true;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("This conference was deleted, but the associated Rocket.Chat group could not be deleted: " . $e->getMessage());
      \Drupal::logger('example_chat')->error($conference->getTitle() . ' was deleted, but the associated Rocket.Chat group could not be deleted: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Retrieves info for a chat group.
   */
  public function chatGroupExists($channel, $type = 'id') {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    try {
      if ($type == 'id') {
        $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/groups.info', [
          'headers' => $this->chatHeaders(),
          'body' => \GuzzleHttp\json_encode([
            'roomId' => $channel,
          ])
        ])->getBody()->getContents());
      } else {
        $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/groups.info', [
          'headers' => $this->chatHeaders(),
          'body' => \GuzzleHttp\json_encode([
            'roomName' => "$channel",
          ])
        ])->getBody()->getContents());
      }

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      if ($result->success === 'true') {
        return TRUE;
      }
      return FALSE;

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("Rocket.Chat was not able to get info on that group: " . $e->getMessage());
      // TODO: DB Log?
      return false;
    }
  }

  /**
   * Retrieves list of all chat groups.
   */
  public function getAllChatGroups() {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    try {
      $result = \GuzzleHttp\json_decode($guzzle->get($chat_settings->get('rocketchat_url') . '/api/v1/groups.listAll', [
        'headers' => $this->chatHeaders()
      ])->getBody()->getContents());

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      $group_ids = [];
      foreach ($result->groups as $group) {
        $group_ids[] = ['id' => $group->_id, 'name' => $group->name];
      }

      return $group_ids;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("Rocket.Chat was not able to get a list of all groups: " . $e->getMessage());
      \Drupal::logger('example_chat')->error("Rocket.Chat was not able to get a list of all groups: " . $e->getMessage());
      // TODO: DB Log?
      return false;
    }
  }

  /**
   * Add a user to a conference chat room.
   *
   * @param NodeInterface $conference
   * @return false|mixed
   */
  public function joinConference($rc_user_id, $rc_channel_id) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/groups.invite', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'roomId' => $rc_channel_id,
          'userId' => $rc_user_id,
        ])
      ])->getBody()->getContents());

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      return true;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("We could not add you to this conference chat room: " . $e->getMessage());
      // TODO: DB Log?
      \Drupal::logger('example_chat')->error('User (' . $rc_user_id . ') could not be added to the conference chat room for ' . $rc_channel_id . ': ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Remove a user from a conference chat room.
   *
   * @param NodeInterface $conference
   * @return false|mixed
   */
  public function leaveConference($rc_user_id, $rc_channel_id) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    try {
      $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/groups.kick', [
        'headers' => $this->chatHeaders(),
        'body' => \GuzzleHttp\json_encode([
          'roomName' => $rc_channel_id,
          'userId' => $rc_user_id,
        ])
      ])->getBody()->getContents());

      if (property_exists($result, 'error')) {
        \Drupal::messenger()->addMessage(print_r($result, true));
        throw new \Exception($result->error ?? "No error given.");
      }

      return true;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addWarning("We could not remove this user from this conference chat room: " . $e->getMessage());
      // TODO: DB Log?
      \Drupal::logger('example_chat')->error('User (' . $rc_user_id . ') could not be removed from the conference chat room for ' . $rc_channel_id . ': ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Logout from chat.
   */
  public function logout(UserInterface $user) {
    $chat_settings = $this->getChatSettings();
    $guzzle = new Client();

    // First, test if the user is logged in.
    $authToken = !empty($user->field_rocketchat_auth_data->value) ?? unserialize($user->field_rocketchat_auth_data->value);
    if ($authToken) {
      try {
        if ($user->hasRole('administrator')) {
          $chat_headers = [
            'X-Auth-Token' => \Drupal::state()->get('example_chat.authToken'),
            'X-User-Id' => \Drupal::state()->get('example_chat.userId'),
            'Content-type' => 'application/json',
          ];
        }
        else {
          $authToken = unserialize($user->field_rocketchat_auth_data->value);
          $chat_headers = [
            'X-Auth-Token' => $authToken['authToken'],
            'X-User-Id' => $authToken['userId'],
            'Content-type' => 'application/json',
          ];
        }

        $result = \GuzzleHttp\json_decode($guzzle->post($chat_settings->get('rocketchat_url') . '/api/v1/logout', [
          'headers' => $chat_headers,
        ])->getBody()->getContents());

        if (property_exists($result, 'error')) {
          \Drupal::messenger()->addMessage(print_r($result, true));
          throw new \Exception($result->error ?? "No error given.");
        }

        return true;
      }
      catch (\Exception $e) {
        \Drupal::logger('example_chat')->alert("We could not log out of Rocket.Chat: " . $e->getMessage());
        return false;
      }
    } // End if we have an $authToken.
  }

  /**
   * Delete Rocket.Chat channels that don't have a conference.
   */
  public function cleanup() {
    $groups = $this->getAllChatGroups();
    foreach ($groups as $group) {
      $conference = $this->loadEntityByChannel($group['id']);
      if (!$conference) {
        \Drupal::logger('example_chat')->notice('Rocket.Chat group chat for id ' . $group['id'] . ' has been deleted as there is no conference with that chat id');
        $this->deleteChatGroup($conference);
      }
    }
  }

  /**
   * Bulk join existing conference registrants as Chat members.
   */
  public function bulkJoinMembers() {
    $batch = [
      'title' => t('Batch joining registrants to conference chat rooms...'),
      'operations' => [],
      'finished' => '\Drupal\example_chat\RocketChatService::batchJoinFinished',
    ];

    $conferences = $this->loadConferences();
    $add_members = [];

    foreach ($conferences as $conference) {
      $channel_id = $conference->field_rocketchat_channel_id->value;
      $registrants = $conference->field_registrants->getValue();
      $members = $this->getGroupMembers($channel_id);
      if (!$members) {
        continue;
      }
      if (is_array($registrants) && !empty($channel_id)) {
        foreach ($registrants as $value) {
          $registrant = $value['value'];
          $username = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $registrant)));
          if (is_array($members) && $this->registrantInList($username, $members)) {
            continue 1;
          }
          $target_user = user_load_by_mail($registrant);
          if ($target_user && $auth_data = $this->getAuthToken($target_user)) {
            $rc_user_id = $auth_data['userId'];
            $add_members[] = ['rc_user_id' => $rc_user_id, 'channel_id' => $channel_id];
          }
        }
      }
    }

    // Add only add non-existing members to one batch job.
    $operations = [[ '\Drupal\example_chat\RocketChatService::batchJoin', [$add_members]]];

    if (!empty($operations)) {
      $batch['operations'] = $operations;
      $batch['max'] = count($operations);
      batch_set($batch);
    }
  }

  /**
   * Adds missing members to Rocket.Chat groups. Batch operation handler.
   *
   * @param array $add_members
   *   Array of members and corresponding room_id's.
   * @param array $context
   *   Context batch array.
   */
  public static function batchJoin($add_members, &$context) {
    $message = 'Batch joining registrants to conference nodes ...';
    $results = [];
    $max = count($add_members);

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_number'] = 0;
      $context['sandbox']['max'] = $max;
    }

    // Track progress.
    $chat_service = \Drupal::service('example_chat.chat');
    foreach ($add_members as $member) {
      $context['sandbox']['progress'] ++;
      $rc_user_id = $member['rc_user_id'];
      $channel_id = $member['channel_id'];
      $results[] = $chat_service->joinConference($rc_user_id, $channel_id);
    }

    $context['message'] = t('Updating @count group chat members for of @total conferences…', ['@count' => $context['sandbox']['progress'], '@total' => $context['sandbox']['max']]);
    $context['results'][] = $rc_user_id . '-' .$channel_id;

    // Track finished.
    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }

    // Context results are not being passed to batchFinish via Drush,
    // therefor we are going to show them when this is finished.
    if ($context['finished'] >= 1) {
      $t_args = [
        '@total' => $context['sandbox']['max'],
      ];
      \Drupal::messenger()->addStatus(t('Missing Rocket.Chat members import completed. (total: @total)', $t_args));
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
  public static function batchJoinFinished($success = FALSE, array $results = [], array $operations = []) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One member added to a conference.', '@count members added to conferences.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::logger('example_chat')->notice('Batch finished: ' . $message);
    \Drupal::messenger()->addMessage($message);
  }


  public function registrantInList($registrant, $members) {
    foreach ($members as $value) {
      if ($value->username == $registrant) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Create Rocket.Chat channels for conferences without one.
   */
  public function bulkCreate() {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $nodeStorage->getQuery();
    $query->condition('type', 'conference')
          ->condition('status', 1);

    $entity_ids = $query->execute();
    $entities = $nodeStorage->loadMultiple($entity_ids);

    // Create chat groups for each.
    if (!$entities) {
      \Drupal::logger('example_chat')->notice('Bulk create: there are no conferences to create chat rooms for.');
    }
    foreach ($entities as $conference) {
      $this->createChatGroup($conference);
    }
  }

  /**
   * Save all conferences to ensure attendees are added to Rocket.Chat.
   */
  public function bulkSaveConferences() {
    $entities = $this->loadConferences();
    $user_id = \Drupal::currentUser()->id();

    foreach ($entities as $entity) {
      $entity->setNewRevision(TRUE);
      $entity->revision_log = 'Created revision for node' . $entity->id();
      $entity->setRevisionCreationTime(REQUEST_TIME);
      $entity->setRevisionUserId($user_id);
      $entity->save();
    }
  }

  /**
   * Load all published conference node entities.
   */
  public function loadConferences() {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $nodeStorage->getQuery();
    $query->condition('type', 'conference')
          ->condition('status', 1);

    $entity_ids = $query->execute();
    $entities = $nodeStorage->loadMultiple($entity_ids);

    return $entities;
  }

  /**
   * Load the entity object from the channel ID field.
   *
   * @param $channel_id (Rocket.Chat channel ID)
   * @return array of NodeInterface $entity objects.
   */
  public function loadEntityByChannel($channel_id) {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $nodeStorage->getQuery();
    $query->condition('type', 'conference')
      ->condition('field_rocketchat_channel_id', $channel_id);

    $entity_ids = $query->execute();
    $entities = $nodeStorage->loadMultiple($entity_ids);
    return $entities;
  }

  /**
   * Check for channel name in all existing groups.
   *
   * Using this as API call for groupinfo on channel name fails.
   *
   * @param $channel (Rocket.Chat channel name)
   * @return true|false
   */
  public function nameExists($channel) {
    $exists = FALSE;
    $groups = $this->getAllChatGroups();
    if (is_array($groups)) {
      foreach ($groups as $group) {
        if ($group['name'] == $channel) {
          $exists = TRUE;
        }
      }
      return $exists;
    }
    return FALSE;
  }

  /**
   * Predict alias for given path as hook_entity_insert()
   * does not have the alias yet.
   *
   * @param string $path (alias type path)
   * @return string $alias
   */
  public function predictAlias($path) {
    if (!$path) {
      \Drupal::logger('example_chat')->notice('path passed to predictAlias is empty');
      return FALSE;
    }
    $database = \Drupal::database();
    $result = $database->select('path_alias', 'path_alias')
      ->fields('path_alias', ['alias'])
      ->condition('alias', '/' . $path . '%', 'LIKE')
      ->range(0, 1)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchField();

    // If no result, there is no path for passed
    // path-prepped title, so just use that.
    if (!$result) {
      return ltrim($path, '/');
    }

    $alias = ltrim($result, '/');
    $alias_parts = explode('-', $alias);
    $num = array_pop($alias_parts);
    if (!empty($num) && is_numeric($num)) {
      $num++;
    } else {
      $num = 1;
    }
    $alias = implode('-', $alias_parts) . '-' . $num;
    return $alias;
  }
}

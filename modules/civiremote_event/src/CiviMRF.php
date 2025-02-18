<?php
/*------------------------------------------------------------+
| CiviRemote - CiviCRM Remote Integration                     |
| Copyright (C) 2020 SYSTOPIA                                 |
| Author: J. Schuppe (schuppe@systopia.de)                    |
+-------------------------------------------------------------+
| This program is released as free software under the         |
| Affero GPL license. You can redistribute it and/or          |
| modify it under the terms of this license which you         |
| can read by viewing the included agpl.txt or online         |
| at www.gnu.org/licenses/agpl.html. Removal of this          |
| copyright header is strictly prohibited without             |
| written permission from the original author(s).             |
+-------------------------------------------------------------*/

namespace Drupal\civiremote_event;

use Drupal;
use Drupal\civiremote;
use Drupal\civiremote\Utils;
use Drupal\user\Entity\User;
use Exception;
use stdClass;

/**
 * CiviMRF implementations for CiviRemote events.
 *
 * @package Drupal\civiremote_event
 */
class CiviMRF extends civiremote\CiviMRF {

  /**
   * Retrieves a remote event with a given ID.
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $remote_token
   *   The remote event token.
   *
   * @return stdClass
   *   The remote event.
   *
   * @throws Exception
   *   When the event could not be retrieved.
   */
  public function getEvent($event_id, $remote_token = NULL) {
    // Since we want all exceptions be safely displayable, catch all unexpected
    // exceptions inside this method.
    try {
      $params = [
        'id' => $event_id,
        'token' => $remote_token,
      ];
      self::addRemoteContactId($params);

      $reply = &drupal_static(__FUNCTION__ . '_' . implode('_', $params));
      if (!isset($reply)) {
        $call = $this->core->createCall(
          $this->connector(),
          'RemoteEvent',
          'getsingle',
          $params,
          []
        );
        $this->core->executeCall($call);
        $reply = $call->getReply();
      }
      if (!isset($reply['id'])) {
        throw new Exception();
      }
    }
    catch (Exception $exception) {
      throw new Exception($reply['error_message'] ?? t('Could not retrieve remote event.'));
    }

    return (object) $reply;
  }

  /**
   * Retrieves the registration form definition of a remote event with a given
   * event ID for a specific profile, or with a remote event token.
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $profile
   *   The remote event profile name.
   * @param string $remote_token
   *   The remote event token.
   * @param string $context
   *   The action the form definition should be retrieved for.
   *
   * @return array
   *   The remote event registration form definition.
   *
   * @throws Exception
   *   When the registration form definition could not be retrieved.
   */
  public function getForm($event_id, $profile, $remote_token = NULL, $context = 'create') {
    $params = [
      'event_id' => $event_id,
      'profile' => $profile,
      'token' => $remote_token,
      'context' => $context,
    ];
    self::addRemoteContactId($params);

    $reply = &drupal_static(__FUNCTION__ . '_' . implode('_', $params));
    if (!isset($reply)) {
      $call = $this->core->createCall(
        $this->connector(),
        'RemoteParticipant',
        'get_form',
        $params,
        []
      );
      $this->core->executeCall($call);
      if ($call->getStatus() !== $call::STATUS_DONE) {
        throw new Exception(t('Retrieving form failed.'));
      }
      $reply = $call->getReply();
    }

    return $reply;
  }

  /**
   * Validates a remote event registration submission .
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $profile
   *   The remote event profile name.
   * @param string $remote_token
   *   The remote token.
   * @param string $context
   *   The context for which to validate the submission, one of
   *   - create (Default)
   *   - update
   *   - cancel
   * @param array $params
   *   Additional parameters to send to the API.
   *
   * @return array
   *   The errors that occurred during the remote event registration validation.
   *
   * @throws Exception
   *   When the remote event registration could not be validated.
   */
  public function validateEventRegistration($event_id, $profile, $remote_token = NULL, $context = 'create', $params = []) {
    self::addRemoteContactId($params);
    $params = array_merge($params, [
      'event_id' => $event_id,
      'profile' => $profile,
      'token' => $remote_token,
      'context' => $context,
    ]);
    self::addRemoteContactId($params);
    $call = $this->core->createCall(
      $this->connector(),
      'RemoteParticipant',
      'validate',
      $params,
      []
    );
    $this->core->executeCall($call);
    $reply = $call->getReply();
    if ($call->getStatus() !== $call::STATUS_DONE && empty($reply['values'])) {
      throw new Exception(t('The event registration validation failed.'));
    }
    return $reply;
  }

  /**
   * Submits a remote event registration submission.
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $profile
   *   The remote event profile name.
   * @param string $remote_token
   *   The remote token.
   * @param array $params
   *   Additional parameters to send to the API.
   * @param bool $show_messages
   *   Whether to show status/error messages returned by the API.
   *
   * @return array
   *   The API response of the remote event registration.
   *
   * @throws Exception
   *   When the remote event registration could not be submitted.
   */
  public function createEventRegistration($event_id, $profile, $remote_token = NULL, $params = [], $show_messages = FALSE) {
    $params = array_merge($params, [
      'event_id' => $event_id,
      'profile' => $profile,
      'token' => $remote_token
    ]);
    self::addRemoteContactId($params);
    $call = $this->core->createCall(
      $this->connector(),
      'RemoteParticipant',
      'create',
      $params,
      []
    );
    $this->core->executeCall($call);
    $reply = $call->getReply();
    if ($show_messages && !empty($reply['status_messages'])) {
      Utils::setMessages($reply['status_messages']);
    }
    if ($call->getStatus() !== $call::STATUS_DONE) {
      throw new Exception(t('The event registration failed.'));
    }
    return $reply;
  }

  /**
   * Submits a remote event registration update submission.
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $profile
   *   The remote event profile name.
   * @param string $remote_token
   *   The remote token.
   * @param array $params
   *   Additional parameters to send to the API.
   * @param bool $show_messages
   *   Whether to show status/error messages returned by the API.
   *
   * @return array
   *   The API response of the remote event registration.
   *
   * @throws Exception
   *   When the remote event registration could not be submitted.
   */
  public function updateEventRegistration($event_id, $profile, $remote_token = NULL, $params = [], $show_messages = FALSE) {
    $params = array_merge($params, [
      'event_id' => $event_id,
      'profile' => $profile,
      'token' => $remote_token
    ]);
    self::addRemoteContactId($params);
    $call = $this->core->createCall(
      $this->connector(),
      'RemoteParticipant',
      'update',
      $params,
      []
    );
    $this->core->executeCall($call);
    $reply = $call->getReply();
    if ($show_messages && !empty($reply['status_messages'])) {
      Utils::setMessages($reply['status_messages']);
    }
    if ($call->getStatus() !== $call::STATUS_DONE) {
      throw new Exception(t('The event registration update failed.'));
    }
    return $reply;
  }

  /**
   * Cancels a remote event registration.
   *
   * @param int $event_id
   *   The remote event ID.
   * @param string $remote_token
   *   The remote event token.
   * @param bool $show_messages
   *   Whether to show status/error messages returned by the API.
   *
   * @return array
   *   The API response of the remote event registration cancellation.
   *
   * @throws Exception
   *   When the remote event registration could not be cancelled.
   */
  public function cancelEventRegistration($event_id, $remote_token = NULL, $show_messages = FALSE) {
    $params = [
      'event_id' => $event_id,
      'token' => $remote_token
    ];
    self::addRemoteContactId($params);
    $call = $this->core->createCall(
      $this->connector(),
      'RemoteParticipant',
      'cancel',
      $params,
      []
    );
    $this->core->executeCall($call);
    $reply = $call->getReply();
    if ($show_messages && !empty($reply['status_messages'])) {
      Utils::setMessages($reply['status_messages']);
    }
    if ($call->getStatus() !== $call::STATUS_DONE) {
      throw new Exception(t('The event registration cancellation failed.'));
    }
    return $reply['values'];
  }

  /**
   * Retrieves information about a participant for checking them in.
   *
   * @param $remote_token
   *   The remote event checkin token.
   *
   * @param bool $show_messages
   *   Whether to show status/error messages returned by the API.
   *
   * @return array
   *   The API response of the remote event checkin verification.
   *
   * @throws Exception
   *   When the remote event checkin could not be verified.
   */
  public function getCheckinInfo($remote_token, $show_messages = FALSE) {
    // Since we want all exceptions be safely displayable, catch all unexpected
    // exceptions inside this method.
    try {
      $params = [
        'token' => $remote_token
      ];
      self::addRemoteContactId($params);
      $call = $this->core->createCall(
        $this->connector(),
        'EventCheckin',
        'verify',
        $params,
        []
      );
      $this->core->executeCall($call);
      $reply = $call->getReply();
      if ($show_messages && !empty($reply['status_messages'])) {
        Utils::setMessages($reply['status_messages']);
      }
      if ($call->getStatus() !== $call::STATUS_DONE) {
        throw new Exception();
      }
    }
    catch (Exception $exception) {
      throw new Exception($reply['error_message'] ?? t('The event checkin verification failed.'));
    }

    return ['fields' => $reply['values'], 'checkin_options' => $reply['checkin_options']];
  }

  /**
   * Checks a participant in to the event.
   *
   * @param $remote_token
   *   The remote event checkin token.
   *
   * @param int $status_id
   *   The participant status ID to use for checking the participant in.
   *
   * @param bool $show_messages
   *   Whether to show status/error messages returned by the API.
   *
   * @return bool
   *   Whether the check-in was successful.
   *
   * @throws Exception
   *   When the participant could not be checked-in.
   */
  public function checkinParticipant($remote_token, $status_id, $show_messages = FALSE) {
    $params = [
      'token' => $remote_token,
      'status_id' => $status_id,
    ];
    self::addRemoteContactId($params);
    $call = $this->core->createCall(
      $this->connector(),
      'EventCheckin',
      'confirm',
      $params,
      []
    );
    $this->core->executeCall($call);
    $reply = $call->getReply();
    if ($show_messages && !empty($reply['status_messages'])) {
      Utils::setMessages($reply['status_messages']);
    }
    if ($call->getStatus() !== $call::STATUS_DONE) {
      throw new Exception(t('The event checkin failed.'));
    }
    return TRUE;
  }

  /**
   * Adds the currently logged-in user's CiviRemote ID to the given parameters
   * array.
   *
   * @param array $params
   *   The parameters array to add the CiviRemote ID to.
   */
  public static function addRemoteContactId(&$params) {
    /* @var User $current_user */
    $current_user = User::load(Drupal::currentUser()->id());
    $params['remote_contact_id'] = $current_user->get('civiremote_id')->value;
  }

}

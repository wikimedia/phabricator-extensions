<?php
/**
 * Static utility functions for dealing with projects and policies.
 * These are used by both of our custom task policy extensions
 * (SecurityPolicyEnforcerAction and SecurityPolicyEventListener)
 * because this avoids much code duplication.
 */
final class WMFSecurityPolicy
{
  /**
   * look up a project by name
   * @param string $projectName
   * @return PhabricatorProject|null
   */
  public static function getProjectByName($projectName, $viewer=null, $needMembers=false) {
    if ($viewer === null) {
      $viewer = PhabricatorUser::getOmnipotentUser();
    }
    if (!is_array($projectName)) {
      $projectName = array($projectName);
    }
    $query = new PhabricatorProjectQuery();
    $query->setViewer($viewer)
              ->withNames($projectName)
              ->needMembers($needMembers);
    if (count($projectName) == 1) {
      return $query->executeOne();
    } else {
      return $query->execute();
    }
  }

  /**
   * get the security project for a task (based on the security_topic field)
   * @return PhabricatorProject|null the project, or null if security_topic
   *                                 is set to none
   */
  public static function getSecurityProjectForTask($task) {
    switch (WMFSecurityPolicy::getSecurityFieldValue($task)) {
      case 'sensitive':
        return WMFSecurityPolicy::getProjectByName('WMF-NDA');
      case 'security-bug':
        return WMFSecurityPolicy::getProjectByName('acl*security');
      case 'ops-access-request':
        return WMFSecurityPolicy::getProjectByName('SRE-Access-Requests');
      default:
        return false;
    }
  }

  /**
   * filter a list of transactions to remove any policy changes that would
   * make an object public.
   * @param array $transactions
   * @return array filtered transactions
   */
  public static function filter_policy_transactions(array $transactions) {
    // these policies are rejected if the task has a security setting:
    $rejected_policies = array(
      PhabricatorPolicies::POLICY_PUBLIC,
      PhabricatorPolicies::POLICY_USER,
    );

    foreach($transactions as $tkey => $t) {
      switch($t->getTransactionType()) {
        case PhabricatorTransactions::TYPE_EDIT_POLICY:
          $edit_policy = $t->getNewValue();
          if (in_array($edit_policy, $rejected_policies)) {
            unset($transactions[$tkey]);
          }
          break;
        case PhabricatorTransactions::TYPE_VIEW_POLICY:
          $view_policy = $t->getNewValue();
          if (in_array($view_policy, $rejected_policies)) {
            unset($transactions[$tkey]);
          }
          break;
      }
    }
    return array_values($transactions);
  }

  /**
   * Creates a custom policy for the given task having the following properties:
   *
   * 1. The users listed in $user_phids can view/edit
   * 2. Members of the project(s) in $project_phids can view/edit
   * 3. $task Subscribers (aka CCs) can view/edit
   *
   * @param ManiphestTask $task - the task the policy will apply to
   * @param array $user_phids - phids of users who can view/edit
   * @param array project_phids - phids of projects who's members can view/edit
   * @param bool include_subscribers - determine if subscribers can view/edit
   * @param PhabricatorPolicy old_policy - if supplied, update the rules to an existing policy
   * @param boolean save - save the policy to db storage?
   */
  public static function createCustomPolicy(
    $task,
    $user_phids,
    $project_phids,
    $include_subscribers=true,
    $old_policy=null,
    $save=true) {

    if (!is_array($user_phids)) {
      $user_phids = array($user_phids);
    }
    if (!is_array($project_phids)) {
      $project_phids = array($project_phids);
    }

    $policy = $old_policy instanceof PhabricatorPolicy
            ? $old_policy
            : new PhabricatorPolicy();

    $rules = array();
    if (!empty($user_phids)){
      $rules[] = array(
        'action' => PhabricatorPolicy::ACTION_ALLOW,
        'rule'   => 'PhabricatorUsersPolicyRule',
        'value'  => $user_phids,
      );
    }
    if (!empty($project_phids)) {
      $rules[] = array(
        'action' => PhabricatorPolicy::ACTION_ALLOW,
        'rule'   => 'PhabricatorProjectsPolicyRule',
        'value'  => $project_phids,
      );
    }
    if ($include_subscribers) {
      $rules[] = array(
        'action' => PhabricatorPolicy::ACTION_ALLOW,
        'rule'   => 'PhabricatorSubscriptionsSubscribersPolicyRule',
        'value'  => array($task->getPHID()),
      );
    }

    $policy
      ->setRules($rules)
      ->setDefaultAction(PhabricatorPolicy::ACTION_DENY);
    if ($save)
      $policy->save();
    return $policy;
  }

  /**
   * return the value of the 'security_topic' custom field
   * on the given $task
   * @param ManiphestTask $task
   * @return string the security_topic field value
   */
  public static function getSecurityFieldValue($task) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_EDIT);

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($task);

    $field_value = null;
    foreach ($field_list->getFields() as $field) {
      $field_key = $field->getFieldKey();

      if ($field_key == 'std:maniphest:security_topic') {
        $field_value = $field->getValueForStorage();
        break;
      }
    }
    return $field_value;
  }

  public static function isTaskPublic($task) {
    $policy = $task->getViewPolicy();

    $public_policies = array(
       PhabricatorPolicies::POLICY_PUBLIC,
       PhabricatorPolicies::POLICY_USER);

    return in_array($policy, $public_policies);
  }


  public static function userCanLockTask(PhabricatorUser $user, ManiphestTask $task) {
    if (!$user->isLoggedIn()) {
      return false;
    }
    $user_phid = $user->getPHID();
    $author_phid = $task->getAuthorPHID();
    if ($user_phid == $author_phid) {
      return true;
    }
    $trusted_project_names = ["Trusted-Contributors", "WMF-NDA", "acl*sre-team", "acl*security"];
    $projects = self::getProjectByName($trusted_project_names, $user, true);

    foreach ($projects as $proj) {
      try {
        if ($proj instanceof PhabricatorProject &&
            $proj->isUserMember($user_phid)) {
          return true;
        }
      } catch(PhabricatorDataNotAttachedException $e) {
        continue;
      }
    }

    return false;
  }

  public static function createPrivateSubtask($task) {
    $ops = self::getProjectByName('acl*sre-team');
    $ops_phids = array($ops->getPHID() => $ops->getPHID());
    $project = self::getProjectByName('operations');
    $project_phids = array(
      $project->getPHID(),$ops->getPHID()
    );

    $task->save();

    $viewer = PhabricatorUser::getOmnipotentUser();

    $transactions = array();

    // Make this public task depend on a corresponding 'private task'
    $edge_type = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;

    // First check for a pre-existant 'private task':
    $preexisting_tasks = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $task->getPHID(),
      $edge_type);

    // if there isn't already a 'private task', create one:
    if (!count($preexisting_tasks)) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array('admin'))
        ->executeOne();

      $policy = self::createCustomPolicy($task, array(), $ops_phids, false);

      $oid = $task->getID();

      $private_task = ManiphestTask::initializeNewTask($viewer);
      $private_task->setViewPolicy($policy->getPHID())
                 ->setEditPolicy($policy->getPHID())
                 ->setTitle("ops access request (T{$oid})")
                 ->setAuthorPHID($user->getPHID())
                 ->attachProjectPHIDs($project_phids)
                 ->save();

      $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $transactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $project_type)
        ->setNewValue(
        array(
          '=' => array_fuse($project_phids),
        ));

      // TODO: This should be transactional now.
      $edge_editor = id(new PhabricatorEdgeEditor());

      foreach($project_phids as $project_phid) {
        $edge_editor->addEdge(
          $private_task->getPHID(),
          $project_type,
          $project_phid);
      }

      $edge_editor
        ->addEdge(
          $task->getPHID(),
          $edge_type,
          $private_task->getPHID())
        ->save();

    }

    return $transactions;
  }

}

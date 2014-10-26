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
  public static function getProjectByName($projectName) {
    static $projects = array();
    if (isset($projects[$projectName])){
      return $projects[$projectName];
    }

    $query = new PhabricatorProjectQuery();
    $project = $query->setViewer(PhabricatorUser::getOmnipotentUser())
                     ->withNames(array($projectName))
                     ->needMembers(true)
                     ->executeOne();

    if (!$project) {
      return null;
    }

    return $projects[$projectName] = $project;
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
        return WMFSecurityPolicy::getProjectByName('security');
      case 'ops-access-request':
        return WMFSecurityPolicy::getProjectByName('Ops-Access-Requests');
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
    return $transactions;
  }

  /**
   * Creates a custom policy for the given task having the following properties:
   *
   * 1. The users listed in $user_phids can view/edit
   * 2. Members of the project(s) in $project_phids can view/edit
   * 3. $task Subscribers (aka CCs) can view/edit
   *
   * @param ManiphestTask $task
   * @param array(PHID) $user_phids
   * @param array(PHID) $project_phids
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
        'rule'   => 'PhabricatorPolicyRuleUsers',
        'value'  => $user_phids,
      );
    }
    if (!empty($project_phids)) {
      $rules[] = array(
        'action' => PhabricatorPolicy::ACTION_ALLOW,
        'rule'   => 'PhabricatorPolicyRuleProjects',
        'value'  => $project_phids,
      );
    }
    if ($include_subscribers) {
      $rules[] = array(
        'action' => PhabricatorPolicy::ACTION_ALLOW,
        'rule'   => 'PhabricatorPolicyRuleTaskSubscribers',
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


  public static function createPrivateSubtask($task) {
    $ops = self::getProjectByName('operations');
    $ops_phids = array($ops->getPHID() => $ops->getPHID());
    $project = self::getProjectByName('Ops-Access-Requests');
    $project_phids = array(
      $project->getPHID(),$ops->getPHID()
    );


    $viewer = PhabricatorUser::getOmnipotentUser();

    $transactions = array();

    // Make this public task depend on a corresponding 'private task'
    $edge_type = PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK;

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

      $policy = createCustomPolicy($task, array(), $ops_phids, true);

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

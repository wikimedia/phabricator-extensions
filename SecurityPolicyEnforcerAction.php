<?php

class SecurityPolicyEnforcerAction extends HeraldCustomAction {

  public function appliesToAdapter(HeraldAdapter $adapter) {
    return $adapter instanceof HeraldManiphestTaskAdapter;
  }

  public function appliesToRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function getActionKey() {
    return "SecurityPolicy";
  }

  public function getActionName() {
    return "Ensure Security Task Policy Are Enforced";
  }

  public function getActionType() {
    return HeraldAdapter::VALUE_NONE;
  }

  public function applyEffect(
    HeraldAdapter $adapter,
    $object,
    HeraldEffect $effect) {

    /** @var ManiphestTask */
    $task = $object;

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

    // These case statements tie into field values set in the
    // maniphest custom fields key
    $enforce = true;
    switch ($field_value) {
      case 'ops-sensitive':
        $enforce = true;
        $phid = $this->getProjectPHID('operations');

        //operations group
        $view_policy = $phid;
        $edit_policy = $phid;

        $project_phids = array($phid);

        break;
      case 'ops-access-request':
        $enforce = true;

        //operations group
        $phid = $this->getProjectPHID('operations');
        $view_policy = $phid;
        $edit_policy = $phid;

        $project_phids = array($phid);

        // Make this public task depend on a corresponding 'private task'
        $edge_type = PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK;

        // First check for a pre-existant 'private task':
        $preexisting_tasks = PhabricatorEdgeQuery::loadDestinationPHIDs(
          $task->getPHID(),
          $edge_type);

        // if there isn't already a 'private task', create one:
        if (!count($preexisting_tasks)) {
          $oid = $task->getID();
          $user = id(new PhabricatorPeopleQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withUsernames(array('admin'))
            ->executeOne();

          $private_task = ManiphestTask::initializeNewTask($viewer);
          $private_task->setViewPolicy($view_policy)
                     ->setEditPolicy($edit_policy)
                     ->setTitle("ops access request: {$oid}")
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
              $private_task ->getPHID())
            ->save();
        }

        // we already set the security policy on the 'private task' but
        // not this task. Now reset the policy vars to avoid making the
        // 'public task' be private.
        $view_policy = null;
        $edit_policy = null;

        break;
      case 'sensitive':
        $enforce = true;

        //operations group
        $phid = $this->getProjectPHID('security');

        $view_policy = $phid;
        $edit_policy = $phid;

        $project_phids = array($phid);

        break;
      case 'security-bug':
        $phid = $this->getProjectPHID('security');
        //operations group
        $view_policy = $phid;
        $edit_policy = $phid;
        $project_phids = array($phid);

        break;
      default:
        $enforce = false;
    }

    if ($enforce) {
      $transactions = array();

      if ($view_policy !== null) {
        $transactions[] = id(new ManiphestTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($view_policy);
      }

      if ($edit_policy !== null) {
        $transactions[] = id(new ManiphestTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($edit_policy);
      }

      if ($project_phids) {
        $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $transactions[] = id(new ManiphestTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $project_type)
          ->setNewValue(
          array(
            '=' => array_fuse($project_phids),
          ));
      }

      foreach ($transactions as $transaction) {
        $adapter->queueTransaction($transaction);
      }
    }

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Set security policy'));
  }

  /**
   * look up a project by name
   */
  function getProjectPHID($projectName) {
    static $phids = array();
    if (isset($phids[$projectName])){
      return $phids[$projectName];
    }

    $query = new PhabricatorProjectQuery();
    $project = $query->setViewer(PhabricatorUser::getOmnipotentUser())
                     ->withNames(array($projectName))
                     ->executeOne();

    if (!$project) {
      return null;
    }

    $phids[$projectName] = $project->getPHID();
    return $phids[$projectName];
  }
}

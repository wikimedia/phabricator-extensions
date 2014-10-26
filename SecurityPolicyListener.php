<?php

class SecurityPolicyEventListener
  extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK);
    $this->listen(PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK:
        return $this->willEditTask($event);
      case PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK:
        return $this->didEditTask($event);
    }
  }

  private function willEditTask($event) {
    $task         = $event->getValue('task');
    $transactions = $event->getValue('transactions');
    $is_new       = $event->getValue('new');
    $type_viewpol = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $type_editpol = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $type_edge    = PhabricatorTransactions::TYPE_EDGE;
    $type_hasproj = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
    $security_setting = WMFSecurityPolicy::getSecurityFieldValue($task);

    if ($security_setting == 'none' || $security_setting == 'default') {
      return;
    }

    if ($project = WMFSecurityPolicy::getSecurityProjectForTask($task)) {
      $project_phids = array($project->getPHID() => $project->getPHID());
    } else {
      $project_phids = array();
    }

    // validate the policy changes on edits to pre-existing tasks:
    if (!$is_new) {
      // on pre-existing tasks we simply
      // filter out any transactions that would make the task public
      $event->setValue('transactions',
        WMFSecurityPolicy::filter_policy_transactions($transactions));
      return;
    }

    if ($security_setting == 'ops-access-request') {
      // ops access requests don't modify the request task, instead
      // we create a subtask which gets custom policy settings applied.
      // any returned transactions get applied to the parent task to record
      // the association with the subtask.
      $trans = WMFSecurityPolicy::createPrivateSubtask($task);
      $trans[$type_edge] = id(new ManiphestTransaction())
          ->setTransactionType($type_edge)
          ->setMetadataValue('edge:type',$type_hasproj)
          ->setNewValue(array('+' => $project_phids));
    } else {
      // other secure tasks get standard policies applied

      // if it's a security-bug then we include subscribers (CCs) in the
      // people who can view and edit
      $include_subscribers = ($security_setting == 'security-bug');

      $edit_policy = WMFSecurityPolicy::createCustomPolicy(
        $task,
        $task->getAuthorPHID(),
        $project_phids,
        $include_subscribers
      );
      // view policy and edit policy will be identical:
      $view_policy = $edit_policy;

      $trans = array();

      if (!empty($project_phids)) {
        $trans[$type_edge] = id(new ManiphestTransaction())
            ->setTransactionType($type_edge)
            ->setMetadataValue('edge:type',$type_hasproj)
            ->setNewValue(array('+' => $project_phids));
      }

      $trans[$type_viewpol] = id(new ManiphestTransaction())
          ->setTransactionType($type_viewpol)
          ->setNewValue($view_policy->getPHID());
      $trans[$type_editpol] = id(new ManiphestTransaction())
          ->setTransactionType($type_editpol)
          ->setNewValue($edit_policy->getPHID());

      // These transactions replace any user-generated transactions of
      // the same type, e.g. user-supplied policy gets overwritten
      // with custom policy.
      foreach($transactions as $n => $t) {
        $type = $t->getTransactionType();
        if ($type == $type_edge) {
          if ($t->getMetadataValue('edge:type') == $type_hasproj) {
            $val = $t->getNewValue();
            if (isset($val['=']) && is_array($val['='])){
              $val['='] = array_unique(
                            array_merge(
                              $val['='], $project_phids));
            } else {
              $val['+'] = $project_phids;
            }
            $t->setNewValue($val);
            unset($trans[$type_edge]);
          }
        }
        else if (isset($trans[$type])){
          $transactions[$n] = $trans[$type];
          unset($trans[$type]);
        }
      }

      $event->setValue('transactions', $transactions);

    }

    if (!empty($trans)) {
      // apply remaining transactions
      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_UNKNOWN,
        array());

      $acting_as = id(new PhabricatorManiphestApplication())
          ->getPHID();

      id(new ManiphestTransactionEditor())
        ->setActor(PhabricatorUser::getOmnipotentUser())
        ->setActingAsPHID($acting_as)
        ->setContentSource($content_source)
        ->applyTransactions($task, $trans);
    }

  }

  private function didEditTask($event) {
    $task         = $event->getValue('task');
    $transactions = $event->getValue('transactions');
    $is_new       = $event->getValue('new');
    $viewer       = PhabricatorUser::getOmnipotentUser();
    $policies     = PhabricatorPolicyQuery::loadPolicies($viewer, $task);
    $this->setTaskId($task, $policies['view']);
    $this->setTaskId($task, $policies['edit']);
  }

  // fix the task id on PhabricatorPolicyRuleTaskSubscribers rules which get a
  // null value due to a race between task creation and policy creation.
  // the task id doesn't exist until after policy object is created but
  // policy needs the task id, so we fix the null value here, after task creation:
  private function setTaskId($task, $policy) {
    $rules        = $policy->getRules();
    $save         = false;

    foreach($rules as $key=>$rule) {
      if ($rule['rule'] == 'PhabricatorPolicyRuleTaskSubscribers') {
        if ($rule['value'][0]==null) {
          $rule['value'] = array($task->getPHID());
          $rules[$key] = $rule;
          $save = true;
        }
      }
    }
    if ($save) {
      $policy->setRules($rules);
      $policy->save();
    }
  }
}

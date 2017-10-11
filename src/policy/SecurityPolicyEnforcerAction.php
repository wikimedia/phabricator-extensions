<?php
// @TODO: remove this file once the herald rule that uses it is removed
// from production.

class SecurityPolicyEnforcerAction extends HeraldAction {
  const TYPECONST = 'SecurityPolicy';
  const ACTIONCONST = 'SecurityPolicy';

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return $object instanceof ManiphestTask;
  }

  public function supportsRuleType($rule_type) {
    if ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) {
      return true;
    } else {
      return false;
    }
  }

  public function getActionKey() {
    return "SecurityPolicy";
  }

  public function getHeraldActionName() {
    return pht('Enforce Task Security Policy');
  }

  public function renderActionDescription($value) {
    return pht("Ensure Security Task Policies are Enforced");
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function getActionType() {
    return new HeraldEmptyFieldValue();
  }

  public function applyEffect($object,  HeraldEffect $effect) {
    $adapter = $this->getAdapter();
    $object = $adapter->getObject();
    /** @var ManiphestTask */
    $task = $object;

    $is_new = $adapter->getIsNewObject();

    // we set to true if/when we apply any effect
    $applied = false;

    if ($is_new) {
      // SecurityPolicyEventListener will take care of
      // setting the policy for newly created tasks so
      // this herald rule only needs to run on subsequent
      // edits to secure tasks.
      return new HeraldApplyTranscript($effect,$applied);
    }
    $security_setting = WMFSecurityPolicy::getSecurityFieldValue($task);

    // only enforce security for the above-listed values of security_setting
    if ($security_setting=='security-bug' ||
        $security_setting=='sensitive') {

        // These policies are too-open and would allow anyone to view
        // the protected task. We override these if someone tries to
        // set them on a 'secure task'
        $rejected_policies = array(
          PhabricatorPolicies::POLICY_PUBLIC,
          PhabricatorPolicies::POLICY_USER,
        );
        $forced_policies = array();
    } else {
      //do nothing
      return new HeraldApplyTranscript($effect,$applied);
    }

    if ($project = WMFSecurityPolicy::getSecurityProjectForTask($task)) {
      $project_phids = array($project->getPHID());
    } else {
      $project_phids = array();
    }

    // check rejected policies first
    if (in_array($task->getViewPolicy(), $rejected_policies)
      ||in_array($task->getEditPolicy(), $rejected_policies)) {

      // only add the 'subscribers' policy rule to security bugs:
      $include_subscribers = ($security_setting == 'security-bug');

      $view_policy = WMFSecurityPolicy::createCustomPolicy(
        $task,
        $task->getAuthorPHID(),
        $project_phids,
        $include_subscribers);

      $edit_policy = $view_policy;

      $adapter->queueTransaction(id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($view_policy->getPHID()));
      $adapter->queueTransaction(id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($edit_policy->getPHID()));
      $applied = true;
    } else if (!empty($forced_policies)) {
      foreach ($forced_policies as $type=>$policy) {
        $adapter->queueTransaction(id(new ManiphestTransaction())
          ->setTransactionType($type)
          ->setNewValue($policy));
      }
      $applied = true;
    }

    if (!empty($project_phids)) {
      $adapter->queueTransaction(id(new ManiphestTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
              ->setMetadataValue(
                  'edge:type',
                  PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
              ->setNewValue(array('+' => array_fuse($project_phids))));
      $applied = true;
    }

    return new HeraldApplyTranscript(
      $effect,
      $applied,
      pht('Reset security settings'));
  }



}

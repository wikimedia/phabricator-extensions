<?php
// @TODO: remove this file once the herald rule that uses it is removed
// from production.

class SecurityPolicyEnforcerAction extends HeraldCustomAction {

  public function appliesToAdapter(HeraldAdapter $adapter) {
    return $adapter instanceof HeraldManiphestTaskAdapter;
  }

  public function appliesToRuleType($rule_type) {
    if ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) {
      return true;
    } else {
      return false;
    }
  }

  public function getActionKey() {
    return "SecurityPolicy";
  }

  public function getActionName() {
    return "Ensure Security Task Policies are Enforced";
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

    $is_new = $adapter->getIsNewObject();

    // we set to true if/when we apply any effect
    $applied = false;

    // This custom action is now a NOOP as the functionality has moved to
    // SecurityPolicyListener.php
    return new HeraldApplyTranscript($effect,$applied);


    if ($is_new) {
      // SecurityPolicyEventListener will take care of
      // setting the policy for newly created tasks so
      // this herald rule only needs to run on subsequent
      // edits to secure tasks.
      return new HeraldApplyTranscript($effect,$applied);
    }
    $security_setting = WMFSecurityPolicy::getSecurityFieldValue($task);
    $project = WMFSecurityPolicy::getSecurityProjectForTask($task);
    // we only do something if this is a secure task
    // if it's not a secure task then $project will be null
    if ($project) {
      $project_phids = array($project->getPHID());

      // These policies are too-open and would allow anyone to view
      // the protected task. We override these if someone tries to
      // set them on a 'secure task'
      $rejected_policies = array(
        PhabricatorPolicies::POLICY_PUBLIC,
        PhabricatorPolicies::POLICY_USER,
      );
      if (in_array($task->getViewPolicy(), $rejected_policies)
        ||in_array($task->getEditPolicy(), $rejected_policies)) {

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
      }

    }

    return new HeraldApplyTranscript(
      $effect,
      $applied,
      pht('Reset security policy'));
  }



}

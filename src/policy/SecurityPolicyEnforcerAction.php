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
    $task_phids = array($task->getPHID());

    $edge_query = id(new PhabricatorEdgeQuery())
    ->withSourcePHIDs($task_phids)
    ->withEdgeTypes(
      array(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
      ));
    $edge_query->execute();
    $task_project_phids = $edge_query->getDestinationPHIDs($task_phids);

    $permanently_private = WMFSecurityPolicy::getProjectByName('PermanentlyPrivate');
    $is_permanently_private = in_array($permanently_private->getPHID(), $task_project_phids);

    if (!($task->getSubtype() == 'security' || $is_permanently_private)) {
        // only enforce security on tasks with at least one of:
        // #PermanentlyPrivate tag
        // subtype 'security'
        return new HeraldApplyTranscript($effect, $applied);
    }

    // These policies are too-open and would allow anyone to view
    // the protected task. We override these if someone tries to
    // set them on a 'secure task'
    $rejected_policies = array(
      PhabricatorPolicies::POLICY_PUBLIC,
      PhabricatorPolicies::POLICY_USER,
      'obj.subscriptions.subscribers',
      'obj.project.members',
      'obj.maniphest.author',
    );
    $forced_policies = array();


    $project_phids = array();
    $security_project  = WMFSecurityPolicy::getProjectByName('acl*security');
    $projects = WMFSecurityPolicy::getProjectByName(['security', 'Security-Team']);

    foreach($projects as $project) {
      $phid = $project->getPHID();
      $project_phids[$phid] = $phid;
    }

    $project_phids = array_values($project_phids);
    phlog($task->getViewPolicy());
    // check rejected policies first
    if (in_array($task->getViewPolicy(), $rejected_policies)
      ||in_array($task->getEditPolicy(), $rejected_policies)) {

      $include_subscribers = true;

      $view_policy = WMFSecurityPolicy::createCustomPolicy(
        $task,
        $task->getAuthorPHID(),
        [$security_project->getPHID()],
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

    if (!empty($forced_policies)) {
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

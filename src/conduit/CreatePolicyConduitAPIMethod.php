<?php
class CreatePolicyConduitAPIMethod extends ConduitAPIMethod {
  public function getAPIMethodName() {
    return 'policy.create';
  }

  public function getMethodDescription() {
    return pht('Create custom access control policy objects.');
  }

  protected function defineParamTypes() {
    return array(
      'policy' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'dict<dict>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $actor = $request->getUser();

    $action_options = array(
      PhabricatorPolicy::ACTION_ALLOW => pht('Allow'),
      PhabricatorPolicy::ACTION_DENY => pht('Deny'),
    );

    $rules = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorPolicyRule')
      ->execute();

    foreach ($rules as $key => $rule) {
      if (!$rule->canApplyToObject($object)) {
        unset($rules[$key]);
      }
    }

    $rules = msort($rules, 'getRuleOrder');

    $default_rule = array(
      'action' => head_key($action_options),
      'rule' => head_key($rules),
      'value' => null,
    );

    if ($phid) {
      $policies = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($phid))
        ->execute();
      if (!$policies) {
        return new Aphront404Response();
      }
      $policy = head($policies);
    } else {
      $policy = id(new PhabricatorPolicy())
        ->setRules(array($default_rule))
        ->setDefaultAction(PhabricatorPolicy::ACTION_DENY);
    }

    $root_id = celerity_generate_unique_node_id();

    $default_action = $policy->getDefaultAction();
    $rule_data = $policy->getRules();


    $data = $request->getValue('rules');
    try {
      $data = phutil_json_decode($data);
    } catch (PhutilJSONParserException $ex) {
      throw new ConduitException('ERR-INVALID-PARAMETER')
        ->setErrorDescription(pht('Failed to JSON decode rule data!'));
    }

    $rule_data = array();
    foreach ($data as $rule) {
      $action = idx($rule, 'action');
      switch ($action) {
        case 'allow':
        case 'deny':
          break;
        default:
          throw new ConduitException('ERR-INVALID-PARAMETER')
            ->setErrorDescription(pht("Invalid action '%s'!", $action));
      }

      $rule_class = idx($rule, 'rule');
      if (empty($rules[$rule_class])) {
        throw new ConduitException('ERR-INVALID-PARAMETER')
          ->setErrorDescription(pht("Invalid rule class '%s'!", $rule_class));
      }

      $rule_obj = $rules[$rule_class];

      $value = $rule_obj->getValueForStorage(idx($rule, 'value'));

      $rule_data[] = array(
        'action' => $action,
        'rule' => $rule_class,
        'value' => $value,
      );
    }

    // Filter out nonsense rules, like a "users" rule without any users
    // actually specified.
    $valid_rules = array();
    foreach ($rule_data as $rule) {
      $rule_class = $rule['rule'];
      if ($rules[$rule_class]->ruleHasEffect($rule['value'])) {
        $valid_rules[] = $rule;
      }
    }

    if (!$valid_rules) {
      throw new ConduitException('ERR-INVALID-PARAMETER')
        ->setErrorDescription(pht('Rules do not have any effect.'));
    }

    // NOTE: Policies are immutable once created, and we always create a new
    // policy here. If we didn't, we would need to lock this endpoint down,
    // as users could otherwise just go edit the policies of objects with
    // custom policies.
    $data = array();


    $new_policy = new PhabricatorPolicy();
    $new_policy->setRules($valid_rules);
    $new_policy->setDefaultAction($request->getStr('default'));
    $new_policy->save();

    $data = array(
      'phid' => $new_policy->getPHID(),
      'info' => array(
        'name' => $new_policy->getName(),
        'full' => $new_policy->getName(),
        'icon' => $new_policy->getIcon(),
      ),
    );
    return array('data' => $data);
  }
}

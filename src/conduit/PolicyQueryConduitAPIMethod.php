<?php
class PolicyQueryConduitAPIMethod extends ConduitAPIMethod {
  public function getAPIMethodName() {
    return 'policy.query';
  }

  public function getMethodDescription() {
    return pht('Execute searches for Policies.');
  }

  protected function defineParamTypes() {
    return array(
      'phids'  => 'required ist<phid>',
      'limit'  => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'dict<string, wild>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phids = $request->getValue('phids');
    if (!$phids) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(pht("PHIDs required"));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids)
      ->execute();

    if (!$policies) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(
          pht("Unknown policies: %s", implode(', ', $phids)));
    }

    $result = array();
    foreach ($policies as $phid => $policy) {
      $type = $policy->getType();
      $result[$phid] = array(
        'phid' => $phid,
        'type' => $type,
        'name' => $policy->getName(),
        'shortName' => $policy->getShortName(),
        'fullName' => $policy->getFullName(),
        'href' => $policy->getHref(),
        'workflow' => $policy->getWorkflow(),
        'icon' => $policy->getIcon(),
      );
      if ($type === PhabricatorPolicyType::TYPE_CUSTOM) {
        $result[$phid]['default'] = $policy->getDefaultAction();
        $result[$phid]['rules'] = $policy->getRules();
      }
    }
    return $result;
  }
}

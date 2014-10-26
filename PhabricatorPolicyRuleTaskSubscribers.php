<?php

final class PhabricatorPolicyRuleTaskSubscribers
  extends PhabricatorPolicyRule {

  private $subscribed_to = array();

  public function getRuleDescription() {
    return pht('subscribers of maniphest task');
  }

  public function willApplyRules(PhabricatorUser $viewer, array $values) {
    $values = array_unique(array_filter(array_mergev($values)));

    $this->subscribed_to = array();
    $viewer_phid = $viewer->getPHID();
    $tasks = id(new ManiphestTaskQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($values)
      ->execute();

    foreach($tasks as $task){
      $ccs = $task->getCCPHIDs();
      $this->subscribed_to[$task->getPHID()] = in_array($viewer_phid, $ccs);
    }
  }

  public function applyRule(PhabricatorUser $viewer, $value) {
    if (!is_array($value)){
      $value = array($value);
    }
    foreach($value as $v) {
      if (isset($this->subscribed_to[$v])) {
        return $this->subscribed_to[$v];
      }
    }
    return false;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_TOKENIZER;
  }

  public function getValueControlTemplate() {
    $datasource = new PhabricatorTypeaheadMonogramDatasource();

    return array(
      'markup' => new AphrontTokenizerTemplateView(),
      'uri' => $datasource->getDatasourceURI(),
      'placeholder' => $datasource->getPlaceholderText(),
    );
  }

  public function getRuleOrder() {
    return 800;
  }

  public function getValueForDisplay(PhabricatorUser $viewer, $value) {
    if (!is_array($value)) {
      $value = array($value);
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($value)
      ->execute();

    return mpull($handles, 'getFullName', 'getPHID');
  }

  public function ruleHasEffect($value) {
    return true;
  }

  public function getValueForStorage($value) {
    PhutilTypeSpec::newFromString('list<string>')->check($value);
    return array_values($value);
  }
}

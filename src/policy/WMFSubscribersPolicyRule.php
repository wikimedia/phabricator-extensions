<?php

final class WMFSubscribersPolicyRule
  extends PhabricatorPolicyRule {

  private static $subscribers_by_object = array();

  public function getRuleDescription() {
    return pht('users subscribed to');
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    $values = array_unique(array_filter(array_mergev($values)));
    if (empty($values)){
      return;
    }

    //see which objects need lookup (not already cached)
    $lookup_phids = array();
    foreach($values as $object_phid) {
      if (empty(self::$subscribers_by_object[$object_phid])){
        //save phids of tasks which need subscribers lookup
        $lookup_phids[]=$object_phid;
      }
    }
    if (empty($lookup_phids)){
      //everything already cached
      return;
    }

    //preload the subscribers for the objects in question
    $result = id(new PhabricatorSubscribersQuery())
      ->withObjectPHIDs($lookup_phids)
      ->execute();

    foreach ($result as $object_phid => $subscribers) {
      self::$subscribers_by_object[$object_phid] = $subscribers;
    }
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $viewer_phid = $viewer->getPHID();

    if (!is_array($value)) {
      $value = array($value);
    }
    foreach($value as $object_phid) {
      if (!isset(self::$subscribers_by_object[$object_phid]))
      {
        // not found, continue checking remaining objects (if any)
        continue;
      }
      if (in_array($viewer_phid, self::$subscribers_by_object[$object_phid])) {
        // found the viewer in a configured object's subscriber list
        // rule succeeds:
        return true;
      }
    }
    // viewer was not found in any object's subscriber list, rule fails:
    return false;
  }


  // remaining methods are needed to support the rule editor typeahead UI:

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

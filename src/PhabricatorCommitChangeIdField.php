<?php

final class PhabricatorCommitChangeIDField
  extends PhabricatorCommitCustomField {
  private $value;

  public function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getFieldKey() {
    return 'gerrit:change-id';
  }

  public function getFieldKeyForConduit() {
    return 'changeID';
  }

  public function getFieldName() {
    return pht('Change-Id');
  }

  public function getFieldDescription() {
    return pht('Ties commits to gerrit changes and provides a permanent link between them.');
  }

  public function canDisableField() {
    return true;
  }

  public function shouldDisableByDefault() {
    return true;
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }
  public function getValueForStorage() {
    return json_encode($this->getValue());
  }

  public function setValueFromStorage($value) {
    try {
      $this->setValue(phutil_json_decode($value));
    } catch (PhutilJSONParserException $ex) {
      $this->setValue(array());
    }
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStrList($this->getFieldKey()));
    return $this;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $links = array();
    foreach($this->getValue() as $value) {
      $url = 'https://gerrit.wikimedia.org/r/#q,'.$value.',n,z';
      $links[] = phutil_tag(
        'a',
        array(
          'href'  => $url,
          'title' => pht('View Change in Gerrit'),
          'target'=> '_blank'),
        $value);
    }
    return phutil_tag('span',array(),$links);
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function parseValueFromCommitMessage($value) {
    return preg_split('/[\s,]+/', $value, $limit = -1, PREG_SPLIT_NO_EMPTY);
  }

  public function renderCommitMessageValue(array $handles) {
    return $this->getValue();
  }

  public function readValueFromCommitMessage($value) {
    $this->setValue($value);
    return $this;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function getOldValueForApplicationTransactions() {
    return $this->getValue();
  }

  public function getNewValueForApplicationTransactions() {
    return $this->getValue();
  }

}

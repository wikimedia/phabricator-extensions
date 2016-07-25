<?php

final class GerritChangeIdField
  extends PhabricatorCommitCustomField {

  private $value = '';

  public function getFieldKey() {
    return 'gerrit:changeid';
  }

  public function getFieldName() {
    return pht('ChangeId');
  }

  public function getFieldDescription() {
    return pht('Shows the gerrit Change ID for a commit.');
  }

  public function shouldAppearInTransactionMail() {
    return false;
  }

  public function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldUseStorage() {
    return false;
  }

  public function getValueForStorage() {
    return json_encode($this->getValue());
  }

  public function setValueFromStorage($value) {
    try {
      $this->setValue(phutil_json_decode($value));
    } catch (PhutilJSONParserException $ex) {
      $this->setValue(array());
      $obj = $this->getObject();
      phlog($obj);
    }
    return $this;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    try {
      $data = $object->getCommitData();
      $message = $data->getCommitMessage();
      $message = explode("\n", $message);
      for ($i=count($message)-1; $i > 0; $i--) {
        $field = explode(": ", $message[$i]);
        if ($field[0] == 'Change-Id') {
          $value = trim($field[1]);
          $this->setValue($value);
          break;
        }
      }
    } catch (Exception $ex) {
      // ignore failures...
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
    $value = $this->getValue();
    if (!strlen($value)) {
      return phutil_tag('em',array(),'None');
    }
    $url = "https://gerrit.wikimedia.org/r/#/q/$value";
    $links[] = phutil_tag(
      'a',
      array(
        'href'  => $url,
        'title' => pht('View Change in Gerrit'),
        'target'=> '_blank'),
      $value);
    return phutil_tag('span',array('id'=>'commit-changeid'),$links);
  }

}

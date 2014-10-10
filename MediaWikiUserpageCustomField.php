<?php

final class MediaWikiUserpageCustomField extends PhabricatorUserCustomField {
  protected $externalAccount;

  public function shouldUseStorage() {
    return false;
  }

  public function getFieldKey() {
    return 'mediawiki:externalaccount';
  }

  public function getFieldName() {
    return pht("MediaWiki Userpage");
  }

  public function getFieldValue() {
    $account = $this->getExternalAccount();

    if (! $account || !strlen($account->getAccountURI())) {
      return null;
    }

    $uri = urldecode($account->getAccountURI());

    // Split on the User: part of the userpage uri
    $name = explode('User:',$uri);
    // grab the part after User:
    $name = array_pop($name);
    // decode for display:
    $name = urldecode(rawurldecode($name));

    return $name;
  }

  protected function getExternalAccount() {
    if (!$this->externalAccount) {
      $user = $this->getObject();
      $this->externalAccount = id(new PhabricatorExternalAccount())->loadOneWhere(
        'userPHID = %s AND accountType = %s',
        $user->getPHID(),
        'mediawiki');
    }
    return $this->externalAccount;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {

    $account = $this->getExternalAccount();

    if (! $account || !strlen($account->getAccountURI())) {
      return pht('Unknown');
    }

    $uri = urldecode($account->getAccountURI());

    // Split on the User: part of the userpage uri
    $name = explode('User:',$uri);
    // grab the part after User:
    $name = array_pop($name);
    // decode for display:
    $name = urldecode(rawurldecode($name));

    return phutil_tag(
      'a',
      array(
        'href' => $uri
      ),
      $name);
  }


  public function shouldAppearInApplicationSearch() {
    return true;
  }


  public function getFieldType() {
    return 'text';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newStringIndex($value);
      $indexes[] = $this->newStringIndex(urldecode($this->getExternalAccount()->getAccountURI()));
      $parts = explode(' ',$value);
      if (count($parts) > 1) {
        foreach($parts as $part) {
          $indexes[] = $this->newStringIndex($part);
        }
      }
    }

    return $indexes;
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {

    return $request->getStr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {

    if (strlen($value)) {
      $query->withApplicationSearchContainsConstraint(
        $this->newStringIndex(null),
        $value);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value,
    array $handles) {

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel($this->getFieldName())
        ->setName($this->getFieldKey())
        ->setValue($value));
  }

  protected function newStringIndexStorage() {
    return new PhabricatorUserCustomFieldStringIndex();
  }

}

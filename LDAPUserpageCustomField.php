<?php

final class LDAPUserpageCustomField extends PhabricatorUserCustomField {

  public function shouldUseStorage() {
    return false;
  }

  public function getFieldKey() {
    return 'ldap:externalaccount';
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return pht('LDAP User');
  }

  public function renderPropertyViewValue(array $handles) {
    $user = $this->getObject();

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'userPHID = %s AND accountType = %s',
      $user->getPHID(),
      'ldap');

    if (! $account || !strlen($account->getusername())) {
      return pht('Unknown');
    }
    $url = 'https://wikitech.wikimedia.org/wiki/User:';
    $name = $account->getusername();
    $uri = urldecode($url . $name);

    return phutil_tag(
      'a',
      array(
        'href' => $uri
      ),
      $name);
  }

}

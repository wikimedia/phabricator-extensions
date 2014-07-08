<?php

final class MediaWikiUserpageCustomField extends PhabricatorUserCustomField {

  public function shouldUseStorage() {
    return false;
  }

  public function getFieldKey() {
    return 'mediawiki:externalaccount';
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return pht('MediaWiki Userpage');
  }

  public function renderPropertyViewValue(array $handles) {
    $user = $this->getObject();

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'userPHID = %s AND accountType = %s',
      $user->getPHID(),
      'mediawiki');

    if (! $account || !strlen($account->getAccountURI())) {
      return pht('Unknown');
    }

    $uri = $account->getAccountURI();

    // Split on the User: part of the userpage uri
    $name = explode('User:',$uri);
    // grab the part after User:
    $name = array_pop($name);
    // decode for display:
    $name = urldecode($name);

    return phutil_tag(
      'a',
      array(
        'href' => $uri
      ),
      $name);
  }

}

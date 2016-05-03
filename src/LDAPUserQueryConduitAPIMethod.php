<?php

final class LDAPUserQueryConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.ldapquery';
  }

  public function getMethodDescription() {
    return pht('Query users by ldap username.');
  }

  protected function defineParamTypes() {
    return array(
      'ldapnames'    => 'list<string>',
      'offset'       => 'optional int',
      'limit'        => 'optional int (default = 100)',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $usernames   = array();
    $ldapnames   = $request->getValue('ldapnames', array());
    $emails      = array();
    $realnames   = array();
    $phids       = array();
    $ids         = array();
    $offset      = $request->getValue('offset',    0);
    $limit       = $request->getValue('limit',     100);

    if (count($ldapnames)) {
      $ldap_accounts = id(new PhabricatorExternalAccount())->loadAllWhere(
        'accountType = %s AND username IN (%Ls)',
        'ldap', $ldapnames);
      foreach($ldap_accounts as $account) {
        $phids[] = $account->getUserPHID();
      }
      $phids = array_unique($phids);
    }

    $query = id(new PhabricatorPeopleQuery())
      ->setViewer($request->getUser())
      ->needProfileImage(true)
      ->needAvailability(true);

    if ($usernames) {
      $query->withUsernames($usernames);
    }
    if ($emails) {
      $query->withEmails($emails);
    }
    if ($realnames) {
      $query->withRealnames($realnames);
    }
    if ($phids) {
      $query->withPHIDs($phids);
    }
    if ($ids) {
      $query->withIDs($ids);
    }
    if ($limit) {
      $query->setLimit($limit);
    }
    if ($offset) {
      $query->setOffset($offset);
    }
    $users = $query->execute();

    $results = array();
    foreach ($users as $user) {
      $results[] = $this->buildUserInformationDictionary(
        $user,
        $with_email = false,
        $with_availability = true);
    }
    return $results;
  }

}

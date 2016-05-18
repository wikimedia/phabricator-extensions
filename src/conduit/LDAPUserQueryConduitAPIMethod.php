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
    $ldapnames   = $request->getValue('ldapnames', array());
    $phids       = array();
    $offset      = $request->getValue('offset',    0);
    $limit       = $request->getValue('limit',     100);
    $phid_name   = array();
    if (count($ldapnames)) {
      $ldap_accounts = id(new PhabricatorExternalAccount())->loadAllWhere(
        'accountType = %s AND username IN (%Ls)',
        'ldap', $ldapnames);
      foreach($ldap_accounts as $account) {
        $phid = $account->getUserPHID();
        $phid_name[$phid] = $account->getUsername();
      }
      $phids = array_keys($phid_name);
    }

    if (!count($phids)) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(
          pht('Unknown or missing ldap names: %s',
              implode(', ', $ldapnames)));
    }

    $query = id(new PhabricatorPeopleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids)
      ->needProfileImage(true)
      ->needAvailability(true);

    if ($limit) {
      $query->setLimit($limit);
    }
    if ($offset) {
      $query->setOffset($offset);
    }
    $users = $query->execute();

    $results = array();
    foreach ($users as $user) {
      $user_info = $this->buildUserInformationDictionary(
        $user,
        $with_email = false,
        $with_availability = true);
      $phid = $user_info['phid'];
      $user_info['ldap_username'] = $phid_name[$phid];
      $results[] = $user_info;
    }
    return $results;
  }

}

<?php

final class MediaWikiUserQueryConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.mediawikiquery';
  }

  public function getMethodDescription() {
    return pht('Query users by MediaWiki username.');
  }

  protected function defineParamTypes() {
    return array(
      'names'  => 'list<string>',
      'offset' => 'optional int (default = 0)',
      'limit'  => 'optional int (default = 100)',
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
    $names     = $request->getValue('names', array());
    $phids     = array();
    $offset    = $request->getValue('offset', 0);
    $limit     = $request->getValue('limit',  100);
    $phid_name = array();
    if (count($names)) {
      $mw_accounts = id(new PhabricatorExternalAccount())->loadAllWhere(
        'accountType = %s AND username IN (%Ls)',
        'mediawiki', $names);
      foreach($mw_accounts as $account) {
        $phid = $account->getUserPHID();
        $phid_name[$phid] = $account->getUsername();
      }
      $phids = array_keys($phid_name);
    }

    if (!count($phids)) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
        ->setErrorDescription(
          pht('Unknown or missing mediawiki names: %s',
              implode(', ', $names)));
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
      $user_info['mediawiki_username'] = $phid_name[$phid];
      $results[] = $user_info;
    }
    return $results;
  }

}

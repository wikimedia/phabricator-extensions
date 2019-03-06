<?php
class UserTransactionsConduitAPIMethod extends ConduitAPIMethod {
  public function getAPIMethodName() {
    return 'user.transactions';
  }

  public function getMethodDescription() {
    return pht('Find public transactions by a particular user.');
  }

  protected function defineParamTypes() {
    return array(
      'username'  => 'optional string',
      'userPHID' => 'optional string',
      'offset' => 'required int',
      'limit'  => 'required int',
    );
  }

  protected function defineReturnType() {
    return 'dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    if (!$viewer->getIsAdmin()) {
      throw new Exception(
        pht('Only admins can call this API')
      );
    }
    $query = new PhabricatorPeopleQuery();
    $query->setViewer($viewer);
    if ($request->getValueExists('username')) {
      $query->withUsernames(array($request->getValue('username')));
    } else if ($request->getValueExists('userPHID')) {
      $query->withPHIDs(array($request->getValue('userPHID')));
    } else {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
      ->setErrorDescription(
        pht('You must provide either a username or userPHID'));
    }

    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');
    if (!$limit) {
      $limit = 100;
    }
    $targetUser = $query->executeOne();

    if (!$targetUser) {
      throw id(new ConduitException('ERR-INVALID-PARAMETER'))
      ->setErrorDescription(
        pht('The specified username / userPHID was not found'));
    }
    $userPHID = $targetUser->getPHID();
    $connection = id(new ManiphestTransaction())->establishConnection('r');
    $transactionTable = new ManiphestTransaction();
    $commentTable = new ManiphestTransactionComment();
    $sql = 'SELECT
      t.phid,
      t.objectPHID,
      t.dateCreated,
      t.transactionType,
      t.oldValue,
      t.newValue,
      t.metadata,
      c.content as commentText
      FROM %R t LEFT JOIN %R c
      ON c.transactionPHID = t.phid
      AND c.viewPolicy = t.viewPolicy
      WHERE t.authorPHID = %s
      AND t.viewPolicy = %s
      ORDER BY t.dateModified ASC
      LIMIT %d, %d';

    $transactions = queryfx_all(
      $connection,
      $sql,
      $transactionTable,
      $commentTable,
      $userPHID,
      'public',
      $offset,
      $limit);

    if (!$transactions) {
      $transactions = array();
    }

    $result = array();
    foreach ($transactions as $trns) {
      try {
        $trns['oldValue'] = phutil_json_decode($trns['oldValue']);
      } catch(PhutilJSONParserException $err) {}
      try {
        $trns['newValue'] = phutil_json_decode($trns['newValue']);
      } catch(PhutilJSONParserException $err) {}
      try {
        $trns['metadata'] = phutil_json_decode($trns['metadata']);
      } catch(PhutilJSONParserException $err) {}
      $objectPHID = $trns['objectPHID'];
      $result[$objectPHID][] = $trns;
    }
    return $result;
  }
}

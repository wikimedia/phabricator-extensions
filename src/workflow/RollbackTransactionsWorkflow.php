<?php

class RollbackTransactionsWorkflow
extends WikimediaCLIWorkflow
{
  protected $edgeEditor;
  protected $dryrun = false;
  protected $deleteTransactions = false;

  private $fieldMap = array(
    "title" => "title",
    "name" => "title",
    "description" => "description",
    "core:subscribers" => "subscribers",
    "core:subtype" => "subtype",
    PhabricatorTransactions::TYPE_SUBTYPE => "subtype",
    PhabricatorTransactions::TYPE_VIEW_POLICY => "viewPolicy",
    PhabricatorTransactions::TYPE_EDIT_POLICY => "editPolicy",
    "core:create" => null,
    "core:comment" => "comment",
    "reassign" => "ownerPHID",
    "status" => "status",
  );

  protected $methodMap = array(
    "subscribers" => "attachSubscriberPHIDs",
  );

  protected function didConstruct()
  {
    $this->edgeEditor = new PhabricatorEdgeEditor();

    $this
      ->setName('execute')
      ->setExamples('**execute** [options]')
      ->setSynopsis(
        pht('Execute a rollback.')
      )
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'param' => 'username',
            'help' => pht(
              'The username for whom transactions will be rolled back.'
            ),
          ),
          array(
            'name' => 'user-phid',
            'param' => 'PHID',
            'help' => pht(
              'The username for whom transactions will be rolled back.'
            ),
          ),
          array(
            'name' => 'dryrun',
            'short' => 'r',
            'help' => pht(
              'Execute a dry run, changes will not be writen back to the database.'
            ),
          ),
          array(
            'name' => 'delete',
            'help' => pht(
              'After reverting transactions, delete the reverted transaction records.'
            ),
          ),
          array(
            'name' => 'offset',
            'param' => 'OFFSET',
            'default' => 0,
            'help' => pht(
              'Skip OFFSET rows before processing the remaining tranactions.'
            ),
          ),
          array(
            'name' => 'limit',
            'param' => 'LIMIT',
            'default' => 10000,
            'help' => pht(
              'Limit the number of transaction rows to process. Default: 10000'
            ),
          ),
          array(
            'name' => 'verbose',
            'help' => pht('Show verbose output.'),
          ),
        )
      );
  }

  protected function getTargetUser($args) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $query = new PhabricatorPeopleQuery();
    $query->setViewer($viewer);
    if ($args->getArg('user')) {
      $query->withUsernames(array($args->getArg('user')));
    } else if ($args->getArg('user-phid')) {
      $query->withPHIDs(array($args->getArg('user-phid')));
    } else {
      throw new PhutilArgumentUsageException(
        pht('You must provide either --user or --user-phid')
      );
    }

    $targetUser = $query->executeOne();

    if (!$targetUser) {
      throw new PhutilArgumentUsageException(
        pht('The specified username / userPHID was not found')
      );
    }
    if ($targetUser->getIsAdmin()) {
      throw new Exception(
        pht('You cannot roll back the activity of a privileged user.'));
    }

    if (!$targetUser->getIsDisabled()) {
      throw new Exception(
        pht('You must disable the user before rolling back their activity'));
    }
    return $targetUser;
  }

  public function execute(PhutilArgumentParser $args)
  {
    try {
      $console = PhutilConsole::getConsole();
      $viewer = PhabricatorUser::getOmnipotentUser();
      $targetUser = $this->getTargetUser($args);
      $userPHID = $targetUser->getPHID();
      clog::$show_verbose = $args->getArg('verbose');
      $this->dryrun = $args->getArg('dryrun');
      $this->deleteTransactions = $args->getArg('delete');
      //clog::log($this->dryrun);
      $offset = (int)$args->getArg('offset');
      $limit = (int)$args->getArg('limit');
    } catch(Exception $e) {
      clog::error($e->getMessage());
      clog::log('Aborting');
      return false;
    }
    $columns = [
      "t.phid",
      "t.objectPHID",
      "t.dateCreated",
      "t.transactionType",
      "t.oldValue",
      "t.newValue",
      "t.metadata",
      "c.content as commentText",
    ];

    $objectQuery = new ManiphestTaskQuery();
    $objectQuery->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true);
    $transactionClass = new ManiphestTransaction();
    $commentTable = new ManiphestTransactionComment();

    $connection = id($transactionClass)->establishConnection('r');
    $columns = implode(', ', $columns);

    $sql = "SELECT
      $columns
      FROM %R t LEFT JOIN %R c
      ON c.transactionPHID = t.phid
      AND c.viewPolicy = t.viewPolicy
      WHERE t.authorPHID = %s
      AND t.viewPolicy = %s
      ORDER BY t.dateModified DESC
      LIMIT %d, %d";

    $transactions = queryfx_all(
      $connection,
      $sql,
      $transactionClass,
      $commentTable,
      $userPHID,
      'public',
      $offset,
      $limit
    );

    if (!$transactions) {
      $transactions = array();
    }

    $result = array();
    foreach ($transactions as $trns) {
      $oldValue = $trns['oldValue'];
      $newValue = $trns['newValue'];

      $trns['oldValue'] = $this->unescapeAndDecode($trns['oldValue']);
      $trns['newValue'] = $this->unescapeAndDecode($trns['newValue']);
      $trns['metadata'] = $this->unescapeAndDecode($trns['metadata']);

      $objectPHID = $trns['objectPHID'];
      if (!isset($result[$objectPHID]['fields'])) {
        $result[$objectPHID]['fields'] = array();
      }
      $result[$objectPHID]['transactions'][] = $trns;
    }
    $phids = array_keys($result);

    $objects = id($objectQuery)
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    foreach ($objects as $obj) {
      $objectPHID = $obj->getPHID();
      $resultObject = $result[$objectPHID];
      $data = $this->getFieldValues($obj);
      $data = $this->loadCustomFields($obj, $data);
      $resultObject['fields'] = $data;
      $resultObject = $this->reverseTransactions($resultObject, $obj);
      $result[$objectPHID] = $resultObject;
    }
    return 0;
  }

  public function projects(PhutilArgumentParser $args)
  {
    throw new Exception('Not yet implemented');

    $targetUser = $this->getTargetUser($args);
    $userPHID = $targetUser->getPHID();

    $offset = 0;
    $limit = 1000;

    $columns = [
      "t.phid",
      "t.objectPHID",
      "t.dateCreated",
      "t.transactionType",
      "t.oldValue",
      "t.newValue",
      "t.metadata",
      "NULL as commentText",
    ];

    $objectQuery = new PhabricatorProjectQuery();
    $transactionClass = new PhabricatorProjectTransaction();

    $connection = id($transactionClass)->establishConnection('r');
    $columns = implode(', ', $columns);

    $sql = "SELECT
      $columns
      FROM %R t
      WHERE t.authorPHID = %s
      AND t.viewPolicy = %s
      ORDER BY t.dateModified DESC
      LIMIT %d, %d";

    $transactions = queryfx_all(
      $connection,
      $sql,
      $transactionClass,
      $userPHID,
      'public',
      $offset,
      $limit
    );

    if (!$transactions) {
      $transactions = array();
    }

    $result = array();
    foreach ($transactions as $trns) {
      $oldValue = $trns['oldValue'];
      $newValue = $trns['newValue'];

      $trns['oldValue'] = $this->unescapeAndDecode($trns['oldValue']);
      $trns['newValue'] = $this->unescapeAndDecode($trns['newValue']);
      $trns['metadata'] = $this->unescapeAndDecode($trns['metadata']);

      $objectPHID = $trns['objectPHID'];
      if (!isset($result[$objectPHID]['fields'])) {
        $result[$objectPHID]['fields'] = array();
      }
      $result[$objectPHID]['transactions'][] = $trns;
    }
    $phids = array_keys($result);

    $objects = id($objectQuery)
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    foreach ($objects as $obj) {
      $objectPHID = $obj->getPHID();
      $resultObject = $result[$objectPHID];
      $data = $this->getFieldValues($obj);
      $data = $this->loadCustomFields($obj, $data);
      $resultObject['fields'] = $data;
      $resultObject = $this->reverseTransactions($resultObject, $obj);
      $result[$objectPHID] = $resultObject;
    }
    return 0;
  }

  protected function loadCustomFields($object, $data)
  {
    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_CONDUIT
    );

    foreach ($field_list->getFields() as $field) {
      $data[$field->getFieldKey()] = $field->getHeraldFieldValue();
    }
    return $data;
  }

  protected function unescapeAndDecode($val)
  {
    $origVal = $val;
    if ($val[0] == '[' || $val[0] == '{') {
      try {
        $val = phutil_json_decode($val);
      } catch (PhutilJSONParserException $err) {
        $val = $origVal;
      }
    }
    if (is_string($val) && strlen($val)) {
      $val = trim($val, '"');
    }
    return $val;
  }

  protected function reverseTransactions($data, $instance)
  {
    $console = PhutilConsole::getConsole();
    $edited=false;
    $done = array();
    $skipped = array();
    $t = $instance->getMonogram();

    clog::log("Found <fg:yellow>".count($data['transactions'])."</fg> transactions on __<fg:blue>$t</fg>__");
    foreach ($data['transactions'] as $trns) {
      $oldValue = $this->normalizeValue($trns['oldValue']);
      $newValue = $this->normalizeValue($trns['newValue']);
      $type = $trns['transactionType'];
      if (isset($this->fieldMap[$type])) {
        $field = $this->fieldMap[$type];
      } else if (isset($data['fields'][$type])) {
        $field = $type;
      } else if ($type == 'core:customfield' && isset($metadata)) {
        $field = $data['metadata']['customfield:key'];
      } else {
        $field = null;
        clog::verbose("no field for type", $type);
      }

      if (isset($data['fields'][$field])) {
        $dbValue = $this->normalizeValue($data['fields'][$field]);
        //clog::log(array("fld" => $field, "old" => $oldValue, "new"=> $newValue, "obj" => $dbValue));
        if ($newValue === $dbValue) {
          $data['fields'][$field] = $oldValue;
          if ($field == 'subscribers') {
            $this->editSubscribers($instance->getPHID(), $newValue, $oldValue);
            $done[] = $trns;
          } else {
            // fields with unconventionally-named setters are mapped to a
            // method name by looking them up in the methodMap array
            if (isset($this->methodMap[$field])) {
              $method = $this->methodMap[$field];
            } else {
              $method = "set".ucfirst($field);
            }
            // dynamically call the setter method for the edited field
            // e.g $task->setPriority($oldPriority)
            call_user_func_array(array($instance, $method), array($oldValue));
            $done[] = $trns;
          }
        } else {
          $skipped[] = $trns;
          $console->writeErr(
            " <fg:red>*</fg> Edit conflict: __<fg:yellow>%s</fg>__ was edited by someone else.\n",
            $field);
        }
      } else if ($field === null) {
        // ignore this transaction.
      } else if ($field == 'comment') {
        $done[] = $trns;
      } else {
        clog::error("Unknown type: $type");
        clog::verbose($data);
      }
    }
    if (count($done)) {
      if (!$this->dryrun) {
        try {
          clog::log('Saving task');
          $instance->save();
        } catch (AphrontQueryException $e) {
          clog::error($e->getMessage());
        }
        try {
          clog::log('Saving edges');
          $this->edgeEditor->save();
        } catch (Exception $e) {
          clog::error($e->getMessage());
        }
      }
      $doneCount = count($done);
      $skipCount = count($skipped);
      clog::log("Processed: <fg:green>$doneCount</fg>, Skipped: <fg:yellow>$skipCount</fg>");
      $toDelete = array();
      foreach ($done as $trns) {
        clog::verbose(" <fg:green>*</fg> ". $trns['phid']);
        clog::verbose($trns);
        $toDelete[] = $trns['phid'];
      }
      clog::verbose(array('Skipped', $skipped));

      if ( !empty($toDelete) && $this->deleteTransactions) {
        if ($this->dryrun) {
          clog::verbose('Dry run, not deleting transactions');
        } else {
          clog::log('Deleting transactions.');
          $transactionClass = new ManiphestTransaction();
          $connection = id($transactionClass)->establishConnection('r');
          queryfx($connection,
            "DELETE FROM %R WHERE phid IN (%Ls)",
            $transactionClass,
            $toDelete);
        }
      }

    }
    clog::log("----");
    return $data;
  }

  protected function normalizeValue($val) {
    if (is_array($val)) {
      if (count($val) == 1 && array_key_exists('raw', $val)) {
        return $val['raw'];
      }
      if (phutil_is_natural_list($val) && count($val) > 1) {
        sort($val);
      }
    }
    return $val;
  }

  protected function getFieldValues($obj)
  {
    $closed_epoch = $obj->getClosedEpoch();
    if ($closed_epoch !== null) {
      $closed_epoch = (int)$closed_epoch;
    }

    $fields = array(
      'title' => $obj->getTitle(),
      'description' => $obj->getDescription(),
      'authorPHID' => $obj->getAuthorPHID(),
      'ownerPHID' => $obj->getOwnerPHID(),
      'status' => $obj->getStatus(),
      'priority' => $obj->getPriority(),
      'points' => $obj->getPoints(),
      'subtype' => $obj->getSubtype(),
      'closerPHID' => $obj->getCloserPHID(),
      'dateClosed' => $closed_epoch,
      'viewPolicy' => $obj->getViewPolicy(),
      'editPolicy' => $obj->getEditPolicy(),
      'subscribers' => $obj->getSubscriberPHIDs(),
      'projects' => $obj->getProjectPHIDs(),
    );
    return $fields;
  }

  protected function editSubscribers($obj, $oldSubscribers, $newSubscribers) {
    $edge_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;
    $this->editEdges($edge_type, $obj, $oldSubscribers, $newSubscribers);
  }

  protected function editEdges($edge_type, $srcPHID, $oldTargets, $newTargets) {

    foreach ($oldTargets as $target) {
      if (!in_array($target, $newTargets)) {
        $this->edgeEditor->removeEdge($srcPHID, $edge_type, $target);
      }
    }
    foreach ($newTargets as $target) {
      if (!in_array($target, $oldTargets)) {
        $this->edgeEditor->addEdge($srcPHID, $edge_type, $target);
      }
    }
  }
}

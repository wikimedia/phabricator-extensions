<?php

class ProjectMetrics {
  protected $tasks = [];

  protected $request;
  protected $project;
  protected $metrics;

  public function __construct($request, $project) {
    $this->request = $request;
    $this->project = $project;
  }

  public function getMetrics() {
    return $this->metrics;
  }

  public function getMetric($name) {
    return $this->metrics[$name];
  }

  protected function getViewer() {
    return $this->request->getViewer();
  }

  public function getProjectPHID() {
    return $this->project->getPHID();
  }

  public function getProject() {
    return $this->project;
  }

  public function getProjectColumns($status=null) {
    if (!isset($status)){
      $status = PhabricatorProjectColumn::STATUS_ACTIVE;
    }
    $columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($this->getViewer())
        ->withProjectPHIDs([$this->getProjectPHID()])
        ->withStatuses([$status])
        ->execute();

    return $columns;
  }

  public function getAssignmentMetrics($projectPHIDs) {
    $query = new ManiphestTaskQuery();
    $query->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_OR,
        $projectPHIDs);
    $tasks = $query->execute();

    $task_owner_phids = mpull($tasks, 'getOwnerPHID');
    $owner_tasks = [];
    foreach($task_owner_phids as $task=>$owner) {
      if (!$owner) {}
      if (!isset($owner_tasks[$owner])) {
        $owner_tasks[$owner] = 1;
      } else {
        $owner_tasks[$owner] += 1;
      }
    }
    //phlog($owner_tasks);
    return $owner_tasks;
  }

  public function getBoardContainerPHIDs() {
    $project = $this->getProject();
    $viewer = $this->getViewer();

    $container_phids = array($project->getPHID());
    if ($project->getHasSubprojects() || $project->getHasMilestones()) {
      $descendants = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withAncestorProjectPHIDs($container_phids)
        ->execute();
      foreach ($descendants as $descendant) {
        $container_phids[] = $descendant->getPHID();
      }
    }

    return $container_phids;
  }

  public function computeMetrics() {

    $container_phids = $this->getBoardContainerPHIDs();
    $project_phid = $this->getProjectPHID();
    $columns = $this->getProjectColumns();
    $columns = msort($columns, 'getSequence');
    $tasks_by_column = [];

    // initialize structure for workboard columns
    foreach($columns as $col) {
      $phid = $col->getPHID();
      $tasks_by_column[$phid] = [
        "name" => $col->getDisplayName(),
        "tasks" => [],
        "ages" => []
      ];
    }

    // start and end date
    $start = $this->request->getInt('startdate');
    $end = $this->request->getInt('enddate');

    // completed tasks
    $query = new ManiphestTaskQuery();
    $query->setViewer($this->getViewer())
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($container_phids))
      ->withStatuses([ManiphestTaskStatus::STATUS_CLOSED_RESOLVED])
      ->withClosedEpochBetween($start, $end);
    $completed = $query->execute();
    $this->metrics['completed'] = pht(' %d ', count($completed));

    // open tasks
    $query = new ManiphestTaskQuery();
    $query->setViewer($this->getViewer())
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($container_phids))
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants());
      //->withColumnPHIDs(mpull($columns, 'getPHID'))
    $tasks = $query->execute();
    $task_phids = mpull($tasks, 'getPHID');
    $task_owner_phids = mpull($tasks, 'getOwnerPHID');
    $owner_tasks = [];

    // find the task count per owner
    foreach($task_owner_phids as $task=>$owner) {
      if (!$owner) {
        continue;
      }
      if (!isset($owner_tasks[$owner])) {
        $owner_tasks[$owner] = 1;
      } else {
        $owner_tasks[$owner] += 1;
      }
    }
    $this->metrics['tasks_by_owner'] = $owner_tasks;

    // get workboard columns
    $engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($this->getViewer())
      ->setBoardPHIDs([$this->getProjectPHID()])
      ->setObjectPHIDs($task_phids)
      ->executeLayout();

    $now = new DateTime();
    $task_ages = [];

    // compute ages and columns for each task
    foreach($tasks as $task){
      $phid = $task->getPHID();
      $date_created = $task->getDateCreated();
      $ago = new DateTime();
      $ago->setTimestamp($date_created);
      $diff = $now->diff($ago);
      $task_ages[] = $diff->days;

      $task_columns = $engine->getObjectColumns(
        $project_phid,
        $phid);
      // count tasks in each column
      foreach($task_columns as $col) {
        $col_phid = $col->getPHID();
        $tasks_by_column[$col_phid]['tasks'][] = $task;
        $tasks_by_column[$col_phid]['ages'][] = $diff->days;
      }
    }
    $max_age = max($task_ages);
    $this->metrics['max_age'] = $max_age;
    foreach ($tasks_by_column as $col_phid=>$col) {
      $tasks_by_column[$col_phid]['stats'] =
        $this->computeAgeStats($col['ages']);

    }
    //phlog($tasks_by_column);
    $this->metrics['columns'] = $tasks_by_column;

    $this->metrics['age'] =
      $this->computeAgeStats($task_ages);
    $this->metrics['histogram'] =
      $this->makeAgeHistogram($task_ages);
    //phlog($this->metrics['age']);
  }

  public function makeHistogramBuckets($count, $interval) {
    $res = [];
    $i=0;
    while( count($res) < $count) {
      $i += $interval;
      $res[$i] = 0;
    }
    phlog($res);
    return $res;
  }
  public function makeAgeHistogram($ages, $buckets=null) {
    if ($buckets == null) {
      $buckets = $this->makeHistogramBuckets(6, 7);
    }
    sort($ages);
    reset($buckets);
    $current = key($buckets);
    foreach($ages as $age) {
      if ($age <= $current) {
        $buckets[$current]++;
      } else {
        $next = next($buckets);
        if ($next === false) {
          break;
        } else {
          $current = key($buckets);
        }
        $buckets[$current]++;
      }
    }
    return $buckets;
  }

  public function computeAgeStats($task_ages) {
    if (empty($task_ages)) {
      return [
        'count' => 0,
        'min'=> 0,
        'mean'=> 0,
        'median'=> 0,
        'max'=> 0
      ];
    }
    sort($task_ages, SORT_NUMERIC);
    $original_count = count($task_ages);
    $task_ages = array_values(array_unique($task_ages));
    $count = count($task_ages);
    $mean_age = array_sum($task_ages) / $count;
    $mid = (int)floor($count / 2);
    if ($count > 4) {
      if ($count & 1) { //odd
        $median_age = $task_ages[$mid];
      } else { // even
        $median_age = ($task_ages[$mid] + $task_ages[$mid+1]) / 2;
      }
    } else {
      $median_age = $mean_age;
    }
    $max_age = $task_ages[$count-1];
    $min_age = $task_ages[0];
    //phlog($task_ages);
    return [
      'count' => $original_count,
      'min'=> round($min_age),
      'mean'=> round($mean_age),
      'median'=> round($median_age),
      'max'=> round($max_age)
    ];

  }

  public function getColumnTransactionsForProject($projectPHIDs) {
    $storage = new ManiphestTransaction();
    $conn = $storage->establishConnection('r');
    $rows = queryfx_all(
      $conn,
      'SELECT
        trns.objectPHID,
        trns.authorPHID,
        JSON_VALUE(trns.newValue, "$[0].boardPHID") as projectPHID,
        JSON_VALUE(trns.newValue, "$[0].columnPHID") as toColumnPHID,
        JSON_VALUE(trns.newValue, "$[0].fromColumnPHIDs.*") as fromColumnPHID
      FROM %T trns
      WHERE
        transactionType="core:columns"
      AND
        JSON_VALUE(trns.newValue, "$[0].boardPHID") IN (%Ls)
      GROUP BY
        objectPHID
      ORDER BY
        dateModified',
      $storage->getTableName(),
      $projectPHIDs);
    return $rows;
}



}

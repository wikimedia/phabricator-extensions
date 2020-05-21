<?php

class GerritPatchesCustomField
 extends ManiphestCustomField
{
  private $task_override = false; //"140007";

  public function getFieldKey() {
    return 'wmf:patches';
  }

  public function getModernFieldKey() {
    return 'gerrit.patches';
  }

  public function getFieldName() {
    return pht('Gerrit Patches');
  }
  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return false;
  }

  public function shouldAppearInConduitTransactions() {
    return false;
  }

  public function shouldAppearInConduitDictionary() {
    return false;
  }

  public function shouldAppearInEditEngine() {
    return false;
  }

  public function shouldAppearInEditView() {
    return false;
  }

  public function shouldAppearInPropertyView() {

    $taskid = $this->getTaskId();
    if (!$taskid) {
      return false;
    }

    return !empty($this->getGerritPatchesForTask($taskid));
  }

  protected function getTaskId() {
    if ($this->task_override &&
    PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      return $this->task_override;
    } else {
      $task = $this->getObject();
      if (!$task) {
        return false;
      }
      return $task->getID();
    }
  }

  public function renderPropertyViewLabel() {
    pht("Related Changes in Gerrit:");
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function renderPropertyViewValue(array $handles) {
    $taskid = $this->getTaskId();
    return $this->getGerritPatchesForTask($taskid);
  }

  private function format_thousands($val) {
    return phutil_format_units_generic(
      $val,
      array(1000, 1000, 1000),
      array('', 'K', 'M'),
      $precision = 0);
  }

  private function getGerritPatchesForTask($taskid) {
    $cache = PhabricatorCaches::getMutableCache();
    $cachekey = 'gerrit:patches:'.$taskid;
    $changes = $cache->getKey($cachekey, null);
    $gerrit_url = "https://gerrit.wikimedia.org/r/changes/?q=bug:T$taskid&o=LABELS";
    $gerrit_search = "https://gerrit.wikimedia.org/r/#/q/bug:T$taskid";
    if ($changes === "loading") {
      return ''; //data is being loaded by another request / process, avoid hammering gerrit
    } else if ($changes == null) {
      // attempt to avoid thundering herd:
      // lock the cache by setting it to "loading" while https request is pending,
      // timeout the cache entry in 5 seconds
      $cache->setKey($cachekey, "loading", 5);
      // request the list of changes from gerrit with a 2 second timeout
      $changes = HTTPSFuture::loadContent(
       $gerrit_url,
        2);
      if ($changes === false) {
        $cache->setKey($cachekey, "", 10);
        // http fetch failed, don't cache or display anything.
        return '';
      }
      if ($changes === '') {
        return '';
      }
      $changes = substr($changes, 4);
      $cache->setKey($cachekey, $changes, 600);
    }

    $changes = json_decode($changes, true);
    $rows = array();
    $view = new PHUIStatusListView();
    Javelin::initBehavior('phabricator-tooltips');
    foreach($changes as $change) {
      $url = "https://gerrit.wikimedia.org/r/c/". $change['_number'];
      $link = phutil_tag('a', array('href'=>$url), $change['subject']);
      $status = $change['status'];
      if ($status == 'MERGED') {
        $icon = 'fa-code-fork black';
        $title = pht('Merged');
      } else if ($status == 'ABANDONED') {
        $icon ='fa-times-circle red';
        $title = pht('Abandoned');
      } else {
        if (isset($change['labels']['Code-Review']['value'])
          && $change['labels']['Code-Review']['value'] < 0) {
            $icon = 'fa-hand-stop-o red';
            $title = pht('Blocked on Code Review');
        } else if ($change['has_review_started']) {
          $icon = 'fa-clock-o blue';
          $title = pht('Code Review Started');
        } else {
          $icon = 'fa-plus-circle blue';
          $title = ucfirst($status);
        }
      }
      $icons = [];

      if (isset($change['unresolved_comment_count']) &&
        $change['unresolved_comment_count'] > 0) {
        $icons[] = id(new PHUIIconView())
          ->setIcon('fa-comments grey')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Unresolved code review comments: %d',
               $change['unresolved_comment_count']),
              'size' => 300,
            ));
            $icons[] = ' ';
      }
      $icons[] = id(new PHUIIconView())
        ->setIcon($icon)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $title,
            'size' => 240,
          ));

      $insertions = $this->format_thousands($change['insertions']);
      $deletions = $this->format_thousands($change['deletions']);
      $project_url = "https://gerrit.wikimedia.org/r/plugins/gitiles/"
        . $change['project'];
      $branch_url = $project_url . '/+/' . $change['branch'];
      $project = phutil_tag('a', [
         'href' => $project_url,
         'target'=>'_blank'],
         $change['project']);
      $branch =  phutil_tag('a', [
        'href' => $branch_url,
      'target'=>'_blank'],
       $change['branch']);
      $rows[] = array(
        $icons,
        $project,
        $branch,
        [ phutil_tag('span', [
          'style' => 'color:green;',
          'title' => pht('%s Line(s) added', $insertions)], '+'.$insertions) ,
        phutil_tag('span', [
          'style' => 'color:red;',
          'title' => pht('%s Line(s) removed', $deletions)],' -' . $deletions)],
        $link,
      );
    }
      $changes_table = id(new AphrontTableView($rows))
      ->setHeaders([
        '',
        'Project',
        'Branch',
        'Lines +/-',
        'Subject'])
      ->setNoDataString(pht('This task has no related gerrit patches.'))
      ->setColumnClasses(
        array(
          'right',
          'left',
          'left',
          'n',
          'wide object-link',
        ))
      ->setColumnVisibility(
        array(
          true,
          true,
          true,
          true,
          true,
        ))
      ->setDeviceVisibility(
        array(
          true,
          true,
          false,
          false,
          true,
        ));

    if (empty($rows)) {
      return '';
    }
    $search_link = phutil_tag('a', ['href'=>$gerrit_search],
      pht('Customize query in gerrit')
    );
    if (count($changes) > 10) {
      require_celerity_resource('wikimedia-extensions-css');
      $table_id = celerity_generate_unique_node_id();
      $link_id = celerity_generate_unique_node_id();
      $changes_div = phutil_tag('div', [
        "id"=>$table_id,
        "class" => "gerrit-patches-hidden",
      ], $changes_table);

      $toggle_link = javelin_tag('a', [
          "id"=>$link_id,
          "class" => "button button-grey",
          "sigil"=> 'jx-toggle-class',
          "meta"=> [
            "map" => [
              $table_id => "gerrit-patches-expanded",
              $link_id => 'gerrit-button-hidden',
            ]
          ]
            ], pht('Show related patches'));

          return array($changes_div, $toggle_link, " ", $search_link );
    }
    return array($changes_table, $search_link );

  }

 }

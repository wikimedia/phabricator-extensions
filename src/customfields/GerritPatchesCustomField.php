<?php

class GerritPatchesCustomField
 extends ManiphestCustomField
 {

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
    $task = $this->getObject();
    if (!$task) {
      return false;
    }
    return !empty($this->getGerritPatchesForTask($task->getId()));
  }

  public function renderPropertyViewLabel() {
    return pht("Related Gerrit Patches:");
  }
  public function getStyleForPropertyView() {
    return 'block';
  }

  public function renderPropertyViewValue(array $handles) {
    $obj = $this->getObject();
    $taskid = $obj->getId();
    return $this->getGerritPatchesForTask($taskid);
  }

  private function getGerritPatchesForTask($taskid) {
    $cache = PhabricatorCaches::getMutableCache();
    $cachekey = 'gerrit:patches:'.$taskid;
    $changes = $cache->getKey($cachekey, null);
    if ($changes === "loading") {
      return ''; //data is being loaded by another request / process, avoid hammering gerrit
    } else if ($changes == null) {
      // attempt to avoid thundering herd:
      // lock the cache by setting it to 1 while https request is pending, timeout in 10 seconds
      $cache->setKey($cachekey, "loading", 10);
      $changes = HTTPSFuture::loadContent(
        'https://gerrit.wikimedia.org/r/changes/?q=bug:T'.$taskid.'&o=LABELS');
      if ($changes === false) {
        $cache->setKey($cachekey, "", 10);
        // http fetch failed, don't cache or display anything.
        return '';
      }
      $changes = substr($changes, 4);
      $cache->setKey($cachekey, $changes, 600);
    }

    $changes = json_decode($changes, true);
    $items = array();
    $view = new PHUIStatusListView();
    foreach($changes as $change) {
      $url = "https://gerrit.wikimedia.org/r/c/".$change['_number'];
      $status = $change['status'];
      $item = id(new PHUIStatusItemView())
        ->setTarget($change['project'] . ' : ' . $change['branch'])
        ->setNote(phutil_tag('a', array('href'=>$url), $change['subject']));

      if ($status == 'MERGED') {
        $item->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green', pht('Merged'));
      } else if ($status == 'ABANDONED') {
        $item->setIcon(PHUIStatusItemView::ICON_REJECT, 'red', pht('Abandoned'));
      } else {
        if (isset($change['labels']['Code-Review']['value'])
          && $change['labels']['Code-Review']['value']  < 0) {
            $item->setIcon(PHUIStatusItemView::ICON_MINUS, 'red', pht('Blocked on Code Review'));
        } else if ($change['has_review_started']) {
          $item->setIcon(PHUIStatusItemView::ICON_CLOCK, 'blue', pht('Code Review Started'));
        } else {
          $item->setIcon(PHUIStatusItemView::ICON_ADD, 'blue', pht('New'));
        }
      }

      $project = $change['project'];
      $branch = $change['branch'];
      $view->addItem($item);
      $items[] = $item;
    }
    if (empty($items)) {
      return '';
    }
    return $view;
  }

 }
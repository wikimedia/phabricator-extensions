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
    $changes = $cache->getKey($cachekey);
    if ($changes == null) {
      $changes = HTTPSFuture::loadContent(
        'https://gerrit.wikimedia.org/r/changes/?q=bug:T'.$taskid);
      if ($changes === false) {
        // http fetch failed, don't cache or display anything.
        return '';
      }
      $changes = substr($changes, 4);
      $cache->setKey($cachekey, $changes, 3600);
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
        if ($change['has_review_started']) {
          $item->setIcon(PHUIStatusItemView::ICON_CLOCK, 'blue', pht('Awaiting Review'));
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
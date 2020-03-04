<?php

final class PhabricatorProjectColumnUpdatedOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'updated';

  public function getDisplayName() {
    return pht('Sort by Last Update');
  }

  protected function newMenuIconIcon() {
    return 'fa-comments-o';
  }

  public function getHasHeaders() {
    return false;
  }

  public function getCanReorder() {
    return false;
  }

  public function getMenuOrder() {
    return 5001;
  }

  protected function newSortVectorForObject($object) {
    return array(
      -1 * (int)$object->getDateModified(),
      -1 * (int)$object->getID(),
    );
  }

}

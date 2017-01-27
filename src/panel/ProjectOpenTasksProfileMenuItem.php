<?php

final class ProjectOpenTasksProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'custom.open-tasks';

  public function getMenuItemTypeIcon() {
    return 'fa-anchor';
  }

  public function getMenuItemTypeName() {
    return pht('Link to Open Tasks');
  }

  public function canAddToObject($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return pht('Open Tasks');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $object = $config->getProfileObject();

    $href = '/maniphest/?project='.$object->getPHID().'&statuses=open()#R';

    $item = $this->newItem()
      ->setHref($href)
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor');

    return array(
      $item,
    );
  }

}
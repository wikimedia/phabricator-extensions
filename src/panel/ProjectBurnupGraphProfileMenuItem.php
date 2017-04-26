<?php

final class ProjectBurnupGraphProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'custom.burnup-graph';

  public function getMenuItemTypeIcon() {
    return 'fa-anchor';
  }

  public function getMenuItemTypeName() {
    return pht('Link to Burnup Graph');
  }

  public function canAddToObject($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return pht('Burnup Graph');
  }

  private function getLinkTooltip(
    PhabricatorProfileMenuItemConfiguration $config) {
    return pht('Number of open tasks over time');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $object = $config->getProfileObject();

    $href = '/maniphest/report/burn/?project='.$object->getPHID();

    $item = $this->newItem()
      ->setHref($href)
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor')
      ->setTooltip($this->getLinkTooltip($config));

    return array(
      $item,
    );
  }

}

<?php

final class ProjectOpenTasksProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'custom.open-tasks';

  public function getPanelTypeIcon() {
    return 'fa-anchor';
  }

  public function getPanelTypeName() {
    return pht('Link to Open Tasks');
  }

  public function canAddToObject($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    return pht('Open Tasks');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $object = $config->getProfileObject();

    $href = '/maniphest/?project='.$object->getPHID().'#R';

    $item = $this->newItem()
      ->setHref($href)
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor');

    return array(
      $item,
    );
  }

}

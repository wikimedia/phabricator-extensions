<?php

final class ProjectBurnupGraphProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'custom.burnup-graph';

  public function getMenuItemTypeIcon() {
    return 'fa-line-chart';
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

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $object = $config->getProfileObject();

    $uri = '/maniphest/report/burn/?project='.$object->getPHID();

    $item = $this->newItemView()
      ->setUri($uri)
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor')
      ->setTooltip($this->getLinkTooltip($config));

    return array(
      $item,
    );
  }


  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'This adds a link to search maniphest for open tasks which are '.
            "tagged with this project.\n\n".
            "NOTE: This feature is provided by a Wikimedia-maintained ".
            'extension, ProjectBurnupGraphProfileMenuItem. See '.
            '{rPHEX} for the source.')),
    );
  }

}

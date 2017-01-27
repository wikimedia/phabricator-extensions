<?php
class PhabricatorMilestoneNavProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.milestonenav';

  public function getMenuItemTypeName() {
    return pht('Milestone Navigation Links');
  }
  public function getMenuItemTypeIcon() {
    return 'fa-step-forward';
  }

  private function getDefaultName() {
    return pht('Series Navigation');
  }

  public function canAddToObject($object) {
    return false;
  }

  public function shouldEnableForObject($object) {
    // Only render this element for milestones.
    if ($object instanceof PhabricatorProject && $object->isMilestone()) {
      return true;
    }
    return false;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'This panel shows navigation links to other milestones in the '.
            'same series.'
            )),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $project = $config->getProfileObject();
    $milestone_num = $project->getMilestoneNumber();
    $parent_phid = $project->getParentProjectPHID();
    $parent_id = $project->getParentProject()->getID();

    $milestones = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withParentProjectPHIDs(array($parent_phid))
      ->withIsMilestone(true)
      ->withMilestoneNumberBetween($milestone_num-1, $milestone_num+1)
      ->setOrderVector(array('-milestoneNumber', 'id'))
      ->execute();

    $parent = $this->newItem();
    $parent->setName('Series')
      ->setIcon('fa-arrows-h')
      ->setHref("/project/subprojects/{$parent_id}/")
      ->setType(PHUIListItemView::TYPE_LINK);

    $items = array($parent);

    foreach($milestones as $milestone) {
      $num = $milestone->getMilestoneNumber();

      $uri = $milestone->getURI();
      $name = $milestone->getName();

      if ($num < $milestone_num) {
        $icon = 'fa-arrow-left';
        $name = pht('Previous: %s', $name);
      } else if ($num > $milestone_num) {
        $icon = 'fa-arrow-right';
        $name = pht('Next: %s', $name);
      } else {
        continue;
      }

      $items[] = $this->newItem()
        ->setIcon($icon)
        ->setHref($uri)
        ->setName($name);
    }

    if (count($items) < 2) {
      return array();
    }

    return  $items;
  }

  private function renderError($message) {
    $message = phutil_tag(
      'div',
      array(
        'class' => 'phui-profile-menu-error',
      ),
      $message);

    $item = $this->newItem()
      ->appendChild($message);

    return array(
      $item,
    );
  }

}

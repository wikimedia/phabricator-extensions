<?php
class PhabricatorMilestoneNavProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.milestonenav';

  public function getPanelTypeName() {
    return pht('Milestone Navigation');
  }

  private function getDefaultName() {
    return pht('Milestone Series');
  }

  public function shouldEnableForObject($object) {
    // Only render this element for milestones.
    if (!$object->isMilestone()) {
      return false;
    }

    return true;
  }

  public function getDisplayName(PhabricatorProfilePanelConfiguration $config) {
    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
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
    PhabricatorProfilePanelConfiguration $config) {
    $viewer = $this->getViewer();
    $project = $config->getProfileObject();
    $milestone_num = $project->getMilestoneNumber();
    $parent_phid = $project->getParentProjectPHID();
    $milestones = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withParentProjectPHIDs(array($parent_phid))
      ->needImages(true)
      ->withIsMilestone(true)
      ->withMilestoneNumberBetween($milestone_num-1, $milestone_num+1)
      ->withStatuses(
        array(
          PhabricatorProjectStatus::STATUS_ACTIVE,
        ))
      ->setOrderVector(array('-milestoneNumber', 'id'))
      ->execute();
    $actions = new PhabricatorActionListView();
    $actions->setViewer($this->getViewer());
    $items = array();
    foreach($milestones as $milestone) {
      $num = $milestone->getMilestoneNumber();
      if ($num == $milestone_num) {
        continue;
      }

      $uri = $milestone->getURI();
      $name = $milestone->getName();

      if ($num < $milestone_num) {
        $icon = 'fa-arrow-left';
        $name = pht('Previous: %s', $name);
      } else if ($num > $milestone_num) {
        $icon = 'fa-arrow-right';
        $name = pht('Next: %s', $name);
      }

      $actions->addAction(
        id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName($name)
        ->setIcon($icon)
      );
      $items[] = $this->newItem()
        ->setIcon($icon)
        ->setHref($uri)
        ->setName($name);
    }
    if (count($items) == 1) {
      return $items;
    }

    $item = $this->newItem()
      ->setType(PHUIListItemView::TYPE_BUTTON)
      ->setName('Series Navigation')
      ->setIndented(true)
      ->setIcon('fa-arrows-h')
      ->setHref('#')
      ->setDropdownMenu($actions);

    return array($item);
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

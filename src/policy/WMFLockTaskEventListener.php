<?php

final class WMFEscalateTaskEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
      break;
    }
  }

  private function handleActionEvent($event) {
    $viewer = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !($object instanceof ManiphestTask)) {
      return;
    }

    // Figure out if the item will be enabled or disabled in the UI.
    // This assumes any logged-in user can escalate tasks,
    $can_lock = $viewer->isLoggedIn();

    // don't show the link if it's already locked.
    $is_locked = !WMFSecurityPolicy::isTaskPublic($object);

    if ($is_locked) {
      return;
    }

    $lock_action = id(new PhabricatorActionView())
      ->setHref('/wmf/escalate-task/'.$object->getID().'/')
      ->setIcon('fa-eye-slash')
      ->setName(pht('Protect as security issue'))
      ->setWorkflow(true)
      ->setDisabled(!$can_lock);

    $actions = $event->getValue('actions');
    $actions[] = $lock_action;
    $event->setValue('actions', $actions);
  }

}

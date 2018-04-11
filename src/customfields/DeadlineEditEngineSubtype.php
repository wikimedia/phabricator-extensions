<?php

class DeadlineEditEngineSubtype extends PhabricatorEditEngineSubtype {


  public function getKey() {
    return 'deadline';
  }

  public function hasTagView() {
    return true;
  }

  public function newTagView($viewer) {
    $deadline = $this->getDeadline($viewer);
    $due = $deadline->getValueForStorage();
    if ($deadline && $due) {
      $text = phabricator_date($due, $viewer);
      $now = time();
      $day = 86400;
      if ($due < $now) {
        $this->setColor('fire');
      } else if ($due < $now + ($day * 2.5)) {
        $this->setColor('orange');
      } else if ($due < $now + ($day * 7)) {
        $this->setColor('green');
      } else {
        $this->setColor('grey');
      }
      $icon = 'fa-calendar-check-o';
    } else {
      $this->setColor('white');
      $text = "";
      $icon = false;
    }
    $view = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OUTLINE)
      ->setSlimShady(true)
      ->setName($text);

    if ($icon) {
      $view->setIcon($icon);
    }


    $color = $this->getColor();
    if ($color) {
      $view->setColor($color);
    }
    return $view;
  }

  public function getDeadline($viewer) {
    $task = $this->getObject();
    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_VIEW
    );

    $field_list->setViewer($viewer);
    $fields = $field_list->getFields();
    $field_list->readFieldsFromStorage($task);
    $deadline = $fields['std:maniphest:deadline.due'];



    return $deadline;
  }

}
<?php

final class DifferentialChangeIDField
  extends DifferentialCustomField {
  private $changeID;
  public function getFieldKey() {
    return 'gerrit:change-id';
  }
  public function getFieldKeyForConduit() {
    return 'changeID';
  }
  public function getFieldName() {
    return pht('Change-Id');
  }
  public function getFieldDescription() {
    return pht(
      'Ties commits to gerrit changes and provides a permanent link between them.');
  }
  public function canDisableField() {
    return false;
  }
  public function shouldAppearInCommitMessage() {
    return true;
  }
  public function parseValueFromCommitMessage($value) {
    // If the value is just "D123" or similar, parse the ID from it directly.
    $value = trim($value);
    return $value;
  }
  public function renderCommitMessageValue(array $handles) {
    $id = coalesce($this->changeID, $this->getObject()->getID());
    if (!$id) {
      return null;
    }
    return $id;
  }
  public function readValueFromCommitMessage($value) {
    $this->changeID = $value;
  }

}

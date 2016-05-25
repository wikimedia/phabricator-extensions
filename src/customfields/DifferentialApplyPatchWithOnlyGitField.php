<?php

final class DifferentialApplyPatchWithOnlyGitField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:apply-patch-git';
  }

  public function getFieldName() {
    return pht('Patch without arc');
  }

  public function getFieldDescription() {
    return pht('Provides instructions for applying a local patch using just git.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $mono = $this->getObject()->getMonogram();

    return phutil_tag('tt', array('class'=>'PhabricatorMonospaced'),
                      "git checkout -b {$mono} && curl -L https://{$_SERVER['HTTP_HOST']}/{$mono}?download=true | git apply");
  }

}

<?php

final class PhabricatorExpertModeSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'developer.expert-mode';

  const VALUE_NORMAL_MODE = '0';
  const VALUE_EXPERT_MODE = '1';

  public function getSettingName() {
    return pht('Expert Mode');
  }

  public function getSettingPanelKey() {
    return PhabricatorDeveloperPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function getControlInstructions() {
    return pht('Enable expert mode to reveal additional "advanced" options in the Phabricator UI.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_NORMAL_MODE;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_NORMAL_MODE => pht('Standard Phabricator.'),
      self::VALUE_EXPERT_MODE => pht('Expert Mode.'),
    );
  }

}

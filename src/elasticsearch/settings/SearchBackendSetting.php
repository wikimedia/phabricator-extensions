<?php

final class PhabricatorElasticSearchBackendSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'search.elastic.enabled';

  const VALUE_ELASTICSEARCH_DISABLED = '0';
  const VALUE_ELASTICSEARCH_ENABLED = '1';

  public function getSettingName() {
    return pht('ElasticSearch');
  }

  public function getSettingPanelKey() {
    return PhabricatorDeveloperPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorEnv::getEnvConfigIfExists(
                               'search.elastic.enabled', false);
  }

  protected function getControlInstructions() {
    return pht('Enable this to use the experimental ElasticSearch fulltext backend.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_ELASTICSEARCH_DISABLED;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_ELASTICSEARCH_DISABLED => pht('Disable ElasticSearch'),
      self::VALUE_ELASTICSEARCH_ENABLED => pht('Enable ElasticSearch'),
    );
  }

}

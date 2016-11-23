<?php
/**
 * Similar to PhabricatorConfigLocalSource with two differences:
 * 1. this source has a higher priority
 * 2. it reads from environment-specific file: /conf/local/{PHABRICATOR_ENV}.json
 */
final class WmfConfigSource extends PhabricatorConfigSiteSource {

  private static $readableConfigFiles = null;
  private $root = null;
  private $environment = null;
  private $configPath = null;

  public function __construct() {
    $env = PhabricatorEnv::getSelectedEnvironmentName();
    if (!$env) {
      $env = 'phd';
    }
    $_ENV['PHABRICATOR_ENV'] = $env;
    putenv($env);
    $this->environment = $env;
    $this->root = dirname(phutil_get_library_root('phabricator'));

    $config = $this->loadConfig();
    $this->setSource(new PhabricatorConfigDictionarySource($config));
  }

  public function setKeys(array $keys) {
    $result = parent::setKeys($keys);
    $this->saveConfig();
    return $result;
  }

  public function deleteKeys(array $keys) {
    $result = parent::deleteKeys($keys);
    $this->saveConfig();
    return parent::deleteKeys($keys);
  }

  private function loadConfig() {
    $path = $this->getConfigPath();
    if (@file_exists($path)) {
      $data = @file_get_contents($path);
      if ($data) {
        $data = json_decode($data, true);
        if (is_array($data)) {
          return $data;
        }
      }
    }
    return array();
  }

  private function saveConfig() {
    $path = $this->getConfigPath();
    if (!$path){
      throw new Exception("Unable to get config path for environment: $this->environment");
    }
    $config = $this->getSource()->getAllKeys();
    $json = new PhutilJSON();
    $data = $json->encodeFormatted($config);
    Filesystem::writeFile($path, $data);
  }

  private function getConfigPath() {
    if ($this->configPath) {
      return $this->configPath;
    }
    $path = "{$this->root}/conf/local/{$this->environment}.json";
    if (is_readable($path))
    {
      return $path;
    }
    $paths = $this->getReadableConfigFiles();
    if ($paths) {
      $this->configPath = reset($paths);
      return $this->configPath;
    }
    return false;
  }

  /**
  * get a list of config files which are owned by
  * the same gid as the current process.
  */
  private function getReadableConfigFiles() {
    if (self::$readableConfigFiles != null) {
      return self::$readableConfigFiles;
    }

    $results = array();
    $files = glob($this->root . "/conf/local/*.json");
    foreach ($files as $filename) {
      if (is_readable($filename) && substr($filename, -10) !== 'local.json') {
        $results[] = $filename;
      }
    }
    return self::$readableConfigFiles = $results;
}

}

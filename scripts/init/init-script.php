<?php

function init_script(array $options = array())
{
  error_reporting(E_ALL | E_STRICT);
  ini_set('display_errors', 1);
  $rootdir = dirname(__FILE__) . '/../../../../';

  $include_path = ini_get('include_path');
  ini_set(
    'include_path',
    $include_path . PATH_SEPARATOR . $rootdir
  );

  @include_once 'phabricator/scripts/init/init-script.php';

  $root = dirname(__FILE__) . '/../..';
  phutil_load_library($root);
  phutil_load_library('arcanist/src');
  phutil_load_library('phabricator/src');

  PhabricatorEnv::initializeScriptEnvironment(false);
}

class clog {
  static $show_verbose = false;

  static function log($args, $err=false) {
    $args = func_get_args();
    if (count($args) == 1) {
      $args = array_shift($args);
    }
    $console = PhutilConsole::getConsole();
    if (!is_string($args)) {
      if (is_string($args[0]) && count($args) == 2) {
        $args = $args[0] ." ". print_r($args[1], true);
      } else {
        $args = print_r($args, true);
      }
    }

    $console->writeOut($args."\n");
  }

  static function verbose($args) {
    $args = func_get_args();
    if (self::$show_verbose && (count($args) > 1 || !empty($args[0]))) {
      self::log($args);
    }
  }

  static function error($args) {
    $args = func_get_args();
    if (count($args) == 1 && is_string($args[0])) {
      $msg = " <fg:red>__Error:__</fg> **".$args[0]."**";
      self::log($msg);
    } else {
      self::log(' <fg:red>Error:</fg>', $args);
    }
  }

  static function warn($args) {
    $args = func_get_args();
    if (count($args) == 2 && is_string($args[0])) {
      self::log(" <fg:yellow>*</fg> $args[0]:", $args[1]);
    } else {
      self::log(' <fg:yellow>*</fg> ', $args);
    }
  }
}

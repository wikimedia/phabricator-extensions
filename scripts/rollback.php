#!/usr/bin/env php
<?php

$root = dirname(dirname(__FILE__));
require_once $root . '/scripts/init/init-script.php';
init_script();
$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('Phabricator transaction rollback tool.'));
$args->setSynopsis(<<<EOSYNOPSIS
**rollback** __workflow__ [__options__]
    Roll back transactions

EOSYNOPSIS
);
$args->parseStandardArguments();

$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('WikimediaCLIWorkflow')
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);

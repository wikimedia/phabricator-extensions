<?php

final class GerritApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Gerrit');
  }

  public function getBaseURI() {
    return '/r/';
  }

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    return array('/r/(?:(?P<gerritProject>.*)/)' => 'GerritProjectController');
  }

}
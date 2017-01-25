<?php

final class ElasticsearchApplication extends PhabricatorApplication {
  protected static $routes;

  public function getName() {
    return pht('ElasticSearch');
  }

  public function getShortDescription() {
    return pht('Full-Text Search backend that uses ElasticSearch.');
  }

  public function getFlavorText() {
    return pht('Find stuff in elastic piles.');
  }

  public function getIcon() {
    return 'fa-search';
  }

  public function isLaunchable() {
    return false;
  }

  public function getRoutes() {
      return array();
  }
}

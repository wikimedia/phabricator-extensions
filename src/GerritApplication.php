<?php

final class GerritApplication extends PhabricatorApplication {
  protected static $routes;
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
    if (count(self::$routes) > 0) {
      return self::$routes;
    }
/*
The following routes correspond the following gerrit [gitweb] config:

[gitweb]
    url = https://git.wikimedia.org
    type = custom
    revision = "/r/revision/${project};${commit}"
    project = /r/project/${project}
    branch = "/r/branch/${project};${branch}"
    filehistory = "/r/browse/${project};${branch};${file}"
    linkname = diffusion
    linkDrafts = false
    urlEncode = false
*/

    $routes = array(
      // filehistory
      '/r/(?P<action>[a-z]+)/(?P<gerritProject>[^:]+);(?P<branch>[^;]+);(?P<file>[^;]+)',
      // branch
      '/r/(?P<action>[a-z]+)/(?P<gerritProject>[^;]+);(?P<branch>[^;]+)',
      // commit
      '/r/(?P<action>[a-z]+)/(?P<gerritProject>[^;]+);(?P<sha>[0-9a-z]+)',
      // project
      '/r/(?P<action>[a-z]+)/(?:(?P<gerritProject>[^;]+)/)',
    );

    self::$routes = array_fill_keys($routes, 'GerritProjectController');

    return self::$routes;
  }

}
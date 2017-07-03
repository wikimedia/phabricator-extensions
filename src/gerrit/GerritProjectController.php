<?php

class GerritProjectController extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $data = $request->getURIMap();
    $project = isset($data['gerritProject'])
             ? preg_replace('/\.git$/', '', $data['gerritProject'])
             : '';
    $diff_uri = null;

    if ($project && isset(GerritProjectMap::$projects[$project])) {
      // static callsign lookup:
      $CALLSIGN = GerritProjectMap::$projects[$project];
      $diff_uri = "/diffusion/" . $CALLSIGN;
    } else if ($project) {
      // look up the repo uri from the database if it's not in the static map:
      $viewer = $request->getViewer();
      $query = new PhabricatorRepositoryQuery();
      $gerrit_uri = "https://gerrit.wikimedia.org/r";

      $project_uris = array(
        "{$gerrit_uri}/{$project}",
        "{$gerrit_uri}/{$project}/",
        "{$gerrit_uri}/p/{$project}",
        "{$gerrit_uri}/{$project}.git",
      );

      $repo = $query->withURIs($project_uris)
                      ->setLimit(1)
                      ->setViewer($viewer)
                      ->executeOne();
      if ($repo) {
        $diff_uri = rtrim($repo->getURI(), '/');
        $CALLSIGN = $repo->getCallsign();
        if (!strlen($CALLSIGN)) {
          $CALLSIGN = $repo->getID();
        }
      }
    }

    if (!$diff_uri) {
      $list_controller = new GerritProjectListController();
      $list_controller->setRequest($request);
      return $list_controller->showProjectList($request,
        pht("The requested project does not exist"));
    }

    $action = $data['action'];
    if ($action == 'p') {
      $diffusionArgs = isset($data['diffusionArgs'])
                     ? $data['diffusionArgs']
                     : "";
      if ( $request->getStr('view') == 'raw') {
        $diffusionArgs .= "?view=raw";
      }
      return id(new AphrontRedirectResponse())
        ->setURI("{$diff_uri}/$diffusionArgs");
    } elseif ($action == 'branch') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);
      if (strlen($branch)==0) {
        return id(new AphrontRedirectResponse())
          ->setURI("{$diff_uri}/browse/");
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI("{$diff_uri}/browse/$branch/");
      }
    } elseif ($action == 'history') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);

      return id(new AphrontRedirectResponse())
        ->setURI("{$diff_uri}/history/$branch/");
    } elseif ($action == 'tags') {
      return id(new AphrontRedirectResponse())
        ->setURI("{$diff_uri}/tags/");
    } elseif ($action == 'tag') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $tag = $this->getTagNameFromRef($data['branch']);
      return id(new AphrontRedirectResponse())
        ->setURI("{$diff_uri}/browse/;$tag");
    }elseif ($action == 'browse') {
      if (!isset($data['branch']) || !isset($data['file'])) {
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);
      $file = $data['file'];
      return id(new AphrontRedirectResponse())
        ->setURI("{$diff_uri}/browse/$branch/$file");
    } elseif ($action == 'revision' || $action == 'patch'
           || $action == 'commit') {
      $sha = isset($data['sha'])
        ? $data['sha']
        : $data['branch'];
      if ($request->getExists('diff') || $request->getExists('patch') ||
          $action == 'patch') {
        $querystring = '?diff=1';
      } else {
        $querystring = '';
      }
      return id(new AphrontRedirectResponse())
        ->setURI('/r' . $CALLSIGN . $sha . $querystring);
    } elseif ($action == 'project') {
      return id(new AphrontRedirectResponse())
        ->setURI($diff_uri);
    }
    phlog('did not match any repository redirect action');
    return new Aphront404Response();

  }

  private function getBranchNameFromRef($branch) {
    // get rid of refs/heads prefix
    $branch = str_replace('refs/heads', '', $branch);
    $branch = trim($branch, '/');
    $branch = str_replace('HEAD', '', $branch);
    // double encode any forward slashes in ref.
    $branch = str_replace('%2F', '%252F', $branch);
    $branch = str_replace('/', '%252F', $branch);
    return $branch;
  }

  private function getTagNameFromRef($tag) {
    // get rid of refs/tags prefix
    $tag = str_replace('refs/tags', '', $tag);
    $tag = trim($tag, '/');
    return $tag;
  }

  public function shouldAllowPublic() {
    return true;
  }
}

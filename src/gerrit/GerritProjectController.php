<?php

class GerritProjectController extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $data = $request->getURIMap();
    $project = preg_replace('/\.git$/', '', $data['gerritProject']);

    if (!isset(GerritProjectMap::$projects[$project])) {
      $list_controller = new GerritProjectListController();
      $list_controller->setRequest($request);
      return $list_controller->showProjectList($request,
        pht("The requested project does not exist"));
    }
    $CALLSIGN = GerritProjectMap::$projects[$project];
    $action = $data['action'];
    if ($action == 'p') {
      $diffusionArgs = isset($data['diffusionArgs'])
                     ? $data['diffusionArgs']
                     : "";
      if ( $request->getStr('view') == 'raw') {
        $diffusionArgs .= "?view=raw";
      }
      return id(new AphrontRedirectResponse())
        ->setURI("/diffusion/$CALLSIGN/$diffusionArgs");
    } elseif ($action == 'branch') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);
      if (strlen($branch)==0) {
        return id(new AphrontRedirectResponse())
          ->setURI("/diffusion/$CALLSIGN/browse/");
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI("/diffusion/$CALLSIGN/browse/$branch/");
      }
    } elseif ($action == 'history') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);

      return id(new AphrontRedirectResponse())
        ->setURI("/diffusion/$CALLSIGN/history/$branch/");
    } elseif ($action == 'tags') {
      return id(new AphrontRedirectResponse())
        ->setURI("/diffusion/$CALLSIGN/tags/");
    } elseif ($action == 'tag') {
      if (!isset($data['branch'])){
        return new Aphront404Response();
      }
      $tag = $this->getBranchNameFromRef($data['branch']);
      return id(new AphrontRedirectResponse())
        ->setURI("/diffusion/$CALLSIGN/browse/;$tag");
    }elseif ($action == 'browse') {
      if (!isset($data['branch']) || !isset($data['file'])) {
        return new Aphront404Response();
      }
      $branch = $this->getBranchNameFromRef($data['branch']);
      $file = $data['file'];
      return id(new AphrontRedirectResponse())
        ->setURI("/diffusion/$CALLSIGN/browse/$branch/$file");
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
        ->setURI("/diffusion/$CALLSIGN/");
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
    $branch = str_replace('/', '%252F', $branch);
    return $branch;
  }

  public function shouldAllowPublic() {
    return true;
  }
}

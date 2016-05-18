<?php

class GerritProjectController extends PhabricatorController {

	final public function handleRequest(AphrontRequest $request) {
		$data = $request->getURIMap();
    if (!isset($data['gerritProject'])) {
      return $this->showProjectList($request);
    }
		$project = preg_replace('/\.git$/', '', $data['gerritProject']);

    $notFoundMessage = pht("The requested project does not exist");

		if (!isset(GerritProjectMap::$projects[$project])) {
			$list_controller = new GerritProjectListController();
			return $list_controller->showProjectList($request, $notFoundMessage);
		}
		$CALLSIGN = GerritProjectMap::$projects[$project];

		if ($data['action'] == 'branch') {
			if (!isset($data['branch'])){
				return new Aphront404Response();
			}
			$branch = $data['branch'];
			// get rid of refs/heads prefix
			$branch = str_replace('refs/heads', '', $branch);
			$branch = trim($branch, '/');
			$branch = str_replace('HEAD', '', $branch);
			// double encode any forward slashes in ref.
			$branch = str_replace('/', '%252F', $branch);
			if (strlen($branch)==0) {
				  return id(new AphrontRedirectResponse())
						->setURI("/diffusion/$CALLSIGN/browse/");
			} else {
				  return id(new AphrontRedirectResponse())
						->setURI("/diffusion/$CALLSIGN/browse/$branch/");
			}
		}
		if ($data['action'] == 'browse') {
			if (!isset($data['branch']) || !isset($data['file'])) {
				return new Aphront404Response();
			}
			$branch = $data['branch'];
			$file = $data['file'];
			return id(new AphrontRedirectResponse())
				->setURI("/diffusion/$CALLSIGN/browse/$branch/$file");
		}
		if ($data['action'] == 'revision') {
			$sha = isset($data['sha'])
				 ? $data['sha']
				 : $data['branch'];
			return id(new AphrontRedirectResponse())
				->setURI('/r' . $CALLSIGN . $sha);
		}

		if ($data['action'] == 'project') {
			return id(new AphrontRedirectResponse())
				->setURI("/diffusion/$CALLSIGN/");
		}
		phlog('did not match any repository redirect action');
		return new Aphront404Response();

	}

	public function shouldAllowPublic() {
		return true;
	}
}

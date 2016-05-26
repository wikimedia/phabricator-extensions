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
		if ($data['action'] == 'history') {
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

			return id(new AphrontRedirectResponse())
				->setURI("/diffusion/$CALLSIGN/history/$branch/");
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

<?php

class GoGetMetaRepositoryExtension extends DiffusionRepositoryExtension
{
  public function willHandleRequest(AphrontRequest $request, PhabricatorRepository $repository)
  {
    if (!$request->getBool('go-get')) {
      return false;
    }



    $uris = $repository->getCloneURIs();
    foreach ($uris as $uri) {
      $uri = $uri->getEffectiveURI();
      $proto = $uri->getProtocol();
      if ($proto == 'https') {
        break;
      }
      else if ($proto == 'http') {
        $uri->setProtocol('https');
        break;
      }
    }

    $gopath = substr($uri->getDomain() . $uri->getPath(), 0, -4);

    //This should produce something like the following meta tag:
    /*<meta name="go-import"
          content="phab.uri/source/repo git https://phab.uri/source/repo.git"
    />*/

    $response = new AphrontWebpageResponse();
    $html = <<<EHTML
<html>
  <head>
  <meta name="go-import" content="$gopath git $uri"/>
  </head>
  <body>
    $gopath git $uri
  </body>
</html>
EHTML;
    return $response->setContent(phutil_safe_html($html));
  }

  public function willModifyPageView(
    PhabricatorUser $viewer,
    AphrontRequest $request,
    PhabricatorRepository $repository,
    DiffusionRequest $drequest){
      // noop
  }
}

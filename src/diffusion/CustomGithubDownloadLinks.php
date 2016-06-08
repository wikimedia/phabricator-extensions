<?php

class CustomGithubDownloadLinks {

  static function getMirrorURI($repo) {
    $uris = $repo->getURIs();

    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }
      if ($uri->getEffectiveIoType() == PhabricatorRepositoryURI::IO_MIRROR) {
        return $uri;
      }
    }
    return false;
  }

  static function AddActionLinksToCurtain($repository, $identifier, $curtain) {

    $uri = self::getMirrorURI($repository);
    if (!$uri) {
      return;
    }
    $uri = $uri->getURI();
    
    $action = id(new PhabricatorActionView())
        ->setName(pht('Download zip (from Github)'))
        ->setIcon('fa-download')
        ->setHref($uri.'/archive/'.$identifier.'.zip');
    $curtain->addAction($action);

    $action = id(new PhabricatorActionView())
        ->setName(pht('Download gz (from Github)'))
        ->setIcon('fa-download')
        ->setHref($uri.'/archive/'.$identifier.'.tar.gz');
    $curtain->addAction($action);
  }

  static function addActionsToCurtainFromRequest($drequest, $curtain) {
    $repository = $drequest->getRepository();

    if ($drequest->getSymbolicType() == 'tag') {
      $download = $drequest->getSymbolicCommit();
    } elseif ($drequest->getSymbolicType() == 'commit') {
      $download = $drequest->getStableCommit();
    } else {
      $download = $drequest->getBranch();
    }

    return self::AddActionLinksToCurtain($repository, $download, $curtain);
  }
}

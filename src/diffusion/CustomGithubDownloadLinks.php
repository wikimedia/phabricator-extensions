<?php

class CustomGithubDownloadLinks {

  static function getMirrorURI($repo) {
    $uris = $repo->getURIs();

    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }
      if ($uri->getEffectiveIoType() == PhabricatorRepositoryURI::IO_MIRROR &&
          strpos($uri->getDisplayURI(), 'github') !== false) {
        return $uri;
      }
    }
    return false;
  }

  static function AddActionLinksToTop($repository, $viewer, $identifier, $color) {

    $uri = self::getMirrorURI($repository);
    if (!$uri) {
      return;
    }
    $uri = $uri->getURI();

    $action_view = id(new PhabricatorActionListView())
      ->setViewer($viewer)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Download zip'))
          ->setIcon('fa-github')
          ->setDownload(true)
          ->setHref($uri.'/archive/'.$identifier.'.zip'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Download tar.gz'))
          ->setIcon('fa-github')
          ->setDownload(true)
          ->setHref($uri.'/archive/'.$identifier.'.tar.gz'));

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Download Archive'))
      ->setHref('#')
      ->setColor($color)
      ->setIcon('fa-download')
      ->setDropdownMenu($action_view);
  }

  static function AddActionLinksToCurtain($drequest, $viewer, $color=PHUIButtonView::GREY) {
    $repository = $drequest->getRepository();

    try {
      if ($drequest->getSymbolicType() == 'tag') {
        $download = $drequest->getSymbolicCommit();
      } elseif ($drequest->getSymbolicType() == 'commit') {
        $download = $drequest->getStableCommit();
      } else {
        $download = $drequest->getBranch();
      }
    } catch(Exception $e) {
      return '';
    }

    return self::AddActionLinksToTop($repository, $viewer, $download, $color);
  }
}

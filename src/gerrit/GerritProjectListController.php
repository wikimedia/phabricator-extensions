<?php
class GerritProjectListController extends GerritProjectController {

  public function handleRequest(AphrontRequest $request) {
    return $this->showProjectList($request);
  }

  function assignArrayByPath(&$arr, $path, $callsign) {
    $keys = explode('/', $path);

    $temp = &$arr;
    foreach($keys as $key) {
      if (!isset($temp[$key])) {
        $temp[$key] = array();
      } else if (!is_array($temp[$key])) {
        $temp[$key] = array($temp[$key]);
      }
      $temp = &$temp[$key];
    }

    $key = end($keys);
    $temp[$key] = phutil_tag('a', array('href'=>"/diffusion/$callsign", 'title'=>$path), $key . " ($callsign)");
  }

  function arrayToUl($arr, $path='', $depth=1) {

    $items = array();

    foreach($arr as $key=>$val) {
      while (is_array($val) && count($val) == 1) {
        $val = end($val);
      }
      if (is_array($val)) {
        if (isset($val[$key])) {
          $subkey = $val[$key];
          unset($val[$key]);
        } else {
          $subkey = $key;
        }
        ksort($val);
        $item = array(phutil_tag('h'.$depth,array(),$subkey), $this->arrayToUl($val, $path.'/'.$key, $depth+1));
      } else {
        $item = phutil_tag('h'.$depth,array(),$val);
      }
      $items[] = phutil_tag('li', array(), $item);
    }
    return phutil_tag('ul', array('class'=>'remarkup-list', 'data-path' => $path), $items);
  }

  public function showProjectList(AphrontRequest $request, $message="") {
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Browse Gerrit Projects'));
    $projects = array();

    foreach(GerritProjectMap::$projects as $path => $callsign) {
      $this->assignArrayByPath($projects, $path, $callsign);
    }
    $view = phutil_tag_div('phabricator-remarkup', $this->arrayToUl($projects));
    $page = $this->buildStandardPageView();
    $page->setApplicationName(pht('Gerrit'));
    $page->setBaseURI('/r/');
    $page->setTitle(pht('Gerrit Projects'));
    $page->setDeviceReady(true);
    if ($message !== "") {
      $message = id(new PHUIInfoView())
        ->setTitle($message)
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA);
    }

    $page->appendChild(array($crumbs, $message, $view));

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }
}

<?php

class ReleaseDetailsCustomField
  extends ManiphestCustomField
  implements PhabricatorStandardCustomFieldInterface{

  private $value = null;
  private $textproxy;

  public function __construct() {
    $this->textproxy = id(new PhabricatorStandardCustomFieldText())
      ->setFieldKey($this->getFieldKey())
      ->setApplicationField($this)
      ->setFieldConfig(array(
        'name' => $this->getFieldName(),
        'description' => $this->getFieldDescription(),
      ));
    $this->setProxy($this->textproxy);
  }


  public function getFieldKey() {
    return 'wmf:releasedetails';
  }

  public function getModernFieldKey() {
    return 'releasedetails';
  }

  public function getFieldName() {
    return 'Release Details';
  }

  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function shouldAppearInPropertyView() {
    $task = $this->getObject();
    if (!$task) {
      return false;
    }
    return $task->getSubtype() == 'release';
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function getFieldValue() {
    return $this->textproxy->getFieldValue();
  }

  public function renderPropertyViewLabel() {
    return "";
  }
  public function getStyleForPropertyView() {
    return 'block';
  }

  public function getStandardCustomFieldNamespace() {
    return 'maniphest';
  }

  public function renderEditControl(array $handles) {
    return id(new PhabricatorRemarkupControl())
    ->setUser($this->getViewer())
    ->setLabel($this->getFieldName())
    ->setName($this->getFieldKey())
    ->setValue($this->getFieldValue());
  }


  public function renderPropertyViewValue(array $handles) {
    $obj = $this->getObject();
    $fields = $obj->getCustomFields();
    $field_list = $fields->getCustomFieldList('view');
    $fields = $field_list->getFields();
    $date = new DateTime();
    $version = "";
    $fieldIndex = null;
    foreach($fields as $key => $field) {
      $val = $field->getValueForStorage();
      if ($key == 'std:maniphest:release.date' && $val) {
        $date->setTimestamp((int)$val);
      } else if ($key == 'std:maniphest:release.version' && $val) {
        $fieldIndex = $field->getFieldIndex();
        $version = $val;
      }
    }
    // if this task doesn't have a release.version assigned then it isn't part
    // of a release series so we should not render anything:
    if (!isset($fieldIndex)) {
      return "";
    }

    // Format dates related to this task based on the release.date custom field
    $year = $date->format("Y");
    $week = $date->format("W");

    $weekdaynum = $date->format('N');
    if ($weekdaynum > 1) {
      // if release.date is after monday, subtract days the preceeding monday:
      $subtract_days = $weekdaynum-1;
      $interval = new DateInterval('P'.$subtract_days."D");
      $date->sub($interval);
    }
    $weekday = $date->format("l");
    $month = $date->format("F");
    $monthday = $date->format("jS");
    $oneday = new DateInterval('P1D');
    $date->add($oneday);
    $tue = $date->format('F jS');
    $date->add($oneday);
    $wed = $date->format('F jS');
    $date->add($oneday);
    $thu = $date->format('F jS');

    list($major, $wmfnum) = explode("-", $version);
    $major = explode('.', $major);
    $major = $major[0] . '.' . $major[1];

    // look up prev/next task monograms:
    $series = $this->getSeries($fieldIndex, $version);

    // vars to pass to template:
    $vars = compact("major", "wmfnum", "year", "week", "weekday", "month",
      "monthday", "tue", "wed", "thu", "series");

    // render template to remarkupL
    $remarkup = $this->renderTrainDeployDetails($vars);
    $viewer = $this->getViewer();

    // RemarkupView based on the template markup:
    return new PHUIRemarkupView($viewer, $remarkup);
  }

  private function renderTrainDeployDetails($vars) {
    extract($vars);
    return <<<EOT
=  {icon calendar} **$year** week **$week** {icon angle-right} {icon book} [[ https://www.mediawiki.org/wiki/Special:MyLanguage/MediaWiki_$major/$wmfnum | $major-$wmfnum Roadmap ]] {icon angle-right} {icon git} [[/source/mediawiki/history/wmf%252F$major-$wmfnum|wmf/$major-$wmfnum  ]]

This MediaWiki Train Deployment is scheduled for the week of **$weekday, $month $monthday**:

|**Monday $month $monthday**|**Tuesday, {$tue}**      | **Wednesday, {$wed}**   | **Thursday, {$thu}** | **Friday**               |
|---------------------------|-------------------------|-------------------------|----------------------|--------------------------|
|SWAT deployments only      |Branch `$wmfnum`, Deploy to Group 0 Wikis| Deploy `$wmfnum` to Group 1 Wikis | Deploy `$wmfnum` to all Wikis  |No deployments on fridays |

* See https://wikitech.wikimedia.org/wiki/Deployments for full schedule.

== {icon info-circle} How this works
* Any serious bugs affecting `$wmfnum` should be added as subtasks beneith this one.
** Use the `Edit Related Tasks` menu to add one.
* Any open subtasks block the train from moving forward. This means no further deployments until the blockers are resolved.
* If something is serious enough to warrant a rollback then you should contact someone on the [[ https://www.mediawiki.org/wiki/MediaWiki_on_IRC | #wikimedia-operations IRC channel ]].

== {icon subway} Other Versions ==
* [[https://www.mediawiki.org/wiki/MediaWiki_$major/Roadmap|MediaWiki $major/Roadmap]]
|{icon chevron-left} Previous Version|     | Next Version {icon chevron-right}|
|----------------|-----|-------------|
|{$series['prev']}| {icon arrows-h}| {$series['next']}|


EOT;
  }


  /**
   * Get the monograms for tasks that represent the versions immediately
   * preceeding and following the current task in a series.
   * @param fieldIndex the fieldIndex for the release.version custom field
   * @return map of exactly two strings with keys 'prev' and 'next'
   */
  private function getSeries($fieldIndex, $version) {

    // decompose the version and increment / decrement the minor segment to find
    // the version number for the previous and next version in this series.
    $v = explode(".", $version);
    $minor = $v[3];
    $versions = array();
    if ($minor > 1) {
      $prev = $minor-1;
      $prev = join('.', array($v[0],$v[1],$v[2],$prev));
      $versions[] = $prev;
    } else {
      $prev = '...';
    }

    $next = $v[3]+1;
    $next = join('.', array($v[0],$v[1],$v[2],$next));
    $versions[] = $next;

    $storage = $this->newStorageObject();
    $conn = $storage->establishConnection('r');
    $indexes = array($fieldIndex);

    // dirty manual query of the custom field storage table to get the value
    // for the release.version field for the previous and next version in this
    // series
    $rows = queryfx_all(
      $conn,
      'SELECT objectPHID, fieldIndex, fieldValue FROM %T
        WHERE fieldIndex IN (%Ls) AND fieldValue in (%Ls)',
      $storage->getTableName(),
      $indexes,
      $versions);


    // now save the versions and phids
    $versions = array();
    $phids = array();
    foreach($rows as $row) {
      $phid = $row['objectPHID'];
      $taskVersion = $row['fieldValue'];
      $phids[] = $phid;
      $versions[$phid] = $taskVersion;
    }

    // now look up the monograms for the phids we found
    $query = id(new ManiphestTaskQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids);
    $tasks = $query->execute();

    $res = array('prev' => '...', 'next' => '...');
    foreach ($tasks as $id => $task) {
      $monogram = $task->getMonogram();
      $phid = $task->getPHID();
      $version = $versions[$phid];
      if ($version == $prev) {
        $res['prev'] = '{'.$monogram.'}';
      } else if ($version == $next) {
        $res['next'] = '{'.$monogram.'}';
      }
    }
    return $res;
  }

}
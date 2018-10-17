<?php

class ReleaseDetailsCustomField
  extends ManiphestCustomField
  implements PhabricatorStandardCustomFieldInterface{

  private $value = null;
  private $textproxy;

  public function __construct() {
    $this->textproxy = id(new PhabricatorStandardCustomFieldText())
      ->setFieldKey($this->getFieldKey())
      ->setRawStandardFieldKey($this->getModernFieldKey())
      ->setApplicationField($this)
      ->setFieldConfig(array(
        'name' => $this->getFieldName(),
        'description' => $this->getFieldDescription(),
      ));
    //$this->setProxy($this->textproxy);
  }


  public function getFieldKey() {
    return 'wmf:release.details';
  }

  public function getModernFieldKey() {
    return 'release.details';
  }

  public function getFieldName() {
    return pht('Release Details');
  }

  public function getFieldDescription() {
    return pht('Auto-generated release schedule details.');
  }

  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return false;
  }

  public function shouldAppearInConduitTransactions() {
    return false;
  }

  public function shouldAppearInConduitDictionary() {
    return false;
  }

  public function shouldAppearInEditEngine() {
    return false;
  }

  public function shouldAppearInEditView() {
    return false;
  }

  public function shouldAppearInPropertyView() {
    $task = $this->getObject();
    if (!$task) {
      return false;
    }
    return $task->getSubtype() == 'release';
  }

  public function shouldUseStorage() {
    return false;
  }

  public function getFieldValue() {
    return '';//return $this->textproxy->getFieldValue();
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
    $taskid = $obj->getId();
    $fields = $obj->getCustomFields();
    $field_list = $fields->getCustomFieldList('view');
    $fields = $field_list->getFields();
    $date = new DateTime();
    $version = "";
    $fieldIndex = null;

    foreach($fields as $key => $field) {
      if (!$field->shouldUseStorage()) {
        continue;
      }
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
      "monthday", "tue", "wed", "thu", "series", "taskid");

    // render template to remarkupL
    $remarkup = $this->renderTrainDeployDetails($vars);
    $viewer = $this->getViewer();

    // RemarkupView based on the template markup:
    $view = id(new PHUIRemarkupView($viewer, $remarkup))
      ->setRemarkupOption('uri.same-window', true)
      ->setContextObject($obj);

    $prev_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-chevron-left')
      ->setText(pht('Previous: %s', $series['prev-version']));

    if ($series['prev'] == '...') {
      $prev_button->setDisabled(true);
    } else {
      $prev_button->setHref('/'.$series['prev']);
    }

    $next_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-chevron-right')
      ->setText(pht('Next: %s', $series['next-version']));

    if ($series['next'] == '...') {
      $next_button->setDisabled(true);
    } else {
      $next_button->setHref('/'.$series['next']);
    }

    return array($view, $prev_button, ' ', $next_button);

  }

  private function renderTrainDeployDetails($vars) {
    extract($vars);
    return <<<EOT
=  {icon calendar} **$year** week **$week** {icon angle-right} {icon book} [[ https://www.mediawiki.org/wiki/Special:MyLanguage/MediaWiki_$major/$wmfnum | $major-$wmfnum Changes ]] {icon angle-right} {icon git} [[/source/mediawiki/history/wmf%252F$major-$wmfnum|wmf/$major-$wmfnum  ]]

This MediaWiki Train Deployment is scheduled for the week of **$weekday, $month $monthday**:

|**Monday $month $monthday**|**Tuesday, {$tue}**      | **Wednesday, {$wed}**   | **Thursday, {$thu}** | **Friday**               |
|---------------------------|-------------------------|-------------------------|----------------------|--------------------------|
|SWAT deployments only      |Branch `$wmfnum`, Deploy to Group 0 Wikis| Deploy `$wmfnum` to Group 1 Wikis | Deploy `$wmfnum` to all Wikis  |No deployments on fridays |

* See https://wikitech.wikimedia.org/wiki/Deployments for full schedule.

== {icon info-circle} How this works
* Any serious bugs affecting `$wmfnum` should be added as subtasks beneath this one.
** Use [[/maniphest/task/edit/form/46/?parent=$taskid|this form]] to create one.
* Any open subtasks block the train from moving forward. This means no further deployments until the blockers are resolved.
* If something is serious enough to warrant a rollback then you should bring it to the attention of deployers on the [[ https://www.mediawiki.org/wiki/MediaWiki_on_IRC | #wikimedia-operations IRC channel ]].

----
== {icon link} Related Links ==
* {icon map-marker} [[https://www.mediawiki.org/wiki/MediaWiki_$major/Roadmap|MediaWiki $major/Roadmap]]
* {icon code-fork} [[/source/mediawiki/compare/?head=wmf%2F$major-$wmfnum&against=master|Commits cherry-picked to $major-$wmfnum ]]

== {icon arrows-h} Other Deployments ==
EOT;
  }


  /**
   * Get the monograms for tasks that represent the versions immediately
   * preceeding and following the current task in a series.
   * @param fieldIndex the fieldIndex for the release.version custom field
   * @return map of exactly two strings with keys 'prev' and 'next'
   */
  private function getSeries($fieldIndex, $version) {
    $storage = $this->newStorageObject();
    $conn = $storage->establishConnection('r');
    $indexes = array($fieldIndex);

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
      $vprev = $v[1]-1;
      $prev = "$v[0].$vprev.$v[2]";
      $prev_series = $this->getEndOfSeries($prev,
        'DESC', $indexes, $conn, $storage);
      if (empty($prev_series)) {
        $prev = '...';
      } else {
        $versions[] = $prev_series;
        $prev = $prev_series;
      }
    }

    $endOfSeries = $this->getEndOfSeries(join('.', array($v[0],$v[1],$v[2])),
       'DESC', $indexes, $conn, $storage);
    if ($endOfSeries == $version) {
      $vnext = $v[1]+1;
      $next = "$v[0].$vnext.$v[2].1";
    } else {
      $next = $v[3]+1;
      $next = join('.', array($v[0],$v[1],$v[2],$next));
    }
    $versions[] = $next;

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

    $res = array('prev' => '...', 'next' => '...',
                 'prev-version' => '...', 'next-version' => '...');
    if (empty($phids)) {
      return $res;
    }

    // now look up the monograms for the phids we found
    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();

    foreach ($tasks as $id => $task) {
      $monogram = $task->getMonogram();
      $phid = $task->getPHID();
      $version = $versions[$phid];
      if ($version == $prev) {
        $res['prev'] = $monogram;
        $res['prev-version'] = $version;
      } else if ($version == $next) {
        $res['next'] = $monogram;
        $res['next-version'] = $version;
      }
    }
    return $res;
  }

  private function getEndOfSeries($v, $order, $indexes, $conn, $storage) {
    static $versions = array();

    if (!isset($versions[$v])) {
      $rows = queryfx_all(
        $conn,
        'SELECT objectPHID, fieldIndex, fieldValue FROM %T
          WHERE fieldIndex IN (%Ls) AND fieldValue like %>',
        $storage->getTableName(),
        $indexes,
        $v);

      if (empty($rows)) {
        return false;
      }

      $sorted = ipull($rows, 'fieldValue');
      usort($sorted, 'version_compare');
      $versions[$v] = array(reset($sorted), end($sorted));
    }

    if ($order == "DESC") {
      return end($versions[$v]);
    } else {
      return reset($versions[$v]);
    }

  }

}
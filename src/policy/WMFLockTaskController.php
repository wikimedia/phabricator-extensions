<?php

final class WMFEscalateTaskController extends PhabricatorController {

  protected function renderRemarkup($remarkup) {
    return phutil_safe_html(PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($remarkup),
        'default',
        $this->getViewer()));
  }

  public function renderRevealContentBlock($remarkup) {
    Javelin::initBehavior('phabricator-reveal-content');
    $text = $this->renderRemarkup($remarkup);
    $hide_action_id = celerity_generate_unique_node_id();
    $show_action_id = celerity_generate_unique_node_id();
    $content_id     = celerity_generate_unique_node_id();

    $hide_action = javelin_tag(
      'a',
      array(
        'sigil' => 'reveal-content',
        'id'    => $hide_action_id,
        'href'  => '#',
        'meta'  => array(
          'showIDs' => array($content_id, $show_action_id),
          'hideIDs' => array($hide_action_id),
        ),
      ),
      pht('Expand Instructions'));

    $show_action = javelin_tag(
      'a',
      array(
        'sigil' => 'reveal-content',
        'style' => 'display: none;',
        'id'    => $show_action_id,
        'href'  => '#',
        'meta'  => array(
          'showIDs' => array($hide_action_id),
          'hideIDs' => array($content_id, $show_action_id),
        ),
      ),
      pht('Hide Instructions'));
    $link_div = phutil_tag_div('aphront-form-instructions',
     phutil_safe_html($hide_action . $show_action));

    $content_div = javelin_tag(
      'div',
      array(
        'style'   => join(';',array(
                      'display: none',
                      'border-left: 2px solid #ccc',
                      'background-color: #eee',
                      'padding-left: 1em')),
        'id'      => $content_id,
        'class'   => 'aphront-form-instructions',
      ),
      phutil_safe_html($text)
    );
    return phutil_safe_html($link_div . $content_div);
  }


  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();

    if (!$task) {
      return new Aphront404Response();
    }

    $task_uri = '/'.$task->getMonogram();

    // See "WMFLockTaskEventListener" for notes.
    $is_locked = !WMFSecurityPolicy::isTaskPublic($task);
    $can_lock = $viewer->isLoggedIn();

    // Task can't be escalated by the acting user, show a "you can't do this"
    // dialog.
    if (!$can_lock) {
      return $this->newDialog()
        ->setTitle(pht('No Permission'))
        ->appendParagraph(
          pht(
            'You do not have permission to escalate tasks as security issues. '.
            'This action can be taken by logged in users.'))
        ->addCancelButton($task_uri);
    }

    // User submitted the form, so lock the task.
    if ($request->isFormPost()) {
      $comment_text = $request->getStr('comments');

      $template = $task->getApplicationTransactionTemplate();
      $comment_template = $template->getApplicationTransactionCommentObject();

      $project_philds = array();
      if ($project = WMFSecurityPolicy::getProjectByName('security')) {
        $project_phids[] = $project->getPHID();
      }
      phlog($project_phids);
      $view_policy = WMFSecurityPolicy::createCustomPolicy(
        $task,
        $task->getAuthorPHID(),
        $project_phids,
        true
      );
      // view policy and edit policy will be identical:
      $policy_phid = $view_policy->getPHID();

      $xactions = array();

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
        ->setMetadataValue('customfield:key', 'std:maniphest:security_topic')
        ->setOldValue(null)
        ->setNewValue('security-bug');

      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(clone $comment_template)
            ->setContent($comment_text));

      if (!empty($project_phids)) {
        $type_edge = PhabricatorTransactions::TYPE_EDGE;
        $xactions[$type_edge] = id(new ManiphestTransaction())
          ->setTransactionType($type_edge)
          ->setMetadataValue('edge:type',
                PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
          ->setNewValue(array('+' => array_fuse($project_phids)));
      }

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($policy_phid);

      $omnipotent_user = PhabricatorUser::getOmnipotentUser();

      $editor = id(new ManiphestTransactionEditor())
        ->setContentSourceFromRequest($request)
        ->setActor($omnipotent_user)
        ->setActingAsPHID($viewer->getPHID())
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($task, $xactions);

      // This may bring the user to a policy exception if they can no longer
      // see the task.
      return id(new AphrontRedirectResponse())
        ->setURI($task_uri);
    }

    $monogram = $task->getMonogram();

    // Important is shown in a red box:
    $instructions = <<<MSG
IMPORTANT: You should only escalate tasks that describe
real or potential security vulnerabilities with Wikimedia software or services.
MSG;

    // detailed explanation is collapsed by default with a link to expand it.
    $more_info_url = "https://www.mediawiki.org/wiki/Reporting_security_bugs";
    $detail = <<<DETAIL
This feature is used to correct the `view policy` on security bugs that were
incorrectly submitted as regular bug reports.

Security bugs should be escalated because of the potential that the information
could be used to develop an exploit or otherwise harm Wikimedia services or the
many people and organizations who utilize our software.

Generally the details will be made public again once the security team has
had time to properly address the issue and publically announce the
vulnerability.

See [[$more_info_url|Reporting security bugs]] for more information about
reporting a vulnerability.
DETAIL;

    // Specific information about who can view a task after escalation:
    $who_can_view = <<<WHO
Escalating will restrict visibility of $monogram so that only the following
people can view it:

* {icon users} Members of the #security team.
* {icon plus-circle} Subscribers to the task.
* {icon user} Author of the task.

NOTE: Unless you are one of the people listed above, you will not be be able to
view the task after you click `Escalate`.
WHO;

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions($instructions)
      ->appendChild($this->renderRevealContentBlock($detail))
      ->appendRemarkupInstructions($who_can_view)
      ->appendControl(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Comments'))
          ->setName('comments'));

    return $this->newDialog()
      ->setTitle(pht('Escalate security issue'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addCancelButton($task_uri)
      ->addSubmitButton(pht('Escalate'));
  }

}

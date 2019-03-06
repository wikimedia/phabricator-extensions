<?php

/**
 * This file is automatically generated. Use 'arc liberate' to rebuild it.
 *
 * @generated
 * @phutil-library-version 2
 */
phutil_register_library_map(array(
  '__library_version__' => 2,
  'class' => array(
    'CreatePolicyConduitAPIMethod' => 'src/conduit/CreatePolicyConduitAPIMethod.php',
    'CustomGithubDownloadLinks' => 'src/diffusion/CustomGithubDownloadLinks.php',
    'CustomLoginHandler' => 'src/other/CustomLoginHandler.php',
    'DeadlineEditEngineSubtype' => 'src/customfields/DeadlineEditEngineSubtype.php',
    'DifferentialApplyPatchWithOnlyGitField' => 'src/customfields/DifferentialApplyPatchWithOnlyGitField.php',
    'ElasticsearchApplication' => 'src/elasticsearch/ElasticsearchApplication.php',
    'GerritApplication' => 'src/gerrit/GerritApplication.php',
    'GerritChangeIdField' => 'src/gerrit/GerritChangeIdField.php',
    'GerritProjectController' => 'src/gerrit/GerritProjectController.php',
    'GerritProjectListController' => 'src/gerrit/GerritProjectListController.php',
    'GerritProjectMap' => 'src/gerrit/GerritProjectMap.php',
    'GoGetMetaRepositoryExtension' => 'src/diffusion/GoGetMetaRepositoryExtension.php',
    'LDAPUserQueryConduitAPIMethod' => 'src/conduit/LDAPUserQueryConduitAPIMethod.php',
    'LDAPUserpageCustomField' => 'src/customfields/LDAPUserpageCustomField.php',
    'MediaWikiUserQueryConduitAPIMethod' => 'src/conduit/MediaWikiUserQueryConduitAPIMethod.php',
    'MediaWikiUserpageCustomField' => 'src/customfields/MediaWikiUserpageCustomField.php',
    'PhabricatorElasticSearchBackendSetting' => 'src/elasticsearch/settings/SearchBackendSetting.php',
    'PhabricatorMediaWikiAuthProvider' => 'src/oauth/PhabricatorMediaWikiAuthProvider.php',
    'PhabricatorMilestoneNavProfileMenuItem' => 'src/panel/PhabricatorMilestoneNavProfileMenuItem.php',
    'PhutilMediaWikiAuthAdapter' => 'src/oauth/PhutilMediaWikiAuthAdapter.php',
    'PolicyQueryConduitAPIMethod' => 'src/conduit/PolicyQueryConduitAPIMethod.php',
    'ProjectBurnupGraphProfileMenuItem' => 'src/panel/ProjectBurnupGraphProfileMenuItem.php',
    'ProjectOpenTasksProfileMenuItem' => 'src/panel/ProjectOpenTasksProfileMenuItem.php',
    'ReleaseDetailsCustomField' => 'src/customfields/ReleaseDetailsCustomField.php',
    'SecurityPolicyEnforcerAction' => 'src/policy/SecurityPolicyEnforcerAction.php',
    'SetSubtypeHeraldAction' => 'src/herald/SetSubtypeHeraldAction.php',
    'UserTransactionsConduitAPIMethod' => 'src/conduit/UserTransactionsQueryConduitAPIMethod.php',
    'WMFEscalateTaskController' => 'src/policy/WMFLockTaskController.php',
    'WMFEscalateTaskEventListener' => 'src/policy/WMFLockTaskEventListener.php',
    'WMFExtensionsApplication' => 'src/policy/WMFExtensionsApplication.php',
    'WMFSecurityPolicy' => 'src/policy/WMFSecurityPolicy.php',
    'WMFSubscribersPolicyRule' => 'src/policy/WMFSubscribersPolicyRule.php',
    'WmfConfigSource' => 'src/other/WmfConfigSource.php',
  ),
  'function' => array(),
  'xmap' => array(
    'CreatePolicyConduitAPIMethod' => 'ConduitAPIMethod',
    'CustomLoginHandler' => 'PhabricatorAuthLoginHandler',
    'DeadlineEditEngineSubtype' => 'PhabricatorEditEngineSubtype',
    'DifferentialApplyPatchWithOnlyGitField' => 'DifferentialCustomField',
    'ElasticsearchApplication' => 'PhabricatorApplication',
    'GerritApplication' => 'PhabricatorApplication',
    'GerritChangeIdField' => 'PhabricatorCommitCustomField',
    'GerritProjectController' => 'PhabricatorController',
    'GerritProjectListController' => 'GerritProjectController',
    'GoGetMetaRepositoryExtension' => 'DiffusionRepositoryExtension',
    'LDAPUserQueryConduitAPIMethod' => 'UserConduitAPIMethod',
    'LDAPUserpageCustomField' => 'PhabricatorUserCustomField',
    'MediaWikiUserQueryConduitAPIMethod' => 'UserConduitAPIMethod',
    'MediaWikiUserpageCustomField' => 'PhabricatorUserCustomField',
    'PhabricatorElasticSearchBackendSetting' => 'PhabricatorSelectSetting',
    'PhabricatorMediaWikiAuthProvider' => 'PhabricatorOAuth1AuthProvider',
    'PhabricatorMilestoneNavProfileMenuItem' => 'PhabricatorProfileMenuItem',
    'PhutilMediaWikiAuthAdapter' => 'PhutilOAuth1AuthAdapter',
    'PolicyQueryConduitAPIMethod' => 'ConduitAPIMethod',
    'ProjectBurnupGraphProfileMenuItem' => 'PhabricatorProfileMenuItem',
    'ProjectOpenTasksProfileMenuItem' => 'PhabricatorProfileMenuItem',
    'ReleaseDetailsCustomField' => array(
      'ManiphestCustomField',
      'PhabricatorStandardCustomFieldInterface',
    ),
    'SecurityPolicyEnforcerAction' => 'HeraldAction',
    'SetSubtypeHeraldAction' => 'HeraldAction',
    'UserTransactionsConduitAPIMethod' => 'ConduitAPIMethod',
    'WMFEscalateTaskController' => 'PhabricatorController',
    'WMFEscalateTaskEventListener' => 'PhabricatorEventListener',
    'WMFExtensionsApplication' => 'PhabricatorApplication',
    'WMFSubscribersPolicyRule' => 'PhabricatorPolicyRule',
    'WmfConfigSource' => 'PhabricatorConfigSiteSource',
  ),
));

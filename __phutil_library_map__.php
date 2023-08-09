<?php

/**
 * This file is automatically generated. Use 'arc liberate' to rebuild it.
 *
 * @generated
 * @phutil-library-version 2
 */
phutil_register_library_map( [
  '__library_version__' => 2,
  'class' => [
	'AdminChangeAnyFileVisibilityController' => 'src/admin/AdminChangeAnyFileVisibilityController.php',
	'AdminChangeFileVisibilityController' => 'src/admin/AdminChangeFileVisibilityController.php',
	'AdminManageObjectsApplication' => 'src/admin/AdminManageObjectsApplication.php',
	'GitHubAccountCustomField' => 'src/customfields/GitHubAccountCustomField.php',
	'MediaWikiUserpageCustomField' => 'src/customfields/MediaWikiUserpageCustomField.php',
	'PhutilCustomProxy' => 'src/proxy/PhutilCustomProxy.php',
	'PhabricatorMediaWikiAuthProvider' => 'src/oauth/PhabricatorMediaWikiAuthProvider.php',
	'PhutilMediaWikiAuthAdapter' => 'src/oauth/PhutilMediaWikiAuthAdapter.php',
	'ProjectOpenTasksProfileMenuItem' => 'src/panel/ProjectOpenTasksProfileMenuItem.php',
	'RollbackTransactionsWorkflow' => 'src/workflow/RollbackTransactionsWorkflow.php',
	'MirahezeCLIWorkflow' => 'src/workflow/MFRollbackWorkflow.php',
	'clog' => 'scripts/init/init-script.php',
  ],
  'function' => [
	'init_script' => 'scripts/init/init-script.php',
  ],
  'xmap' => [
	'AdminChangeAnyFileVisibilityController' => 'PhabricatorController',
	'AdminManageObjectsApplication' => 'PhabricatorApplication',
	'AdminChangeFileVisibilityController' => 'PhabricatorController',
	'GitHubAccountCustomField' => 'PhabricatorUserCustomField',
	'MediaWikiUserpageCustomField' => 'PhabricatorUserCustomField',
	'PhutilCustomProxy' => 'PhutilHTTPEngineExtension',
	'PhutilMediaWikiAuthAdapter' => 'PhutilOAuth1AuthAdapter',
	'ProjectOpenTasksProfileMenuItem' => 'PhabricatorProfileMenuItem',
	'RollbackTransactionsWorkflow' => 'MirahezeCLIWorkflow',
	'MirahezeCLIWorkflow' => 'PhabricatorManagementWorkflow',
  ],
] );

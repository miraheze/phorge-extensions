<?php
final class GitHubIntegrationApplication extends PhabricatorApplication {

	public function getName() {
		return pht( 'GitHub Integration' );
	}

	public function getShortDescription() {
		return pht( 'Link pull requests to tasks' );
	}

	public function getIcon() {
		return 'fa-github';
	}

	public function getApplicationGroup() {
		return self::GROUP_UTILITIES;
	}

	public function getRoutes() {
		return [
			'/github/' => [
				'webhook/' => 'GitHubWebhookController',
			],
		];
	}
}

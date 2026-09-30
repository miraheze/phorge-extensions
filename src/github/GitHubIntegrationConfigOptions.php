<?php

final class GitHubIntegrationConfigOptions
	extends PhabricatorApplicationConfigOptions {

	public function getName() {
		return pht( 'GitHub Integration' );
	}

	public function getDescription() {
		return pht( 'Configure how GitHub pull requests are linked to tasks.' );
	}

	public function getIcon() {
		return 'fa-github';
	}

	public function getGroup() {
		return 'apps';
	}

	public function getApplicationClassName() {
		return GitHubIntegrationApplication::class;
	}

	public function getOptions() {
		return [
			$this->newOption( 'github.webhook-secret', 'string', null )
				->setHidden( true )
				->setDescription(
					pht(
						'Shared secret used to verify webhook deliveries. ' .
						'Leave empty to disable the webhook endpoint.'
					)
				),
			$this->newOption( 'github.api-token', 'string', null )
				->setHidden( true )
				->setDescription(
					pht(
						'Optional token for reading public pull request data. ' .
						'Only needed if the unauthenticated rate limit is too low.'
					)
				),
			$this->newOption( 'github.bot-username', 'string', null )
				->addExample( 'GitHubBot', pht( 'Bot account' ) )
				->setDescription(
					pht(
						'Username of the bot account that posts comments and ' .
						'closes tasks. Comments are skipped if it does not exist.'
					)
				),
			$this->newOption( 'github.allowed-owners', 'list<string>', [] )
				->addExample( [ 'miraheze' ], pht( 'Single organization' ) )
				->setDescription(
					pht(
						'GitHub users and organizations whose repositories may ' .
						'be linked to tasks.'
					)
				),
		];
	}
}

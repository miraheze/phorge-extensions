<?php

final class GitHubWebhookController extends PhabricatorController {

	private const PULL_ACTIONS = [
		'opened',
		'reopened',
		'closed',
		'synchronize',
		'edited',
		'ready_for_review',
	];

	private const MAX_COMMITS = 10;

	// GitHub sends the push and the pull request events together, and its API lags slightly behind both.
	private const PUSH_SETTLE_SECONDS = 3;

	private $botPHID = false;
	private $assignees = [];
	private $lastFailure = '';

	public function shouldRequireLogin() {
		return false;
	}

	/**
	 * @phutil-external-symbol class PhabricatorStartup
	 */
	public function handleRequest( AphrontRequest $request ) {
		$secret = PhabricatorEnv::getEnvConfig( 'github.webhook-secret' );
		if ( !phutil_nonempty_string( $secret ) ) {
			return new Aphront404Response();
		}

		if ( !$request->isHTTPPost() ) {
			return new Aphront400Response();
		}

		$raw = PhabricatorStartup::getRawInput();
		$signature = AphrontRequest::getHTTPHeader( 'X-Hub-Signature-256' );
		$expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
		if ( !phutil_nonempty_string( $signature ) || !hash_equals( $expected, $signature ) ) {
			return new Aphront403Response();
		}

		$event = AphrontRequest::getHTTPHeader( 'X-GitHub-Event' );
		if ( $event === 'ping' ) {
			return $this->newTextResponse( 200, 'pong' );
		}

		if ( $event !== 'pull_request' && $event !== 'push' ) {
			return $this->newTextResponse( 200, 'ignored event' );
		}

		try {
			$payload = phutil_json_decode( $raw );
		} catch ( PhutilJSONParserException $ex ) {
			return new Aphront400Response();
		}

		$repository = (string)idxv( $payload, [ 'repository', 'full_name' ], '' );
		if ( !GitHubPullRequestUtil::isValidRepository( $repository ) ) {
			return new Aphront400Response();
		}

		$allowed = array_map( 'strtolower', PhabricatorEnv::getEnvConfig( 'github.allowed-owners' ) );
		$owner = strtolower( explode( '/', $repository )[0] );
		if ( !in_array( $owner, $allowed, true ) ) {
			return $this->newTextResponse( 200, 'ignored owner' );
		}

		if ( $event === 'push' ) {
			return $this->handlePush( $request, $payload, $repository );
		}

		return $this->handlePullRequest( $request, $payload, $repository );
	}

	private function handlePullRequest( AphrontRequest $request, array $payload, $repository ) {
		$action = idx( $payload, 'action' );
		$number = (int)idx( $payload, 'number', 0 );
		if ( !in_array( $action, self::PULL_ACTIONS, true ) || $number <= 0 ) {
			return $this->newTextResponse( 200, 'ignored action' );
		}

		// The signed payload only tells us where to look. Everything we act on comes from the API.
		[ $code, $pull ] = $this->fetchGitHub( '/repos/' . $repository . '/pulls/' . $number );
		if ( $code === 404 ) {
			return $this->newTextResponse( 200, 'ignored repository' );
		}
		if ( !is_array( $pull ) ) {
			return $this->newTextResponse( 502, 'could not read pull request: ' . $this->lastFailure );
		}

		$base_repo = idxv( $pull, [ 'base', 'repo' ], [] );
		if (
			!is_array( $base_repo ) ||
			(int)idx( $pull, 'number', 0 ) !== $number ||
			strtolower( (string)idx( $base_repo, 'full_name', '' ) ) !== strtolower( $repository ) ||
			idx( $base_repo, 'private' ) !== false
		) {
			return $this->newTextResponse( 200, 'ignored repository' );
		}

		$is_merged = (bool)idx( $pull, 'merged' );
		$is_closed = idx( $pull, 'state' ) === 'closed';

		// Pushes and edits are only useful for linking a task that is not linked yet.
		if ( in_array( $action, [ 'synchronize', 'edited', 'ready_for_review' ], true ) && $is_closed ) {
			return $this->newTextResponse( 200, 'ignored action' );
		}

		[ $code, $commits ] = $this->fetchGitHub(
			'/repos/' . $repository . '/pulls/' . $number . '/commits?per_page=100'
		);
		if ( !is_array( $commits ) ) {
			return $this->newTextResponse( 502, 'could not read commits: ' . $this->lastFailure );
		}

		$corpus = [ (string)idx( $pull, 'title', '' ), (string)idx( $pull, 'body', '' ) ];
		foreach ( $commits as $commit ) {
			if ( is_array( $commit ) ) {
				$corpus[] = (string)idxv( $commit, [ 'commit', 'message' ], '' );
			}
		}

		$merge_sha = (string)idx( $pull, 'merge_commit_sha', '' );
		if ( $is_merged && GitHubPullRequestUtil::isValidSha( $merge_sha ) ) {
			[ $code, $merge_commit ] = $this->fetchGitHub( '/repos/' . $repository . '/commits/' . $merge_sha );
			if ( is_array( $merge_commit ) && idx( $merge_commit, 'sha' ) === $merge_sha ) {
				$corpus[] = (string)idxv( $merge_commit, [ 'commit', 'message' ], '' );
			}
		}

		$references = GitHubPullRequestUtil::parseTaskReferences( implode( "\n\n", $corpus ) );
		if ( !$references ) {
			return $this->newTextResponse( 200, 'no task references' );
		}

		if ( $is_merged ) {
			$state = 'merged';
			$actor = idxv( $pull, [ 'merged_by', 'login' ] );
		} elseif ( $is_closed ) {
			$state = 'closed';
			$actor = idxv( $payload, [ 'sender', 'login' ] );
		} else {
			$state = 'open';
			$actor = null;
		}

		$author = idxv( $pull, [ 'user', 'login' ] );
		$value = [
			'kind' => GitHubPullRequestUtil::KIND_PULL,
			'repo' => $repository,
			'number' => $number,
			'title' => GitHubPullRequestUtil::cleanInline( idx( $pull, 'title', '' ) ),
			'branch' => GitHubPullRequestUtil::cleanInline( idxv( $pull, [ 'base', 'ref' ], '' ), 100 ),
			'author' => GitHubPullRequestUtil::isValidLogin( $author ) ? $author : '',
			'actor' => GitHubPullRequestUtil::isValidLogin( $actor ) ? $actor : '',
			'state' => $state,
			'merge_sha' => $is_merged && GitHubPullRequestUtil::isValidSha( $merge_sha ) ? $merge_sha : '',
		];

		$resolves = $state === 'merged' &&
			idxv( $pull, [ 'base', 'ref' ] ) === idx( $base_repo, 'default_branch' );

		$updated = $this->applyToTasks( $request, $references, $value, $resolves, idxv( $pull, [ 'user', 'id' ] ) );

		return $this->newTextResponse( 200, 'updated ' . $updated );
	}

	private function handlePush( AphrontRequest $request, array $payload, $repository ) {
		$ref = (string)idx( $payload, 'ref', '' );
		if ( idx( $payload, 'deleted' ) || strpos( $ref, 'refs/heads/' ) !== 0 ) {
			return $this->newTextResponse( 200, 'ignored ref' );
		}
		$branch = substr( $ref, strlen( 'refs/heads/' ) );

		if ( $branch !== (string)idxv( $payload, [ 'repository', 'default_branch' ], '' ) ) {
			return $this->newTextResponse( 200, 'ignored branch' );
		}

		// Cheap filter on the signed payload so most pushes cost no API calls.
		$candidates = [];
		foreach ( (array)idx( $payload, 'commits', [] ) as $commit ) {
			$sha = (string)idx( (array)$commit, 'id', '' );
			$message = (string)idx( (array)$commit, 'message', '' );
			if ( GitHubPullRequestUtil::isValidSha( $sha ) && GitHubPullRequestUtil::parseTaskReferences( $message ) ) {
				$candidates[] = $sha;
			}
		}

		if ( !$candidates ) {
			return $this->newTextResponse( 200, 'no task references' );
		}

		[ $code, $repo_data ] = $this->fetchGitHub( '/repos/' . $repository );
		if ( $code === 404 ) {
			return $this->newTextResponse( 200, 'ignored repository' );
		}
		if ( !is_array( $repo_data ) ) {
			return $this->newTextResponse( 502, 'could not read repository: ' . $this->lastFailure );
		}

		if ( idx( $repo_data, 'private' ) !== false || $branch !== idx( $repo_data, 'default_branch' ) ) {
			return $this->newTextResponse( 200, 'ignored repository' );
		}

		sleep( self::PUSH_SETTLE_SECONDS );

		$updated = 0;
		foreach ( array_slice( array_unique( $candidates ), 0, self::MAX_COMMITS ) as $sha ) {
			[ $code, $commit ] = $this->fetchGitHub( '/repos/' . $repository . '/commits/' . $sha );
			if ( !is_array( $commit ) || idx( $commit, 'sha' ) !== $sha ) {
				continue;
			}

			// Commits that arrived through a merged pull request are covered by the pull request event.
			[ $code, $pulls ] = $this->fetchGitHub( '/repos/' . $repository . '/commits/' . $sha . '/pulls' );
			if ( !is_array( $pulls ) ) {
				continue;
			}

			$from_pull = false;
			foreach ( $pulls as $pull ) {
				if ( is_array( $pull ) && ( idx( $pull, 'merged_at' ) || idx( $pull, 'merge_commit_sha' ) === $sha ) ) {
					$from_pull = true;
					break;
				}
			}
			if ( $from_pull ) {
				continue;
			}

			$message = (string)idxv( $commit, [ 'commit', 'message' ], '' );
			$references = GitHubPullRequestUtil::parseTaskReferences( $message );
			if ( !$references ) {
				continue;
			}

			$login = idxv( $commit, [ 'author', 'login' ] );
			$author = GitHubPullRequestUtil::isValidLogin( $login ) ? $login :
				GitHubPullRequestUtil::cleanInline( idxv( $commit, [ 'commit', 'author', 'name' ], '' ), 80 );

			$subject = explode( "\n", trim( $message ), 2 )[0];
			$value = [
				'kind' => GitHubPullRequestUtil::KIND_COMMIT,
				'repo' => $repository,
				'sha' => $sha,
				'title' => GitHubPullRequestUtil::cleanInline( $subject ),
				'branch' => GitHubPullRequestUtil::cleanInline( $branch, 100 ),
				'author' => $author,
				'state' => 'committed',
			];

			$updated += $this->applyToTasks( $request, $references, $value, true, idxv( $commit, [ 'author', 'id' ] ) );
		}

		return $this->newTextResponse( 200, 'updated ' . $updated );
	}

	private function applyToTasks( AphrontRequest $request, array $references, array $value, $resolves, $github_id = null ) {
		$viewer = PhabricatorUser::getOmnipotentUser();
		$tasks = id( new ManiphestTaskQuery() )
			->setViewer( $viewer )
			->withIDs( array_keys( $references ) )
			->execute();

		$bot_phid = $this->getBotPHID();
		$acting_phid = $bot_phid ?: ( new GitHubIntegrationApplication() )->getPHID();
		$key = GitHubPullRequestUtil::getStateKey( $value );

		$unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
		$updated = 0;

		foreach ( $tasks as $task ) {
			$states = GitHubPullRequestUtil::loadLatestStates( $viewer, $task );
			$last = idx( $states, $key );
			if ( $last && idx( $last, 'state' ) === $value['state'] ) {
				continue;
			}

			if ( GitHubPullRequestUtil::isCoveredByMergedPull( $states, $value ) ) {
				continue;
			}

			$xactions = [];

			$xactions[] = id( new ManiphestTransaction() )
				->setTransactionType( GitHubPullRequestTransaction::TRANSACTIONTYPE )
				->setNewValue( $value );

			if ( $bot_phid ) {
				$xactions[] = id( new ManiphestTransaction() )
					->setTransactionType( PhabricatorTransactions::TYPE_COMMENT )
					->attachComment(
						id( new ManiphestTransactionComment() )
							->setContent( GitHubPullRequestUtil::buildCommentBody( $value ) )
					);
			}

			$status = idx( $references, $task->getID() );
			if (
				$resolves &&
				$status !== null &&
				!$task->isClosed() &&
				$task->getStatus() !== $status &&
				ManiphestTaskStatus::isValidStatusConstant( $status )
			) {
				$xactions[] = id( new ManiphestTransaction() )
					->setTransactionType( ManiphestTaskStatusTransaction::TRANSACTIONTYPE )
					->setNewValue( $status );

				// Only unassigned tasks are claimed, so existing owners are never replaced.
				if ( ManiphestTaskStatus::isClosedStatus( $status ) && !$task->getOwnerPHID() ) {
					$assignee = $this->getAssignee( $github_id );
					if (
						$assignee &&
						PhabricatorPolicyFilter::hasCapability( $assignee, $task, PhabricatorPolicyCapability::CAN_VIEW )
					) {
						$xactions[] = id( new ManiphestTransaction() )
							->setTransactionType( ManiphestTaskOwnerTransaction::TRANSACTIONTYPE )
							->setNewValue( $assignee->getPHID() );
					}
				}
			}

			try {
				$task->getApplicationTransactionEditor()
					->setActor( $viewer )
					->setActingAsPHID( $acting_phid )
					->setContentSource( PhabricatorContentSource::newFromRequest( $request ) )
					->setContinueOnNoEffect( true )
					->setContinueOnMissingFields( true )
					->applyTransactions( $task, $xactions );
				$updated++;
			} catch ( Exception $ex ) {
				phlog( $ex );
			}
		}

		unset( $unguarded );

		return $updated;
	}

	private function getAssignee( $github_id ) {
		$key = (string)$github_id;
		if ( !array_key_exists( $key, $this->assignees ) ) {
			$this->assignees[$key] = GitHubPullRequestUtil::loadUserForGitHubID( $github_id );
		}

		return $this->assignees[$key];
	}

	private function getBotPHID() {
		if ( $this->botPHID !== false ) {
			return $this->botPHID;
		}

		$this->botPHID = null;
		$username = PhabricatorEnv::getEnvConfig( 'github.bot-username' );
		if ( phutil_nonempty_string( $username ) ) {
			$user = id( new PhabricatorPeopleQuery() )
				->setViewer( PhabricatorUser::getOmnipotentUser() )
				->withUsernames( [ $username ] )
				->executeOne();
			if ( $user && !$user->getIsDisabled() ) {
				$this->botPHID = $user->getPHID();
			}
		}

		return $this->botPHID;
	}

	private function fetchGitHub( $path ) {
		$future = id( new HTTPSFuture( 'https://api.github.com' . $path ) )
			->setMethod( 'GET' )
			->addHeader( 'Accept', 'application/vnd.github+json' )
			->addHeader( 'X-GitHub-Api-Version', '2022-11-28' )
			->addHeader( 'User-Agent', 'WikiTide-Phorge' )
			->setTimeout( 15 );

		$token = PhabricatorEnv::getEnvConfig( 'github.api-token' );
		if ( phutil_nonempty_string( $token ) ) {
			$future->addHeader( 'Authorization', 'Bearer ' . $token );
		}

		[ $status, $body, $headers ] = $future->resolve();
		$code = $status->getStatusCode();
		if ( $status->isError() ) {
			$this->lastFailure = GitHubPullRequestUtil::cleanInline(
				$status->getMessage() . $this->describeRateLimit( $headers ),
				300
			);
			phlog( 'GitHub webhook request failed: ' . $this->lastFailure );
			return [ $code, null ];
		}

		try {
			return [ $code, phutil_json_decode( $body ) ];
		} catch ( PhutilJSONParserException $ex ) {
			return [ $code, null ];
		}
	}

	private function describeRateLimit( array $headers ) {
		foreach ( $headers as $header ) {
			if ( strtolower( (string)idx( $header, 0 ) ) === 'x-ratelimit-remaining' ) {
				return ' (rate limit remaining: ' . (string)idx( $header, 1 ) . ')';
			}
		}

		return '';
	}

	private function newTextResponse( $code, $text ) {
		return id( new AphrontPlainTextResponse() )
			->setHTTPResponseCode( $code )
			->setContent( $text );
	}
}

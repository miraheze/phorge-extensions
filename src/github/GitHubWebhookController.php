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
	private $tagPHID = false;
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

		// Edits and pushes on a finished pull request can still rename it, but must not link new tasks or resolve any.
		$existing_only = $is_closed && in_array( $action, [ 'synchronize', 'edited', 'ready_for_review' ], true );

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

		// A reference that disappears from a pull request that is still open unlinks the task.
		// Truncated commit or reference lists could hide a reference, so those never unlink.
		$removed = [ 0, 0 ];
		if (
			!$is_closed &&
			count( $commits ) < 100 &&
			count( $references ) < GitHubPullRequestUtil::MAX_TASKS
		) {
			$removed = $this->removeUnreferenced( $value, $references );
		}

		if ( !$references ) {
			if ( !$removed[0] && !$removed[1] ) {
				return $this->newTextResponse( 200, 'no task references' );
			}
			return $this->newResultResponse( $removed[0], $removed[1] );
		}

		$resolves = $state === 'merged' &&
			idxv( $pull, [ 'base', 'ref' ] ) === idx( $base_repo, 'default_branch' );

		[ $updated, $failed ] = $this->applyToTasks(
			$request,
			$references,
			$value,
			$resolves,
			idxv( $pull, [ 'user', 'id' ] ),
			$existing_only
		);

		return $this->newResultResponse( $updated + $removed[0], $failed + $removed[1] );
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
		$failed = 0;
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

			[ $done, $bad ] = $this->applyToTasks( $request, $references, $value, true, idxv( $commit, [ 'author', 'id' ] ) );
			$updated += $done;
			$failed += $bad;
		}

		return $this->newResultResponse( $updated, $failed );
	}

	private function applyToTasks(
		AphrontRequest $request,
		array $references,
		array $value,
		$resolves,
		$github_id = null,
		$existing_only = false
	) {
		$viewer = PhabricatorUser::getOmnipotentUser();
		$tasks = id( new ManiphestTaskQuery() )
			->setViewer( $viewer )
			->withIDs( array_keys( $references ) )
			->execute();

		$bot_phid = $this->getBotPHID();
		$tag_phid = $this->getTagPHID();
		$acting_phid = $bot_phid ?: ( new GitHubIntegrationApplication() )->getPHID();
		$key = GitHubPullRequestUtil::getStateKey( $value );

		$unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
		$updated = 0;
		$failed = 0;
		$is_pull = GitHubPullRequestUtil::getKind( $value ) === GitHubPullRequestUtil::KIND_PULL;
		$source = PhabricatorContentSource::newFromRequest( $request );

		foreach ( $tasks as $task ) {
			$states = GitHubPullRequestUtil::loadLatestStates( $viewer, $task );
			$last = idx( $states, $key );

			if ( $existing_only && ( !$last || idx( $last, 'state' ) === 'removed' ) ) {
				continue;
			}

			if ( $last && idx( $last, 'state' ) === $value['state'] ) {
				// Same state with a new title or base branch, so only the stored row and posted comments change.
				try {
					$changed = false;
					if ( $is_pull && $this->hasDescriptionChanged( $last, $value ) ) {
						$this->applyTransactions( $task, $source, $acting_phid, [ $this->newStateTransaction( $value ) ] );
						$changed = true;
					}
					if ( $is_pull && $bot_phid && $this->syncComments( $task, $source, $value, $bot_phid ) ) {
						$changed = true;
					}
					if ( $changed ) {
						$updated++;
					}
				} catch ( Throwable $ex ) {
					$failed++;
					phlog( $ex );
				}
				continue;
			}

			if ( GitHubPullRequestUtil::isCoveredByMergedPull( $states, $value ) ) {
				continue;
			}

			$xactions = [];

			$xactions[] = $this->newStateTransaction( $value );

			if ( $bot_phid ) {
				$xactions[] = id( new ManiphestTransaction() )
					->setTransactionType( PhabricatorTransactions::TYPE_COMMENT )
					->attachComment(
						id( new ManiphestTransactionComment() )
							->setContent( GitHubPullRequestUtil::buildCommentBody( $value ) )
					);
			}

			if ( $tag_phid ) {
				$states[$key] = $value;
				$tagged = in_array(
					$tag_phid,
					PhabricatorEdgeQuery::loadDestinationPHIDs(
						$task->getPHID(),
						PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
					),
					true
				);
				$wants_tag = GitHubPullRequestUtil::hasOpenPull( $states );

				if ( $wants_tag !== $tagged ) {
					$xactions[] = id( new ManiphestTransaction() )
						->setTransactionType( PhabricatorTransactions::TYPE_EDGE )
						->setMetadataValue( 'edge:type', PhabricatorProjectObjectHasProjectEdgeType::EDGECONST )
						->setNewValue( [ ( $wants_tag ? '+' : '-' ) => [ $tag_phid => $tag_phid ] ] );
				}
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
				$this->applyTransactions( $task, $source, $acting_phid, $xactions );
				if ( $is_pull && $bot_phid ) {
					$this->syncComments( $task, $source, $value, $bot_phid );
				}
				$updated++;
			} catch ( Throwable $ex ) {
				$failed++;
				phlog( $ex );
			}
		}

		unset( $unguarded );

		return [ $updated, $failed ];
	}

	private function newResultResponse( $updated, $failed ) {
		// A failed save fails the delivery, so it can be found and redelivered from GitHub.
		if ( $failed > 0 ) {
			return $this->newTextResponse( 503, 'failed to save ' . $failed . ' task(s), updated ' . $updated );
		}

		return $this->newTextResponse( 200, 'updated ' . $updated );
	}

	private function newStateTransaction( array $value ) {
		return id( new ManiphestTransaction() )
			->setTransactionType( GitHubPullRequestTransaction::TRANSACTIONTYPE )
			->setNewValue( $value );
	}

	private function hasDescriptionChanged( array $last, array $value ) {
		return (string)idx( $last, 'title', '' ) !== (string)idx( $value, 'title', '' ) ||
			(string)idx( $last, 'branch', '' ) !== (string)idx( $value, 'branch', '' );
	}

	private function applyTransactions( ManiphestTask $task, $source, $acting_phid, array $xactions ) {
		$task->getApplicationTransactionEditor()
			->setActor( PhabricatorUser::getOmnipotentUser() )
			->setActingAsPHID( $acting_phid )
			->setContentSource( $source )
			->setContinueOnNoEffect( true )
			->setContinueOnMissingFields( true )
			->applyTransactions( $task, $xactions );
	}

	// Rewrites the subject line in the bot comments already posted for this pull request.
	private function syncComments( ManiphestTask $task, $source, array $value, $bot_phid ) {
		$uri = GitHubPullRequestUtil::getURI( $value );
		$subject = '%%%' . GitHubPullRequestUtil::buildSubjectLine( $value ) . '%%%';
		$pattern = '/\A(.*\n)%%%[^\n]*%%%(\n' . preg_quote( $uri, '/' ) . ')\z/s';

		$xactions = id( new ManiphestTransactionQuery() )
			->setViewer( PhabricatorUser::getOmnipotentUser() )
			->withObjectPHIDs( [ $task->getPHID() ] )
			->withTransactionTypes( [ PhabricatorTransactions::TYPE_COMMENT ] )
			->withAuthorPHIDs( [ $bot_phid ] )
			->execute();

		$edited = false;
		foreach ( $xactions as $xaction ) {
			$comment = $xaction->getComment();
			if ( !$comment || $comment->getIsRemoved() ) {
				continue;
			}

			$content = (string)$comment->getContent();
			if ( !preg_match( $pattern, $content, $matches ) ) {
				continue;
			}

			$new_content = $matches[1] . $subject . $matches[2];
			if ( $new_content === $content ) {
				continue;
			}

			id( new PhabricatorApplicationTransactionCommentEditor() )
				->setActor( PhabricatorUser::getOmnipotentUser() )
				->setActingAsPHID( $comment->getAuthorPHID() )
				->setContentSource( $source )
				->setRequest( $this->getRequest() )
				->setCancelURI( $task->getURI() )
				->applyEdit(
					$xaction,
					$xaction->getApplicationTransactionCommentObject()->setContent( $new_content )
				);

			$edited = true;
		}

		return $edited;
	}

	/**
	 * Marks the pull request as removed on tasks that track it but no longer have a reference to them.
	 * Only meant for pull requests that are still open. Returns [ updated, failed ].
	 */
	private function removeUnreferenced( array $value, array $references ) {
		$viewer = PhabricatorUser::getOmnipotentUser();
		$key = GitHubPullRequestUtil::getStateKey( $value );

		$xactions = id( new ManiphestTransactionQuery() )
			->setViewer( $viewer )
			->withTransactionTypes( [ GitHubPullRequestTransaction::TRANSACTIONTYPE ] )
			->execute();

		$latest = [];
		foreach ( msort( $xactions, 'getID' ) as $xaction ) {
			$stored = $xaction->getNewValue();
			if (
				is_array( $stored ) &&
				GitHubPullRequestUtil::isValidValue( $stored ) &&
				GitHubPullRequestUtil::getStateKey( $stored ) === $key
			) {
				$latest[$xaction->getObjectPHID()] = $stored;
			}
		}

		$phids = [];
		foreach ( $latest as $phid => $stored ) {
			if ( idx( $stored, 'state' ) !== 'removed' ) {
				$phids[] = $phid;
			}
		}

		if ( !$phids ) {
			return [ 0, 0 ];
		}

		$tasks = id( new ManiphestTaskQuery() )
			->setViewer( $viewer )
			->withPHIDs( $phids )
			->execute();

		$removed = $value;
		$removed['state'] = 'removed';
		$removed['actor'] = '';
		$removed['merge_sha'] = '';

		$bot_phid = $this->getBotPHID();
		$tag_phid = $this->getTagPHID();
		$acting_phid = $bot_phid ?: ( new GitHubIntegrationApplication() )->getPHID();
		$source = PhabricatorContentSource::newFromRequest( $this->getRequest() );

		$unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
		$updated = 0;
		$failed = 0;

		foreach ( $tasks as $task ) {
			if ( array_key_exists( $task->getID(), $references ) ) {
				continue;
			}

			try {
				$xactions = [ $this->newStateTransaction( $removed ) ];

				if ( $bot_phid ) {
					$xactions[] = id( new ManiphestTransaction() )
						->setTransactionType( PhabricatorTransactions::TYPE_COMMENT )
						->attachComment(
							id( new ManiphestTransactionComment() )
								->setContent( GitHubPullRequestUtil::buildCommentBody( $removed ) )
						);
				}

				if ( $tag_phid ) {
					$states = GitHubPullRequestUtil::loadLatestStates( $viewer, $task );
					$states[$key] = $removed;
					$tagged = in_array(
						$tag_phid,
						PhabricatorEdgeQuery::loadDestinationPHIDs(
							$task->getPHID(),
							PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
						),
						true
					);

					if ( $tagged && !GitHubPullRequestUtil::hasOpenPull( $states ) ) {
						$xactions[] = id( new ManiphestTransaction() )
							->setTransactionType( PhabricatorTransactions::TYPE_EDGE )
							->setMetadataValue( 'edge:type', PhabricatorProjectObjectHasProjectEdgeType::EDGECONST )
							->setNewValue( [ '-' => [ $tag_phid => $tag_phid ] ] );
					}
				}

				$this->applyTransactions( $task, $source, $acting_phid, $xactions );
				$updated++;
			} catch ( Throwable $ex ) {
				$failed++;
				phlog( $ex );
			}
		}

		unset( $unguarded );

		return [ $updated, $failed ];
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

	private function getTagPHID() {
		if ( $this->tagPHID !== false ) {
			return $this->tagPHID;
		}

		$this->tagPHID = null;
		$name = trim( (string)PhabricatorEnv::getEnvConfig( 'github.open-pr-tag' ) );
		if ( $name !== '' ) {
			$query = id( new PhabricatorProjectQuery() )
				->setViewer( PhabricatorUser::getOmnipotentUser() );

			if ( preg_match( '/^PHID-PROJ-[a-z0-9]+$/', $name ) ) {
				$query->withPHIDs( [ $name ] );
			} else {
				$query->withSlugs( [ PhabricatorSlug::normalizeProjectSlug( ltrim( $name, '#' ) ) ] );
			}

			$project = $query->executeOne();
			if ( $project && !$project->isArchived() ) {
				$this->tagPHID = $project->getPHID();
			}
		}

		return $this->tagPHID;
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

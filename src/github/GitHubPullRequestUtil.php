<?php

final class GitHubPullRequestUtil extends Phobject {

	public const KIND_PULL = 'pr';
	public const KIND_COMMIT = 'commit';

	private const MAX_TASKS = 10;

	public static function isValidRepository( $name ) {
		return (bool)preg_match(
			'/^[A-Za-z0-9_.-]{1,100}\/[A-Za-z0-9_.-]{1,100}$/',
			(string)$name
		);
	}

	public static function isValidLogin( $login ) {
		return (bool)preg_match(
			'/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})(?:\[bot\])?$/',
			(string)$login
		);
	}

	public static function isValidSha( $sha ) {
		return (bool)preg_match( '/^[0-9a-f]{40}$/', (string)$sha );
	}

	public static function getKind( array $value ) {
		return idx( $value, 'kind' ) === self::KIND_COMMIT ? self::KIND_COMMIT : self::KIND_PULL;
	}

	public static function getPullRequestURI( $repository, $number ) {
		return 'https://github.com/' . $repository . '/pull/' . (int)$number;
	}

	public static function getCommitURI( $repository, $sha ) {
		return 'https://github.com/' . $repository . '/commit/' . $sha;
	}

	public static function getURI( array $value ) {
		$repository = idx( $value, 'repo' );
		if ( self::getKind( $value ) === self::KIND_COMMIT ) {
			return self::getCommitURI( $repository, idx( $value, 'sha' ) );
		}
		return self::getPullRequestURI( $repository, idx( $value, 'number' ) );
	}

	public static function getShortSha( $sha ) {
		return substr( (string)$sha, 0, 10 );
	}

	public static function isValidValue( array $value ) {
		if ( !self::isValidRepository( idx( $value, 'repo' ) ) ) {
			return false;
		}
		if ( self::getKind( $value ) === self::KIND_COMMIT ) {
			return self::isValidSha( idx( $value, 'sha' ) );
		}
		return (int)idx( $value, 'number' ) > 0;
	}

	public static function getStateKey( array $value ) {
		$repository = strtolower( $value['repo'] );
		if ( self::getKind( $value ) === self::KIND_COMMIT ) {
			return $repository . '@' . $value['sha'];
		}
		return $repository . '#' . (int)$value['number'];
	}

	// Keeps stored text safe inside a remarkup literal on a single line.
	public static function cleanInline( $text, $length = 240 ) {
		$text = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string)$text );
		$text = trim( str_replace( '%%%', '%%', $text ) );

		return id( new PhutilUTF8StringTruncator() )
			->setMaximumGlyphs( $length )
			->truncateString( $text );
	}

	public static function buildSubjectLine( array $value ) {
		return '[' . $value['repo'] . '@' . idx( $value, 'branch', '' ) . '] ' .
			idx( $value, 'title', '' );
	}

	public static function buildCommentBody( array $value ) {
		$actor = (string)idx( $value, 'actor', '' );
		$author = (string)idx( $value, 'author', '' );
		$number = (int)idx( $value, 'number', 0 );

		if ( self::getKind( $value ) === self::KIND_COMMIT ) {
			$head = 'Commit ' . self::getShortSha( $value['sha'] ) . ' pushed' .
				( strlen( $author ) ? ' by ' . $author : '' ) . ':';
		} else {
			switch ( idx( $value, 'state' ) ) {
				case 'merged':
					$head = 'Pull request #' . $number . ' **merged**' .
						( strlen( $actor ) ? ' by ' . $actor : '' ) . ':';
					break;
				case 'closed':
					$head = 'Pull request #' . $number . ' **closed**' .
						( strlen( $actor ) ? ' by ' . $actor : '' ) . ' without merging:';
					break;
				default:
					$head = 'Pull request #' . $number . ' **opened**' .
						( strlen( $author ) ? ' by ' . $author : '' ) . ':';
					break;
			}
		}

		return $head . "\n%%%" . self::buildSubjectLine( $value ) . "%%%\n" . self::getURI( $value );
	}

	public static function getKeywordMap() {
		$map = ManiphestTaskStatus::getStatusPrefixMap();
		$closed = ManiphestTaskStatus::getDefaultClosedStatus();

		$builtin = [ 'close', 'closes', 'closed', 'fix', 'fixes', 'fixed', 'resolve', 'resolves', 'resolved' ];
		foreach ( $builtin as $keyword ) {
			if ( !array_key_exists( $keyword, $map ) ) {
				$map[$keyword] = $closed;
			}
		}

		return $map;
	}

	/**
	 * Finds the active user who linked the given GitHub account, if any.
	 */
	public static function loadUserForGitHubID( $github_id ) {
		$github_id = (string)$github_id;
		if ( !preg_match( '/^[1-9]\d{0,19}$/', $github_id ) ) {
			return null;
		}

		$viewer = PhabricatorUser::getOmnipotentUser();

		$configs = id( new PhabricatorAuthProviderConfigQuery() )
			->setViewer( $viewer )
			->withProviderClasses( [ PhabricatorGitHubAuthProvider::class ] )
			->execute();
		if ( !$configs ) {
			return null;
		}

		$accounts = id( new PhabricatorExternalAccountQuery() )
			->setViewer( $viewer )
			->withProviderConfigPHIDs( mpull( $configs, 'getPHID' ) )
			->withRawAccountIdentifiers( [ $github_id ] )
			->execute();

		$user_phids = array_unique( array_filter( mpull( $accounts, 'getUserPHID' ) ) );
		if ( count( $user_phids ) !== 1 ) {
			return null;
		}

		$user = id( new PhabricatorPeopleQuery() )
			->setViewer( $viewer )
			->withPHIDs( $user_phids )
			->executeOne();
		if ( !$user || $user->getIsSystemAgent() || !$user->isUserActivated() ) {
			return null;
		}

		return $user;
	}

	/**
	 * Returns a map of task ID to the status the reference asks for.
	 * References that only link a task map to null.
	 */
	public static function parseTaskReferences( $corpus ) {
		$prefix_map = self::getKeywordMap();
		$prefixes = array_keys( $prefix_map );
		foreach ( [ 'bug', 'bugs', 'task', 'tasks', 'issue', 'issues' ] as $extra ) {
			$prefixes[] = $extra;
		}

		usort(
			$prefixes,
			static function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		$quoted = [];
		foreach ( array_unique( $prefixes ) as $prefix ) {
			$quoted[] = preg_quote( (string)$prefix, '/' );
		}

		$separator = '(?:[ \t]*:[ \t]*|[ \t]+)';
		$pattern = '/(?:^|\b)(' . implode( '|', $quoted ) . ')' . $separator .
			'(?:(?:tasks?|issues?|bugs?)' . $separator . ')?' .
			'(T\d{1,8}(?:(?:[ \t]*[,;&][ \t]*|[ \t]+and[ \t]+|[ \t]+)T\d{1,8})*)\b/im';

		if ( !preg_match_all( $pattern, (string)$corpus, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$result = [];
		foreach ( $matches as $match ) {
			$status = idx( $prefix_map, phutil_utf8_strtolower( $match[1] ) );
			preg_match_all( '/T(\d{1,8})/', $match[2], $ids );
			foreach ( $ids[1] as $id ) {
				$id = (int)$id;
				if ( $id <= 0 ) {
					continue;
				}
				if ( !array_key_exists( $id, $result ) || ( $result[$id] === null && $status !== null ) ) {
					$result[$id] = $status;
				}
			}
		}

		return array_slice( $result, 0, self::MAX_TASKS, true );
	}

	/**
	 * Returns the newest recorded entry for each pull request or commit on a task.
	 */
	public static function loadLatestStates( PhabricatorUser $viewer, ManiphestTask $task ) {
		if ( !$task->getPHID() ) {
			return [];
		}

		$xactions = id( new ManiphestTransactionQuery() )
			->setViewer( $viewer )
			->withObjectPHIDs( [ $task->getPHID() ] )
			->withTransactionTypes( [ GitHubPullRequestTransaction::TRANSACTIONTYPE ] )
			->execute();

		$states = [];
		foreach ( msort( $xactions, 'getID' ) as $xaction ) {
			$value = $xaction->getNewValue();
			if ( !is_array( $value ) || !self::isValidValue( $value ) ) {
				continue;
			}

			$key = self::getStateKey( $value );
			unset( $states[$key] );
			$states[$key] = $value + [ 'epoch' => $xaction->getDateCreated() ];
		}

		return $states;
	}
}

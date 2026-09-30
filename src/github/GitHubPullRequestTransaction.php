<?php

final class GitHubPullRequestTransaction extends ManiphestTaskTransactionType {

	public const TRANSACTIONTYPE = 'miraheze:github-pr';

	public function generateOldValue( $object ) {
		return null;
	}

	public function shouldHideForMail() {
		return true;
	}

	public function shouldHideForFeed() {
		return true;
	}

	public function getIcon() {
		return 'fa-code-fork';
	}

	public function getColor() {
		$value = $this->getNewValue();
		if ( GitHubPullRequestUtil::getKind( $value ) === GitHubPullRequestUtil::KIND_COMMIT ) {
			return 'green';
		}

		switch ( idx( $value, 'state' ) ) {
			case 'merged':
				return 'violet';
			case 'closed':
				return 'grey';
			default:
				return 'blue';
		}
	}

	public function getTitle() {
		$value = $this->getNewValue();
		if ( !is_array( $value ) || !GitHubPullRequestUtil::isValidValue( $value ) ) {
			return pht( 'A change was linked to this task.' );
		}

		$actor = (string)idx( $value, 'actor', '' );
		$author = (string)idx( $value, 'author', '' );

		if ( GitHubPullRequestUtil::getKind( $value ) === GitHubPullRequestUtil::KIND_COMMIT ) {
			$link = phutil_tag(
				'a',
				[ 'href' => GitHubPullRequestUtil::getURI( $value ) ],
				GitHubPullRequestUtil::getShortSha( $value['sha'] )
			);
			$first = strlen( $author ) ?
				pht( 'Commit %s pushed by %s:', $link, $author ) :
				pht( 'Commit %s pushed:', $link );
		} else {
			$link = phutil_tag(
				'a',
				[ 'href' => GitHubPullRequestUtil::getURI( $value ) ],
				'#' . (int)$value['number']
			);

			switch ( idx( $value, 'state' ) ) {
				case 'merged':
					$first = strlen( $actor ) ?
						pht( 'Pull request %s merged by %s:', $link, $actor ) :
						pht( 'Pull request %s merged:', $link );
					break;
				case 'closed':
					$first = strlen( $actor ) ?
						pht( 'Pull request %s closed by %s without merging:', $link, $actor ) :
						pht( 'Pull request %s closed without merging:', $link );
					break;
				default:
					$first = strlen( $author ) ?
						pht( 'Pull request %s opened by %s:', $link, $author ) :
						pht( 'Pull request %s opened:', $link );
					break;
			}
		}

		return hsprintf( '%s<br />%s', $first, GitHubPullRequestUtil::buildSubjectLine( $value ) );
	}
}

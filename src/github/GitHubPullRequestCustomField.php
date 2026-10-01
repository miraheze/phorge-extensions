<?php

final class GitHubPullRequestCustomField extends ManiphestCustomField {

	public function shouldUseStorage() {
		return false;
	}

	public function getFieldKey() {
		return 'miraheze:github-pull-requests';
	}

	public function getFieldName() {
		return pht( 'Related Pull Requests' );
	}

	public function shouldAppearInPropertyView() {
		return true;
	}

	public function renderPropertyViewLabel() {
		return pht( 'Related Changes in GitHub' );
	}

	public function getStyleForPropertyView() {
		return 'block';
	}

	public function renderPropertyViewValue( array $handles ) {
		$task = $this->getObject();
		if ( !$task || !$task->getPHID() ) {
			return null;
		}

		$states = GitHubPullRequestUtil::loadLatestStates( $this->getViewer(), $task );
		if ( !$states ) {
			return null;
		}

		$rows = [];
		foreach ( array_reverse( $states ) as $state ) {
			$subject = phutil_tag(
				'a',
				[ 'href' => GitHubPullRequestUtil::getURI( $state ) ],
				GitHubPullRequestUtil::buildSubjectLine( $state )
			);

			$rows[] = [
				$subject,
				(string)idx( $state, 'author', '' ),
				$this->renderStatus( $state ),
			];
		}

		$table = id( new AphrontTableView( $rows ) )
			->setHeaders( [ pht( 'Subject' ), pht( 'Author' ), pht( 'Status' ) ] )
			->setColumnClasses( [ 'wide pri', '', '' ] );

		$uri = GitHubPullRequestUtil::loadLinkURI( $this->getViewer() );
		if ( !$uri ) {
			return $table;
		}

		$hint = phutil_tag(
			'div',
			[ 'style' => 'margin-top: 8px; color: #6b748c;' ],
			[
				phutil_tag( 'a', [ 'href' => $uri ], pht( 'Link your GitHub account' ) ),
				pht( ' to get tasks assigned to you when your fixes are merged.' ),
			]
		);

		return [ $table, $hint ];
	}

	private function renderStatus( array $state ) {
		if ( GitHubPullRequestUtil::getKind( $state ) === GitHubPullRequestUtil::KIND_COMMIT ) {
			$name = pht( 'Committed' );
			$color = 'green';
		} else {
			switch ( idx( $state, 'state' ) ) {
				case 'merged':
					$name = pht( 'Merged' );
					$color = 'violet';
					break;
				case 'closed':
					$name = pht( 'Closed' );
					$color = 'grey';
					break;
				default:
					$name = pht( 'Open' );
					$color = 'blue';
					break;
			}
		}

		return id( new PHUITagView() )
			->setType( PHUITagView::TYPE_SHADE )
			->setColor( $color )
			->setName( $name );
	}
}

<?php

final class ProjectTagLinksCustomField extends ManiphestCustomField {

	public function shouldUseStorage() {
		return false;
	}

	public function getFieldKey() {
		return 'miraheze:project-tag-links';
	}

	public function getFieldName() {
		return pht( 'Helpful Links' );
	}

	public function shouldAppearInPropertyView() {
		return true;
	}

	public function renderPropertyViewLabel() {
		return $this->getFieldName();
	}

	public function renderPropertyViewValue( array $handles ) {
		$task = $this->getObject();
		if ( !$task || !$task->getPHID() ) {
			return null;
		}

		$util = new ProjectTagLinksUtil( $this->getViewer() );
		if ( !$util->hasAnyConfig() ) {
			return null;
		}

		$projects = $this->loadTaskProjects( $task );
		if ( !$projects ) {
			return null;
		}

		$column_names = $util->usesColumns() ? $this->loadColumnNames( $task, $projects ) : [];

		$candidates = [];
		foreach ( $projects as $project ) {
			$candidates = array_merge(
				$candidates,
				$util->collectProjectLinks(
					$util->getProjectKeys( $project ),
					$column_names[$project->getPHID()] ?? []
				)
			);
		}

		$links = [];
		foreach ( $util->filterVisible( $candidates ) as $link ) {
			$links[] = phutil_tag(
				'a',
				[
					'href' => $link['uri'],
					'target' => '_blank',
					'rel' => 'noreferrer',
				],
				$link['label']
			);
		}

		if ( !$links ) {
			return null;
		}

		return phutil_implode_html( phutil_tag( 'br' ), $links );
	}

	private function loadTaskProjects( ManiphestTask $task ) {
		$project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
			$task->getPHID(),
			PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
		);

		if ( !$project_phids ) {
			return [];
		}

		return id( new PhabricatorProjectQuery() )
			->setViewer( $this->getViewer() )
			->withPHIDs( $project_phids )
			->needSlugs( true )
			->execute();
	}

	private function loadColumnNames( ManiphestTask $task, array $projects ) {
		$boards = [];
		foreach ( $projects as $project ) {
			if ( $project->getHasWorkboard() ) {
				$boards[$project->getPHID()] = $project;
			}
		}

		if ( !$boards ) {
			return [];
		}

		$viewer = $this->getViewer();

		$columns = id( new PhabricatorProjectColumnQuery() )
			->setViewer( $viewer )
			->withProjectPHIDs( array_keys( $boards ) )
			->execute();
		$columns = mpull( $columns, null, 'getPHID' );

		$positions = id( new PhabricatorProjectColumnPositionQuery() )
			->setViewer( $viewer )
			->withBoardPHIDs( array_keys( $boards ) )
			->withObjectPHIDs( [ $task->getPHID() ] )
			->execute();

		$positioned = [];
		foreach ( $positions as $position ) {
			$positioned[$position->getBoardPHID()][] = $position->getColumnPHID();
		}

		$names = [];
		foreach ( $boards as $board_phid => $project ) {
			if ( isset( $positioned[$board_phid] ) ) {
				foreach ( $positioned[$board_phid] as $column_phid ) {
					if ( isset( $columns[$column_phid] ) ) {
						$names[$board_phid][] = $columns[$column_phid]->getDisplayName();
					}
				}

				continue;
			}

			foreach ( $columns as $column ) {
				if ( $column->getProjectPHID() === $board_phid && $column->isDefaultColumn() ) {
					$names[$board_phid][] = $column->getDisplayName();
				}
			}
		}

		return $names;
	}
}

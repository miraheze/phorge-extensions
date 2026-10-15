<?php

final class ProjectTagLinksUtil extends Phobject {
	private const VISIBILITY_PUBLIC = 'public';
	private const VISIBILITY_LOGIN = 'login';
	private const VISIBILITY_PROJECTS = 'projects';

	private PhabricatorUser $viewer;
	private array $memberKeys = [];
	private ?array $linkConfig = null;
	private ?array $defaultAccess = null;

	public function __construct( PhabricatorUser $viewer ) {
		$this->viewer = $viewer;
	}

	public function hasAnyConfig() {
		return (bool)$this->loadLinkConfig();
	}

	public function usesColumns() {
		foreach ( $this->loadLinkConfig() as $entries ) {
			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && array_key_exists( 'columns', $entry ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function buildTaskLinks( ManiphestTask $task ) {
		if ( !$task->getPHID() || !$this->hasAnyConfig() ) {
			return [];
		}

		$projects = $this->loadTaskProjects( $task );
		if ( !$projects ) {
			return [];
		}

		$column_names = $this->usesColumns() ? $this->loadColumnNames( $task, $projects ) : [];

		$candidates = [];
		foreach ( $projects as $project ) {
			$candidates = array_merge(
				$candidates,
				$this->collectProjectLinks(
					$this->getProjectKeys( $project ),
					$column_names[$project->getPHID()] ?? []
				)
			);
		}

		return $this->filterVisible( $candidates );
	}

	public function getProjectKeys( PhabricatorProject $project ) {
		$keys = [];
		foreach ( $this->keyVariants( $project->getName() ) as $variant ) {
			$keys[$variant] = true;
		}

		foreach ( $project->getSlugs() as $slug ) {
			foreach ( $this->keyVariants( $slug->getSlug() ) as $variant ) {
				$keys[$variant] = true;
			}
		}

		return $keys;
	}

	public function collectProjectLinks( array $project_keys, ?array $column_names = null ) {
		if ( $column_names !== null ) {
			$column_names = array_map( [ $this, 'normalizeKey' ], $column_names );
		}

		$candidates = [];
		foreach ( $this->loadLinkConfig() as $key => $entries ) {
			if ( !$this->hasKey( $project_keys, $key ) ) {
				continue;
			}

			foreach ( $this->buildCandidates( $entries ) as $candidate ) {
				if ( $this->matchesColumns( $candidate['columns'], $column_names ) ) {
					$candidates[] = $candidate;
				}
			}
		}

		return $candidates;
	}

	public function filterVisible( array $candidates ) {
		if ( !$candidates ) {
			return [];
		}

		$this->loadMemberKeys( $candidates );

		$links = [];
		foreach ( $candidates as $candidate ) {
			$uri = $candidate['uri'];
			if ( isset( $links[$uri] ) || !$this->canView( $candidate['access'] ) ) {
				continue;
			}

			$links[$uri] = [
				'label' => $candidate['label'],
				'uri' => $uri,
				'icon' => $candidate['icon'],
			];
		}

		return array_values( $links );
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
			->setViewer( $this->viewer )
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

		$columns = id( new PhabricatorProjectColumnQuery() )
			->setViewer( $this->viewer )
			->withProjectPHIDs( array_keys( $boards ) )
			->execute();
		$columns = mpull( $columns, null, 'getPHID' );

		$positions = id( new PhabricatorProjectColumnPositionQuery() )
			->setViewer( $this->viewer )
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

	private function buildCandidates( array $entries ) {
		$candidates = [];
		foreach ( $entries as $label => $entry ) {
			$candidate = $this->buildCandidate( (string)$label, $entry );
			if ( $candidate !== null ) {
				$candidates[] = $candidate;
			}
		}

		return $candidates;
	}

	private function buildCandidate( $label, $entry ) {
		$default_access = $this->getDefaultAccess();

		$columns = null;
		$icon = null;

		if ( is_string( $entry ) ) {
			$target = $entry;
			$access = $default_access;
		} elseif ( is_array( $entry ) ) {
			$target = $entry['url'] ?? null;
			$access = $this->parseAccess( $entry, $default_access );
			$columns = $this->parseColumns( $entry );
			$icon = $this->parseIcon( $entry );
		} else {
			return null;
		}

		$uri = $this->resolveTarget( $target );
		if ( $uri === null ) {
			return null;
		}

		return [
			'label' => $label,
			'uri' => $uri,
			'access' => $access,
			'columns' => $columns,
			'icon' => $icon,
		];
	}

	private function parseIcon( array $spec ) {
		$icon = $spec['icon'] ?? null;
		if ( !is_string( $icon ) || !preg_match( '/^fa-[a-z0-9-]+$/', $icon ) ) {
			return null;
		}

		return $icon;
	}

	private function parseColumns( array $spec ) {
		if ( !array_key_exists( 'columns', $spec ) ) {
			return null;
		}

		$columns = $spec['columns'];
		if ( is_string( $columns ) ) {
			$columns = [ $columns ];
		}

		if ( !is_array( $columns ) ) {
			return [];
		}

		$names = [];
		foreach ( $columns as $column ) {
			if ( is_string( $column ) && $this->normalizeKey( $column ) !== '' ) {
				$names[] = $this->normalizeKey( $column );
			}
		}

		return $names;
	}

	private function matchesColumns( ?array $wanted, ?array $actual ) {
		if ( $wanted === null ) {
			return true;
		}

		if ( $actual === null ) {
			return false;
		}

		return (bool)array_intersect( $wanted, $actual );
	}

	private function loadLinkConfig() {
		if ( $this->linkConfig !== null ) {
			return $this->linkConfig;
		}

		$this->linkConfig = [];

		$raw = PhabricatorEnv::getEnvConfig( 'projecttaglinks.links' );
		if ( !is_array( $raw ) ) {
			return $this->linkConfig;
		}

		foreach ( $raw as $tag => $entries ) {
			$key = $this->normalizeKey( $tag );
			if ( $key === '' || !is_array( $entries ) ) {
				continue;
			}

			$this->linkConfig[$key] = array_merge( $this->linkConfig[$key] ?? [], $entries );
		}

		return $this->linkConfig;
	}

	private function getDefaultAccess() {
		if ( $this->defaultAccess === null ) {
			$this->defaultAccess = $this->parseAccess(
				PhabricatorEnv::getEnvConfig( 'projecttaglinks.default-access' ),
				$this->newAccess( self::VISIBILITY_PUBLIC )
			);
		}

		return $this->defaultAccess;
	}

	private function newAccess( $visibility, array $projects = [] ) {
		return [
			'visibility' => $visibility,
			'projects' => $projects,
		];
	}

	private function parseAccess( $spec, array $fallback ) {
		if ( !is_array( $spec ) ) {
			return $fallback;
		}

		$visibility = $spec['visibility'] ?? null;
		$projects = $spec['projects'] ?? null;

		if ( $visibility === null && $projects === null ) {
			return $fallback;
		}

		if ( $visibility === null ) {
			$visibility = self::VISIBILITY_PROJECTS;
		}

		$visibility = is_string( $visibility ) ? $this->normalizeKey( $visibility ) : '';

		if ( $visibility === self::VISIBILITY_PUBLIC || $visibility === self::VISIBILITY_LOGIN ) {
			return $this->newAccess( $visibility );
		}

		if ( $visibility !== self::VISIBILITY_PROJECTS ) {
			return $this->newAccess( self::VISIBILITY_PROJECTS );
		}

		if ( is_string( $projects ) ) {
			$projects = [ $projects ];
		}

		if ( !is_array( $projects ) ) {
			return $this->newAccess( self::VISIBILITY_PROJECTS );
		}

		$names = [];
		foreach ( $projects as $project ) {
			if ( is_string( $project ) && $this->normalizeKey( $project ) !== '' ) {
				$names[] = trim( $project );
			}
		}

		return $this->newAccess( self::VISIBILITY_PROJECTS, $names );
	}

	private function canView( array $access ) {
		switch ( $access['visibility'] ) {
			case self::VISIBILITY_PUBLIC:
				return true;
			case self::VISIBILITY_LOGIN:
				return $this->viewer->isLoggedIn();
			case self::VISIBILITY_PROJECTS:
				foreach ( $access['projects'] as $name ) {
					if ( $this->hasKey( $this->memberKeys, $name ) ) {
						return true;
					}
				}
				return false;
			default:
				return false;
		}
	}

	private function loadMemberKeys( array $candidates ) {
		$this->memberKeys = [];

		if ( !$this->viewer->isLoggedIn() ) {
			return;
		}

		$names = [];
		foreach ( $candidates as $candidate ) {
			if ( $candidate['access']['visibility'] === self::VISIBILITY_PROJECTS ) {
				foreach ( $candidate['access']['projects'] as $name ) {
					$names[$name] = $name;
				}
			}
		}

		if ( !$names ) {
			return;
		}

		$names = array_values( $names );
		$by_name = id( new PhabricatorProjectQuery() )
			->setViewer( $this->viewer )
			->withNames( $names )
			->needSlugs( true )
			->execute();
		$by_slug = id( new PhabricatorProjectQuery() )
			->setViewer( $this->viewer )
			->withSlugs( $names )
			->needSlugs( true )
			->execute();

		foreach ( array_merge( $by_name, $by_slug ) as $project ) {
			if ( $project->isUserMember( $this->viewer->getPHID() ) ) {
				$this->memberKeys += $this->getProjectKeys( $project );
			}
		}
	}

	private function hasKey( array $keys, $value ) {
		foreach ( $this->keyVariants( $value ) as $variant ) {
			if ( isset( $keys[$variant] ) ) {
				return true;
			}
		}

		return false;
	}

	private function keyVariants( $value ) {
		$lower = $this->normalizeKey( $value );
		if ( $lower === '' ) {
			return [];
		}

		return array_unique( [ $lower, PhabricatorSlug::normalizeProjectSlug( $lower ) ] );
	}

	private function resolveTarget( $target ) {
		if ( !is_string( $target ) ) {
			return null;
		}

		$target = trim( $target );
		if ( $target === '' ) {
			return null;
		}

		if ( !preg_match( '#^https?://#i', $target ) ) {
			$base = PhabricatorEnv::getEnvConfig( 'mediawikilinks.base' );
			if ( !phutil_nonempty_string( $base ) ) {
				return null;
			}

			$target = $base . str_replace( ' ', '_', $target );
		}

		if ( !preg_match( '#^https?://#i', $target ) ) {
			return null;
		}

		return $target;
	}

	private function normalizeKey( $value ) {
		return phutil_utf8_strtolower( trim( (string)$value ) );
	}
}

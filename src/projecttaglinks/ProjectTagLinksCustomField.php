<?php

final class ProjectTagLinksCustomField extends ManiphestCustomField {
	private const VISIBILITY_PUBLIC = 'public';
	private const VISIBILITY_LOGIN = 'login';
	private const VISIBILITY_PROJECTS = 'projects';

	private array $memberKeys;

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

		$config = $this->loadLinkConfig();
		if ( !$config ) {
			return null;
		}

		$tag_keys = $this->loadTagKeys( $task );
		if ( !$tag_keys ) {
			return null;
		}

		$default_access = $this->parseAccess(
			PhabricatorEnv::getEnvConfig( 'projecttaglinks.default-access' ),
			$this->newAccess( self::VISIBILITY_PUBLIC )
		);

		$candidates = [];
		foreach ( $config as $key => $entries ) {
			if ( !$this->hasKey( $tag_keys, $key ) ) {
				continue;
			}

			foreach ( $entries as $label => $entry ) {
				$candidate = $this->buildCandidate( (string)$label, $entry, $default_access );
				if ( $candidate !== null ) {
					$candidates[] = $candidate;
				}
			}
		}

		if ( !$candidates ) {
			return null;
		}

		$this->loadMemberKeys( $candidates );

		$links = [];
		foreach ( $candidates as $candidate ) {
			$uri = $candidate['uri'];
			if ( isset( $links[$uri] ) || !$this->canView( $candidate['access'] ) ) {
				continue;
			}

			$links[$uri] = phutil_tag(
				'a',
				[
					'href' => $uri,
					'target' => '_blank',
					'rel' => 'noreferrer',
				],
				$candidate['label']
			);
		}

		if ( !$links ) {
			return null;
		}

		return phutil_implode_html( phutil_tag( 'br' ), array_values( $links ) );
	}

	private function buildCandidate( $label, $entry, array $default_access ) {
		if ( is_string( $entry ) ) {
			$target = $entry;
			$access = $default_access;
		} elseif ( is_array( $entry ) ) {
			$target = $entry['url'] ?? null;
			$access = $this->parseAccess( $entry, $default_access );
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
		];
	}

	private function loadLinkConfig() {
		$raw = PhabricatorEnv::getEnvConfig( 'projecttaglinks.links' );
		if ( !is_array( $raw ) ) {
			return [];
		}

		$config = [];
		foreach ( $raw as $tag => $entries ) {
			if ( !is_array( $entries ) ) {
				continue;
			}

			$key = $this->normalizeKey( $tag );
			if ( $key === '' ) {
				continue;
			}

			$config[$key] = array_merge( $config[$key] ?? [], $entries );
		}

		return $config;
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
				return $this->getViewer()->isLoggedIn();
			case self::VISIBILITY_PROJECTS:
				foreach ( $access['projects'] as $name ) {
					if ( $this->hasKey( $this->memberKeys ?? [], $name ) ) {
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

		$viewer = $this->getViewer();
		if ( !$viewer->isLoggedIn() ) {
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
			->setViewer( $viewer )
			->withNames( $names )
			->needSlugs( true )
			->execute();
		$by_slug = id( new PhabricatorProjectQuery() )
			->setViewer( $viewer )
			->withSlugs( $names )
			->needSlugs( true )
			->execute();

		foreach ( array_merge( $by_name, $by_slug ) as $project ) {
			if ( $project->isUserMember( $viewer->getPHID() ) ) {
				$this->memberKeys += $this->buildProjectKeys( $project );
			}
		}
	}

	private function loadTagKeys( ManiphestTask $task ) {
		$project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
			$task->getPHID(),
			PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
		);

		if ( !$project_phids ) {
			return [];
		}

		$projects = id( new PhabricatorProjectQuery() )
			->setViewer( $this->getViewer() )
			->withPHIDs( $project_phids )
			->needSlugs( true )
			->execute();

		$keys = [];
		foreach ( $projects as $project ) {
			$keys += $this->buildProjectKeys( $project );
		}

		return $keys;
	}

	private function buildProjectKeys( PhabricatorProject $project ) {
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

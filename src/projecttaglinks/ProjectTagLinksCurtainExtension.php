<?php

final class ProjectTagLinksCurtainExtension extends PHUICurtainExtension {
	public const EXTENSIONKEY = 'custom.project-tag-links';

	private const KIND_GITHUB = 'github';
	private const KIND_WIKI = 'wiki';
	private const KIND_OTHER = 'other';

	private const KIND_ICONS = [
		self::KIND_GITHUB => 'fa-github',
		self::KIND_WIKI => 'fa-book',
		self::KIND_OTHER => 'fa-external-link',
	];

	public function shouldEnableForObject( $object ) {
		return ( $object instanceof ManiphestTask );
	}

	public function getExtensionApplicationClass() {
		return PhabricatorManiphestApplication::class;
	}

	public function buildCurtainPanel( $object ) {
		$util = new ProjectTagLinksUtil( $this->getViewer() );

		$links = $util->buildTaskLinks( $object );
		if ( !$links ) {
			return null;
		}

		require_celerity_resource( 'project-tag-links-css', 'mf-ext-misc' );

		$rows = [];
		foreach ( $links as $link ) {
			$rows[] = $this->renderRow( $link );
		}

		return $this->newPanel()
			->setHeaderText( pht( 'Helpful Links' ) )
			->setOrder( 50000 )
			->appendChild( phutil_tag( 'div', [ 'class' => 'ptl' ], $rows ) );
	}

	private function renderRow( array $link ) {
		$host = (string)parse_url( $link['uri'], PHP_URL_HOST );
		$kind = $this->getKind( $host );
		$icon = $link['icon'] ?? self::KIND_ICONS[$kind];

		if ( strncmp( $host, 'www.', 4 ) === 0 ) {
			$host = substr( $host, 4 );
		}

		return phutil_tag(
			'a',
			[
				'class' => 'ptl-row',
				'href' => $link['uri'],
				'target' => '_blank',
				'rel' => 'noreferrer',
			],
			[
				phutil_tag(
					'span',
					[ 'class' => 'ptl-tile ptl-tile-' . $kind ],
					id( new PHUIIconView() )->setIcon( $icon )
				),
				phutil_tag(
					'span',
					[ 'class' => 'ptl-text' ],
					[
						phutil_tag( 'span', [ 'class' => 'ptl-title' ], $link['label'] ),
						phutil_tag( 'span', [ 'class' => 'ptl-host' ], $host ),
						phutil_tag( 'span', [ 'class' => 'ptl-sr' ], pht( ' (opens in a new tab)' ) ),
					]
				),
			]
		);
	}

	private function getKind( $host ) {
		$host = strtolower( $host );

		if ( $host === 'github.com' || substr( $host, -11 ) === '.github.com' ) {
			return self::KIND_GITHUB;
		}

		$base_host = (string)parse_url(
			(string)PhabricatorEnv::getEnvConfig( 'mediawikilinks.base' ),
			PHP_URL_HOST
		);

		if (
			( $host !== '' && $host === strtolower( $base_host ) ) ||
			substr( $host, -13 ) === '.miraheze.org'
		) {
			return self::KIND_WIKI;
		}

		return self::KIND_OTHER;
	}
}

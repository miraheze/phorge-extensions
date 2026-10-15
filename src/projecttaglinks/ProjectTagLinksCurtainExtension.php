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

		$rows = [];
		foreach ( $links as $link ) {
			$rows[] = $this->renderRow( $link );
		}

		return $this->newPanel()
			->setHeaderText( pht( 'Helpful Links' ) )
			->setOrder( 50000 )
			->appendChild( [
				$this->renderStyle(),
				phutil_tag( 'div', [ 'class' => 'ptl' ], $rows ),
			] );
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

	private function renderStyle() {
		$css = <<<'EOT'
.ptl { overflow: hidden; margin: 2px 0 4px; border: 1px solid rgba(18, 48, 63, 0.14); border-radius: 10px; font-size: 13px; line-height: 1.35; }
.ptl .phui-icon-view { font-size: inherit; line-height: 1; }
.ptl .ptl-tile .phui-icon-view { color: #fff; }
.ptl-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

.ptl a.ptl-row { display: flex; align-items: center; gap: 11px; padding: 9px 11px; color: #12303f; text-decoration: none; }
.ptl a.ptl-row + a.ptl-row { border-top: 1px solid rgba(18, 48, 63, 0.09); }
.ptl a.ptl-row:hover,
.ptl a.ptl-row:focus { background: rgba(15, 98, 119, 0.06); color: #0f6277; text-decoration: none; }
.ptl a.ptl-row:focus-visible { outline: 2px solid #0f6277; outline-offset: -2px; }

.ptl-tile { flex: 0 0 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 15px; }
.ptl-tile-github { background: #24292f; }
.ptl-tile-wiki { background: #0f6277; }
.ptl-tile-other { background: #5d7080; }

.ptl-text { flex: 1; min-width: 0; }
.ptl-title { display: block; font-weight: 600; }
.ptl-host { display: block; margin-top: 1px; font-size: 12px; color: #5d7080; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
EOT;

		return phutil_tag( 'style', [], phutil_safe_html( $css ) );
	}
}

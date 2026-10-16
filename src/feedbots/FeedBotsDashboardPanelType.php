<?php

final class FeedBotsDashboardPanelType extends PhabricatorDashboardPanelType {
	private const LIMIT = 40;
	private const FETCH_LIMIT = 200;

	public function getPanelTypeKey() {
		return 'custom.feed-bots';
	}

	public function getPanelTypeName() {
		return pht( 'Recent Activity with Bot Toggle' );
	}

	public function getIcon() {
		return 'fa-newspaper-o';
	}

	public function getPanelTypeDescription() {
		return pht( 'Show recent activity with bot accounts hidden unless the viewer turns them on.' );
	}

	public function shouldRenderAsync() {
		return false;
	}

	protected function newEditEngineFields( PhabricatorDashboardPanel $panel ) {
		return [];
	}

	public function renderPanelContent(
		PhabricatorUser $viewer,
		PhabricatorDashboardPanel $panel,
		PhabricatorDashboardPanelRenderingEngine $engine
	) {
		$limit = (int)$panel->getProperty( 'limit', self::LIMIT );
		if ( $limit < 1 ) {
			$limit = self::LIMIT;
		}

		$stories = id( new PhabricatorFeedQuery() )
			->setViewer( $viewer )
			->setLimit( self::FETCH_LIMIT )
			->execute();

		$bot_phids = $this->loadBotPHIDs( $viewer );

		$groups = [];
		$shown = 0;
		$hidden = 0;
		foreach ( $stories as $story ) {
			$is_bot = isset( $bot_phids[$story->getAuthorPHID()] );
			if ( !$is_bot ) {
				if ( $shown >= $limit ) {
					break;
				}
				$shown++;
			} else {
				$hidden++;
			}

			$date = ucfirst( phabricator_relative_date( $story->getEpoch(), $viewer ) );
			$groups[$date][] = $this->renderStory( $viewer, $story, $is_bot );
		}

		$toggle_id = celerity_generate_unique_node_id();

		$rows = [];
		foreach ( $groups as $date => $items ) {
			$rows[] = phutil_tag(
				'div',
				[ 'class' => 'fb-group' ],
				[
					id( new PHUIHeaderView() )
						->setHeader( $date )
						->setHeaderIcon( 'fa-calendar msr' ),
					$items,
				]
			);
		}

		if ( !$rows ) {
			$rows[] = phutil_tag( 'div', [ 'class' => 'fb-empty' ], pht( 'No Stories.' ) );
		}

		$label = $hidden ?
			pht( 'Show bot activity (%d)', $hidden ) :
			pht( 'Show bot activity' );

		return phutil_tag(
			'div',
			[ 'class' => 'fb', 'style' => 'position: relative' ],
			[
				$this->renderStyle(),
				phutil_tag( 'input', [ 'type' => 'checkbox', 'id' => $toggle_id, 'class' => 'fb-toggle' ] ),
				phutil_tag( 'label', [ 'for' => $toggle_id, 'class' => 'fb-label' ], $label ),
				phutil_tag( 'div', [ 'class' => 'fb-list phabricator-feed-frame' ], $rows ),
			]
		);
	}

	private function renderStory( PhabricatorUser $viewer, PhabricatorFeedStory $story, $is_bot ) {
		try {
			$view = $story->renderView();
			$view->setViewer( $viewer );
			$view = $view->render();
		} catch ( Exception $ex ) {
			$view = pht( 'Feed Story Failed to Render (%s)', get_class( $story ) );
		}

		return phutil_tag(
			'div',
			[ 'class' => $is_bot ? 'fb-bot' : 'fb-human' ],
			$view
		);
	}

	private function loadBotPHIDs( PhabricatorUser $viewer ) {
		$bots = id( new PhabricatorPeopleQuery() )
			->setViewer( $viewer )
			->withIsSystemAgent( true )
			->execute();

		return array_fill_keys( mpull( $bots, 'getPHID' ), true );
	}

	private function renderStyle() {
		$css = <<<'EOT'
.fb-toggle { position: absolute; opacity: 0; pointer-events: none; }
.fb-label { display: flex; align-items: center; gap: 8px; margin: 0; padding: 10px 4px 6px; font-size: 13px; line-height: 1; color: #5d7080; cursor: pointer; user-select: none; }
.fb-label::before { content: ''; flex: 0 0 15px; height: 15px; box-sizing: border-box; border: 1px solid #8a99a6; border-radius: 4px; background: #fff; }
.fb-label:hover::before { border-color: #0f6277; }
.fb-toggle:checked + .fb-label::before { border-color: #0f6277; background: #0f6277 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 8.5l3 3 6-7' fill='none' stroke='%23fff' stroke-width='2'/%3E%3C/svg%3E") center / 11px no-repeat; }
.fb-toggle:focus-visible + .fb-label::before { outline: 2px solid #0f6277; outline-offset: 2px; }
.fb-toggle:not(:checked) ~ .fb-list .fb-bot { display: none; }
.fb-toggle:not(:checked) ~ .fb-list .fb-group:not(:has(.fb-human)) { display: none; }
.fb-empty { padding: 12px; color: #5d7080; }
EOT;

		return phutil_tag( 'style', [], phutil_safe_html( $css ) );
	}
}

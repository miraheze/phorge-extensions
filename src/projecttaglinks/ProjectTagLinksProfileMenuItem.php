<?php

final class ProjectTagLinksProfileMenuItem extends PhabricatorProfileMenuItem {
	public const MENUITEMKEY = 'custom.project-tag-links';

	public function getMenuItemTypeIcon() {
		return 'fa-book';
	}

	public function getMenuItemTypeName() {
		return pht( 'Helpful Links' );
	}

	public function canAddToObject( $object ) {
		return ( $object instanceof PhabricatorProject );
	}

	public function getDisplayName(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		return pht( 'Helpful Links' );
	}

	protected function newMenuItemViewList(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		$viewer = $this->getViewer();
		$object = $config->getProfileObject();

		if ( !( $object instanceof PhabricatorProject ) ) {
			return [];
		}

		$project = id( new PhabricatorProjectQuery() )
			->setViewer( $viewer )
			->withPHIDs( [ $object->getPHID() ] )
			->needSlugs( true )
			->executeOne();

		if ( !$project ) {
			return [];
		}

		$util = new ProjectTagLinksUtil( $viewer );
		$links = $util->filterVisible(
			$util->collectProjectLinks( $util->getProjectKeys( $project ) )
		);

		if ( !$links ) {
			return [];
		}

		$items = [
			$this->newItemView()
				->setName( $this->getDisplayName( $config ) )
				->setIsLabel( true ),
		];

		foreach ( $links as $link ) {
			$items[] = $this->newItemView()
				->setURI( $link['uri'] )
				->setName( $link['label'] )
				->setIcon( 'fa-external-link' )
				->setIsExternalLink( true );
		}

		return $items;
	}

	public function buildEditEngineFields(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		return [
			id( new PhabricatorInstructionsEditField() )->setValue(
				pht(
					'This adds the helpful links configured for this project, ' .
					"based on its name or hashtag. Nothing is shown when there are no " .
					"links or when you are not allowed to see any of them.\n\n"
				)
			),
		];
	}
}

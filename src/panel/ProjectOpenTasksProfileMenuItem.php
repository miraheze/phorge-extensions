<?php

// Source: https://raw.githubusercontent.com/wikimedia/phabricator-extensions/wmf/stable/src/panel/ProjectOpenTasksProfileMenuItem.php

final class ProjectOpenTasksProfileMenuItem extends PhabricatorProfileMenuItem {
	public const MENUITEMKEY = 'custom.open-tasks';

	public function getMenuItemTypeIcon() {
		return 'fa-anchor';
	}

	public function getMenuItemTypeName() {
		return pht( 'Link to Open Tasks' );
	}

	public function canAddToObject(
		$object
	) {
		return ( $object instanceof PhabricatorProject );
	}

	public function getDisplayName(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		return pht( 'Open Tasks' );
	}

	protected function newMenuItemViewList(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		$object = $config->getProfileObject();

		$uri = '/maniphest/?project=' . $object->getPHID() . '&statuses=open()&group=none&order=newest#R';

		$item = $this->newItemView()
			->setURI( $uri )
			->setName( $this->getDisplayName( $config ) )
			->setIcon( 'fa-anchor' );

		return [
			$item,
		];
	}

	public function buildEditEngineFields(
		PhabricatorProfileMenuItemConfiguration $config
	) {
		return [
			id( new PhabricatorInstructionsEditField() )->setValue(
				pht(
					'This adds a link to search maniphest for open tasks which are ' .
					"tagged with this project.\n\n"
				)
			),
		];
	}
}

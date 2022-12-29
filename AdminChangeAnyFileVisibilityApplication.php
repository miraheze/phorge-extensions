<?php

class AdminChangeAnyFileVisibilityApplication extends PhabricatorApplication {
	public function getName() {
		return pht( 'Change File Visibility' );
	}

	public function getBaseURI() {
		return '/file/';
	}

	public function getIconName() {
		return 'file';
	}

	public function getShortDescription() {
		return pht( 'Allow admins to change the visibility of any file' );
	}

	public function getApplicationGroup() {
		return self::GROUP_UTILITIES;
	}

	public function getRoutes() {
		return [
			'/file/' => [
				'' => 'AdminChangeAnyFileVisibilityController',
				'(?P<id>\d+)/' => 'AdminChangeAnyFileVisibilityController',
				'path/(?P<path>.+)/' => 'AdminChangeAnyFileVisibilityController',
				'visibility/(?P<id>\d+)/' => 'AdminChangeFileVisibilityController',
			],
		];
	}

	public function buildApplicationMenu() {
		$menu = parent::buildApplicationMenu();

		$viewer = $this->getViewer();
		if ( $viewer->getIsAdmin() ) {
			$item = id( new PHUIListItemView() )
				->setName( pht( 'Change Any File Visibility' ) )
				->setIcon( 'fa-file' )
				->setHref( '/file/' );
			$menu->addMenuItem( $item );
		}

		return $menu;
	}
}

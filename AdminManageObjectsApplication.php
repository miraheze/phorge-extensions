<?php

class AdminManageObjectsApplication extends PhabricatorApplication {
	public function getName() {
		return pht( 'Admin Manage Objects' );
	}

	public function getBaseURI() {
		return '/admin/';
	}

	public function getIconName() {
		return 'admin';
	}

	public function getShortDescription() {
		return pht( 'Allow admins to change the visibility of objects they have no access to' );
	}

	public function getApplicationGroup() {
		return self::GROUP_UTILITIES;
	}

	public function getRoutes() {
		return [
			'/admin/' => [
				'file/' => 'AdminChangeAnyFileVisibilityController',
				'file/(?P<id>\d+)/' => 'AdminChangeAnyFileVisibilityController',
				'file/path/(?P<path>.+)/' => 'AdminChangeAnyFileVisibilityController',
				'file/visibility/' => 'AdminChangeFileVisibilityController',
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
				->setHref( '/admin/file/' );
			$menu->addMenuItem( $item );
		}

		return $menu;
	}
}

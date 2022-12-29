<?php

abstract class AdminManageObjectsController extends PhabricatorController {

	public function buildApplicationMenu() {
		$menu = parent::buildApplicationMenu();

		$viewer = $this->getViewer();
		if ( $viewer->getIsAdmin() ) {
			$item = id( new PHUIListItemView() )
				->setName( pht( 'Change Any File Visibility' ) )
				->setIcon( 'fa-file' )
				->setHref( $this->getApplicationURI( 'file/' ) );

			$menu->addMenuItem( $item );
		}

		return $menu;
	}
}

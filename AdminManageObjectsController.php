<?php

class AdminManageObjectsController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		return id( new PhabricatorFileSearchEngine() )
			->setController( $this )
			->buildResponse();
	}

	public function buildApplicationMenu() {
		$menu = $this->newApplicationMenu()
			->setSearchEngine( new PhabricatorFileSearchEngine() );

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

<?php

class AdminManageObjectsController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		return id( new PhabricatorFileSearchEngine() )
			->setController( $this )
			->buildResponse();
	}

	public function buildApplicationMenu() {
		$viewer = $this->getViewer();

		$nav = id( new AphrontSideNavFilterView() )
			->setBaseURI( new PhutilURI( $this->getApplicationURI() ) )
			->setViewer( $viewer );

		if ( $viewer->getIsAdmin() ) {
			$item = id( new PHUIListItemView() )
				->setName( pht( 'Change Any File Visibility' ) )
				->setIcon( 'fa-file' )
				->setHref( $this->getApplicationURI( 'file/' ) );

			$nav->addMenuItem( $item );
		}

		return $nav->getMenu();
	}
}

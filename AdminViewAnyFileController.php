<?php

class AdminViewAnyFileController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		$viewer = $this->getViewer();

		if ( !$viewer->getIsAdmin() ) {
			return new Aphront403Response();
		}

		$id = $request->getInt( 'id' );
		$path = $request->getStr( 'path' );

		if ( !$id && !$path ) {
			return new Aphront400Response();
		}

		$file = null;
		if ( $id ) {
			$file = id( new PhabricatorFile() )
				->loadOneWhere( 'id = %d', $id );
		} elseif ( $path ) {
			$file = id( new PhabricatorFile() )
				->loadOneWhere( 'name = %s', $path );
		}

		if ( !$file ) {
			return new Aphront404Response();
		}

		$title = pht( 'View File: %s', $file->getName() );

		$header = id( new PHUIHeaderView() )
			->setHeader( $title );

		$download_button = id( new PHUIButtonView() )
			->setTag( 'a' )
			->setText( pht( 'Download File' ) )
			->setHref( $file->getBestURI() )
			->setIcon( 'fa-download' )
			->setWorkflow( true );

		$file_data = $file->loadFileData();
		$content = phutil_tag(
			'pre',
			[],
			$file_data
		);

		$primary_object_phid = $file->getPHID();
		$properties = id( new PHUIPropertyListView() )
			->setUser( $viewer )
			->setObject( $file )
			->setActionList( $actions )
			->setHeader( $header );

		$properties->addAction( $download_button );

		$crumbs = $this->buildApplicationCrumbs();
		$crumbs->addTextCrumb( $title );
		$crumbs->setBorder( true );

		$timeline = $this->buildTransactionTimeline(
			$file,
			new PhabricatorFileTransactionQuery()
		);

		$timeline->setShouldTerminate( true );
		$object_box = id( new PHUIObjectBoxView() )
			->setHeader( $header )
			->setProperties( $properties );

		$view = id( new PHUITwoColumnView() )
			->setHeader( $header )
			->setFooter( [
				$object_box,
				$timeline,
			]
		);

		return $this->newPage()
			->setTitle( $title )
			->setCrumbs( $crumbs )
			->appendChild( $view );
	}
}

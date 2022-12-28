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

		// Create a download button
		$download_button = id( new PHUIButtonView() )
			->setTag( 'a' )
			->setText( pht( 'Download File' ) )
			->setHref( $file->getBestURI() )
			->setIcon( 'fa-download' )
			->setWorkflow( true );

		// Display the file contents
		$content = phutil_tag(
			'pre',
			[],
			$file->loadFileData()
		);

		$view = id( new PHUITwoColumnView() )
			->setHeader( $header )
			->setMainColumn( $content );

		$view->addAction( $download_button );

		return $this->newPage()
			->setTitle( $title )
			->appendChild( $view );
	}
}

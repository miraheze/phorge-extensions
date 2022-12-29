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

		$file->setViewPolicy( PhabricatorPolicies::POLICY_PUBLIC );

		$title = pht( 'View File: %s', $file->getName() );

		$header = id( new PHUIHeaderView() )
			->setHeader( $title );

		$download_button = id( new PHUIButtonView() )
			->setTag( 'a' )
			->setText( pht( 'Download File' ) )
			->setHref( $file->getBestURI() )
			->setIcon( 'fa-download' )
			->setWorkflow( true );

		$form = id( new AphrontFormView() )
			->setUser( $viewer )
			->setMethod( 'POST' )
			->setAction( $this->getApplicationURI( 'visibility/' . $file->getID() . '/' ) )
			->appendChild(
				id( new AphrontFormSelectControl() )
					->setLabel( pht( 'Visibility' ) )
					->setName( 'visibility' )
					->setOptions( [
						PhabricatorPolicies::POLICY_PUBLIC => pht( 'Public' ),
						PhabricatorPolicies::POLICY_USER => pht( 'Logged In Users' ),
						PhabricatorPolicies::POLICY_NOONE => pht( 'Only Me' ),
					] )
					->setValue( $file->getViewPolicy() )
			)
			->appendChild(
				id( new AphrontFormSubmitControl() )
					->setValue( pht( 'Save Visibility' ) )
			);

		$file_data = $file->loadFileData();
		$content = phutil_tag(
			'pre',
			[],
			$file_data
		);

		$left_column = id( new PHUITwoColumnView() )
			->setHeader( $header )
			->setFooter( $form );

		$right_column = id( new PHUITwoColumnView() )
			->appendChild( $download_button )
			->setMainColumn( $content );

		$view = id( new PHUITwoColumnView() )
			->setMainColumn( $left_column )
			->setSideColumn( $right_column );

		return $this->newPage()
			->setTitle( $title )
			->appendChild( $view );
	}

	public function handleChangeVisibilityRequest( AphrontRequest $request ) {
		$viewer = $this->getViewer();

		if ( !$viewer->getIsAdmin() ) {
			return new Aphront403Response();
		}

		$id = $request->getInt( 'id' );

		$file = id( new PhabricatorFile() )
			->loadOneWhere( 'id = %d', $id );

		if ( !$file ) {
			return new Aphront404Response();
		}

		$new_visibility = $request->getStr( 'visibility' );
		if ( !$new_visibility ) {
			return new Aphront400Response();
		}

		$visibility_values = [ 'public', 'private' ];
		if ( !in_array( $new_visibility, $visibility_values ) ) {
			return new Aphront400Response();
		}

		$file->setViewPolicy( $new_visibility );
		$file->save();

		return id( new AphrontRedirectResponse() )
			->setURI( $file->getURI() );
	}
}

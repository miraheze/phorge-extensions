<?php

class AdminChangeAnyFileVisibilityController extends PhabricatorController {

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
						PhabricatorPolicies::POLICY_ADMIN => pht( 'Only Administrators' ),
						PhabricatorPolicies::POLICY_NOONE => pht( 'Only Me' ),
					] )
					->setValue( $file->getViewPolicy() )
			)
			->appendChild(
				id( new AphrontFormTextControl() )
					->setName( 'id' )
					->setValue( $file->getID() )
			);
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

		$column = id( new PHUITwoColumnView() )
			->setHeader( $header )
			->setFooter( $form );

		$view = id( new PHUITwoColumnView() )
			->setMainColumn( $column );

		return $this->newPage()
			->setTitle( $title )
			->appendChild( $view );
	}
}

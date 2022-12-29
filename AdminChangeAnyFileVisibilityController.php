<?php

class AdminChangeAnyFileVisibilityController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		$viewer = $this->getViewer();

		if ( !$viewer->getIsAdmin() ) {
			return new Aphront403Response();
		}

		$id = $request->getInt( 'id' );
		$path = $request->getStr( 'path' );

		$file = null;
		if ( $id ) {
			$file = id( new PhabricatorFile() )
				->loadOneWhere( 'id = %d', $id );
		} elseif ( $path ) {
			$file = id( new PhabricatorFile() )
				->loadOneWhere( 'name = %s', $path );
		}

		$title = pht( 'Change File Visibility: %s', $file ? $file->getName() : '<unknown file>' );

		$header = id( new PHUIHeaderView() )
			->setHeader( $title );

		$form = id( new AphrontFormView() )
			->setUser( $viewer )
			->setMethod( 'POST' )
			->setAction( $this->getApplicationURI( 'file/visibility/' ) )
			->appendChild(
				id( new AphrontFormSelectControl() )
					->setLabel( pht( 'Visibility' ) )
					->setName( 'visibility' )
					->setOptions( [
						PhabricatorPolicies::POLICY_PUBLIC => pht( 'Public' ),
						PhabricatorPolicies::POLICY_USER => pht( 'Logged In Users' ),
						PhabricatorPolicies::POLICY_ADMIN => pht( 'Administrators' ),
						PhabricatorPolicies::POLICY_NOONE => pht( 'Only Me' ),
					] )
					->setValue( $file ? $file->getViewPolicy() : PhabricatorPolicies::POLICY_NOONE )
			)
			->appendChild(
				id( new AphrontFormTextControl() )
					->setLabel( pht( 'PHID' ) )
					->setName( 'id' )
					->setValue( $file ? $file->getID() : '' )
			)
			->appendChild(
				id( new AphrontFormSubmitControl() )
					->setValue( pht( 'Save Visibility' ) )
			);

		$view = id( new PHUITwoColumnView() )
			->setHeader( $header )
			->setFooter( $form );

		return $this->newPage()
			->setTitle( $title )
			->appendChild( $view );
	}
}

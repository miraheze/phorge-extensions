<?php

class AdminChangeFileVisibilityController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		$viewer = $this->getViewer();

		if ( !$viewer->getIsAdmin() ) {
			return new Aphront403Response();
		}

		// $id = $request->getInt( 'id' );

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

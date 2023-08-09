<?php

class AdminChangeFileVisibilityController extends PhabricatorController {

	public function handleRequest( AphrontRequest $request ) {
		$viewer = $this->getViewer();

		if ( !$viewer->getIsAdmin() ) {
			return new Aphront403Response();
		}

		$id = $request->getInt( 'id' );

		if ( !$id ) {
			return new Aphront400Response();
		}

		$file = id( new PhabricatorFile() )
			->loadOneWhere( 'id = %d', $id );

		if ( !$file ) {
			return new Aphront404Response();
		}

		$visibility = $request->getStr( 'visibility' );
		if ( !$visibility ) {
			return new Aphront400Response();
		}

		$visibility_values = [ 'public', 'private', 'admin' ];
		if ( !in_array( $visibility, $visibility_values ) ) {
			return new Aphront400Response();
		}

		$file->setViewPolicy( $visibility );
		$file->save();

		return id( new AphrontRedirectResponse() )
			->setURI( $file->getURI() );
	}
}

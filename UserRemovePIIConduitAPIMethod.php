<?php

final class UserRemovePIIConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
	return 'user.removepii';
  }

  public function getMethodDescription() {
	return pht( 'Remove PII from specified users (admin only).' );
  }

  public function defineParamTypes() {
	return [
	  'username' => 'required string',
	];
  }

  public function defineReturnType() {
	return 'void';
  }

  public function defineErrorTypes() {
	return [
	  'ERR-PERMISSIONS' => pht( 'Only admins can call this method.' ),
	  'ERR-INVALID-PARAMETER' => pht( 'Missing or malformed parameter.' ),
	];
  }

  protected function execute( ConduitAPIRequest $request ) {
	$actor = $request->getUser();
	if ( !$actor->getIsAdmin() ) {
	  throw new ConduitException( 'ERR-PERMISSIONS' );
	}

	$query = new PhabricatorPeopleQuery();
	$query->setViewer( $actor );

	if ( $request->getValueExists( 'username' ) ) {
		$query->withUsernames( [ $request->getValue( 'username' ) ] );
	} else {
		throw id( new ConduitException( 'ERR-INVALID-PARAMETER' ) )
		  ->setErrorDescription(
			  pht( 'You must provide a username' ) );
	}

	$targetUser = $query->executeOne();

	if ( !$targetUser ) {
	  throw id( new ConduitException( 'ERR-INVALID-PARAMETER' ) )
		  ->setErrorDescription(
			  pht( 'The specified username was not found' ) );
	}

	id( new PhabricatorUserEditor() )
		->setActor( $actor )
		->disableUser( $targetUser, true );
  }
}

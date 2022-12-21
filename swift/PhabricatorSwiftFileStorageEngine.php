<?php

final class PhabricatorSwiftFileStorageEngine extends PhabricatorFileStorageEngine {

	/**
	 * This engine identifies as `swift`.
	 */
	public function getEngineIdentifier() {
		return 'swift';
	}

	public function getEnginePriority() {
		return 100;
	}

	public function canWriteFiles() {
		$container = PhabricatorEnv::getEnvConfig( 'storage.swift.container' );
		$account = PhabricatorEnv::getEnvConfig( 'storage.swift.account' );
		$key = PhabricatorEnv::getEnvConfig( 'storage.swift.key' );
		$endpoint = PhabricatorEnv::getEnvConfig( 'storage.swift.endpoint' );

		return ( strlen( $container ) &&
			strlen( $account ) &&
			strlen( $key ) &&
			strlen( $endpoint )
		);
	}

	/**
	 * Writes file data into swift.
	 */
	public function writeFile( $data, array $params ) {
		$object = $this->newSwiftAPI();
		$container = $this->newSwiftAPI();

		$seed = Filesystem::readRandomCharacters( 20 );
		$parts = [];

		$parts[] = substr( $seed, 0, 2 );
		$parts[] = substr( $seed, 2, 2 );
		$parts[] = substr( $seed, 4 );

		$name = implode( '/', $parts );

		AphrontWriteGuard::willWrite();
		$profiler = PhutilServiceProfiler::getInstance();
		$call_id = $profiler->beginServiceCall(
			[
				'type' => 'swift',
				'method' => 'putObject',
			]
		);

		$res = $container
			->setParametersForPutContainer( $name )
			->resolve();

		$res = $object
			->setParametersForPutObject( $name, $data )
			->resolve();

		$profiler->endServiceCall( $call_id, [] );

		return $name;
	}

	/**
	 * Load a stored blob from swift.
	 */
	public function readFile( $handle ) {
		$swift = $this->newSwiftAPI();

		$profiler = PhutilServiceProfiler::getInstance();
		$call_id = $profiler->beginServiceCall(
			[
				'type' => 'swift',
				'method' => 'getObject',
			]
		);

		$result = $swift
			->setParametersForGetObject( $handle )
			->resolve();

		$profiler->endServiceCall( $call_id, [] );

		return $result;
	}

	/**
	 * Delete a blob from swift.
	 */
	public function deleteFile( $handle ) {
		$swift = $this->newSwiftAPI();

		AphrontWriteGuard::willWrite();
		$profiler = PhutilServiceProfiler::getInstance();
		$call_id = $profiler->beginServiceCall(
			[
				'type' => 'swift',
				'method' => 'deleteObject',
			]
		);

		$swift
			->setParametersForDeleteObject( $handle )
			->resolve();

		$profiler->endServiceCall( $call_id, [] );
	}

	/**
	 * Retrieve the swift container name.
	 */
	private function getContainerName() {
		$container = PhabricatorEnv::getEnvConfig( 'storage.swift.container' );
		if ( !$container ) {
			throw new PhabricatorFileStorageConfigurationException(
				pht(
					"No '%s' specified!",
					'storage.swift.container'
				)
			);
		}

		return $container;
	}

	/**
	 * Create a new swift API object.
	 */
	private function newSwiftAPI() {
		$container = PhabricatorEnv::getEnvConfig( 'storage.swift.container' );
		$account = PhabricatorEnv::getEnvConfig( 'storage.swift.account' );
		$key = PhabricatorEnv::getEnvConfig( 'storage.swift.key' );
		$endpoint = PhabricatorEnv::getEnvConfig( 'storage.swift.endpoint' );

		return id( new PhutilSwiftFuture() )
			->setAccount( $account )
			->setSecretKey( new PhutilOpaqueEnvelope( $key ) )
			->setEndpoint( $endpoint )
			->setContainer( $container );
	}
}

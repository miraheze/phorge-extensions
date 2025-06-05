<?php

class PhutilCustomProxy extends PhutilHTTPEngineExtension {
	public const EXTENSIONKEY = 'CustomProxy';

	public function getExtensionName() {
		return pht( 'CustomProxy' );
	}

	public function getHTTPProxyURI( PhutilURI $uri ) {
		return new PhutilURI( 'http://bastion.fsslc.wtnet:8080/' );
	}
}

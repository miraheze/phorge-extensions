<?php

class PhutilCustomProxy extends PhutilHTTPEngineExtension {
	public const EXTENSIONKEY = 'CustomProxy';

	public function getExtensionName() {
		return 'CustomProxy';
	}

	public function getHTTPProxyURI( PhutilURI $uri ) {
		return new PhutilURI( 'http://bastion.wikitide.net:8080/' );
	}
}

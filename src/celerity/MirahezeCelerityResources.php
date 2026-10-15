<?php

final class MirahezeCelerityResources extends CelerityResourcesOnDisk {

	public function getName() {
		return 'mf-ext-misc';
	}

	public function getPathToResources() {
		return dirname( __DIR__, 2 ) . '/webroot/rsrc/';
	}

	public function getPathToMap() {
		return dirname( __DIR__, 2 ) . '/resources/celerity/map.php';
	}
}

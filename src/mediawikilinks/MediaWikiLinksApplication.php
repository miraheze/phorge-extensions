<?php

/**
 * @license http://www.apache.org/licenses/ Apache License 2.0
 */
final class MediaWikiLinksApplication extends PhabricatorApplication {

	public function getName() {
		return pht( 'MediaWiki Links' );
	}

	public function getShortDescription() {
		return pht( 'Use Wikitext Links' );
	}

	public function getIcon() {
		return 'fa-link';
	}

	public function getTitleGlyph() {
		return "\xEF\x83\x81";
  	}

	public function getApplicationGroup() {
		return self::GROUP_UTILITIES;
	}

	public function getRemarkupRules() {
		return [
			new MediaWikiLinksRemarkupRule(),
		];
  	}
}

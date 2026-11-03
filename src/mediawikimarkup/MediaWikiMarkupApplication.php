<?php

final class MediaWikiMarkupApplication extends PhabricatorApplication {

	public function getName() {
		return pht( 'MediaWiki Markup' );
	}

	public function getShortDescription() {
		return pht( 'Use Wikitext formatting and links' );
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
			new MediaWikiNowikiRemarkupRule(),
			new MediaWikiLinkRemarkupRule(),
			new MediaWikiExternalLinkRemarkupRule(),
			new MediaWikiFormatRemarkupRule(),
		];
	}
}

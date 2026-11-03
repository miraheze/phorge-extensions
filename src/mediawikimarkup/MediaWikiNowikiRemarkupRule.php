<?php

final class MediaWikiNowikiRemarkupRule extends PhutilRemarkupRule {

	public function getPriority(): float {
		// Before every other rule, so the contents are never formatted.
		return 50.0;
	}

	public function apply( $text ) {
		return preg_replace_callback(
			'@<nowiki>(.*?)</nowiki>@is',
			[ $this, 'markupNowiki' ],
			$text
		);
	}

	public function markupNowiki( array $matches ) {
		if ( $this->getEngine()->isTextMode() ) {
			return $matches[1];
		}

		return $this->getEngine()->storeText( $matches[1] );
	}
}

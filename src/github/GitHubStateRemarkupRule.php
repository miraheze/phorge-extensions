<?php

final class GitHubStateRemarkupRule extends PhabricatorRemarkupCustomInlineRule {

	public const STATES = [ 'opened', 'merged', 'closed', 'pushed' ];

	public function getPriority() {
		return 1000.0;
	}

	public function apply( $text ) {
		$safe = $text instanceof PhutilSafeHTML;
		$raw = $safe ? $text->getHTMLContent() : $text;
		if ( !is_string( $raw ) ) {
			return $text;
		}

		$raw = preg_replace_callback(
			'/\{gh:(' . implode( '|', self::STATES ) . ')\}/',
			[ $this, 'markupState' ],
			$raw
		);

		return $safe ? phutil_safe_html( $raw ) : $raw;
	}

	protected function markupState( array $matches ) {
		$state = $matches[1];
		if ( $this->getEngine()->isTextMode() ) {
			return $this->getEngine()->storeText( $state );
		}

		$result = phutil_tag(
			'strong',
			[ 'class' => 'gh-state gh-state-' . $state ],
			$state
		);

		return $this->getEngine()->storeText( $result );
	}
}

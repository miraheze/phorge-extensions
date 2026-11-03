<?php

final class MediaWikiExternalLinkRemarkupRule extends PhutilRemarkupRule {

	public function getPriority(): float {
		// Same as the internal link rule, which is before bare URLs are turned into links.
		return 200.0;
	}

	public function apply( $text ) {
		// Handle MediaWiki external links: [https://example.org label]
		return preg_replace_callback(
			'@(?<!\[)\[(https?://[^\s\[\]<>"|]+)(?:[ \t]+([^\[\]\n]+?))?\](?!\])@i',
			[ $this, 'markupExternalLink' ],
			$text
		);
	}

	public function markupExternalLink( array $matches ) {
		$url = $matches[1];
		$name = isset( $matches[2] ) ? trim( $matches[2] ) : '';

		if ( !$this->isFlatText( $url ) || !$this->isFlatText( $name ) ) {
			return $matches[0];
		}

		if ( !strlen( $name ) ) {
			$name = $url;
		}

		$engine = $this->getEngine();
		if ( $engine->isTextMode() ) {
			return $name === $url ? $url : $name . ' <' . $url . '>';
		}

		if ( $engine->getState( 'toc' ) ) {
			return $name;
		}

		return $engine->storeText(
			phutil_tag(
				'a',
				[
					'href' => $url,
					'class' => $this->getRemarkupLinkClass( false ),
					'target' => '_blank',
					'rel' => 'noreferrer',
				],
				$name
			)
		);
	}
}

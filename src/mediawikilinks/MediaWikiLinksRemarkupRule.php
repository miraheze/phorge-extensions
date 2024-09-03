<?php

// Copied from https://we.phorge.it/source/phorge/browse/master/src/infrastructure/markup/markuprule/PhutilRemarkupDocumentLinkRule.php

/**
 * @license http://www.apache.org/licenses/ Apache License 2.0
 */
final class MediaWikiLinksRemarkupRule extends PhutilRemarkupRule {

	public function getPriority(): float {
		// Set to 200.0 since Phriction is set to 175.0: https://we.phorge.it/source/phorge/browse/master/src/applications/phriction/markup/PhrictionRemarkupRule.php;b9ea6f1ce823dcd83a431f5913d9b1fe29dd25bd$8
		return 200.0;
	}

	public function apply( $text ) {
		// Handle mediawiki-style links: [[ href | [name] ]]
		$text = preg_replace_callback(
			'@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]*))?\\]\\]\B@U',
			[ $this, 'markupDocumentLink' ],
			$text
		);

		return $text;
	}

	protected function renderHyperlink( $link, $name ) {
		$engine = $this->getEngine();

		if ( $engine->isTextMode() ) {
			if ( !strlen( $name ) ) {
				return $link;
			}

			return $name . ' <' . $link . '>';
		}

		if ( $engine->getState( 'toc' ) ) {
			return $name;
		}

		return phutil_tag(
			'a',
			[
				'href' => $link,
				'class' => $this->getRemarkupLinkClass( false ),
				'target' => '_blank',
				'rel' => 'noreferrer',
			],
			$name
		);
	}

	protected function doPipeTrick( string $page ): string {
		// Remove leading prefix (e.g. [[w:Egg]])
		$page = preg_replace( '/^:?\\w+:/', '', $page );

		$oldPage = $page;
		// Remove parenthesis
		$page = preg_replace( '/\(.+/', '', $page );

		// Remove commas if parenthesis were not removed
		if ( $oldPage === $page ) {
			$page = preg_replace( '/,.+/', '', $page );
		}

		return trim( $page );
	}

	public function markupDocumentLink( array $matches ) {
		$page = trim( $matches[1] );
		$name = isset( $matches[2] ) ? trim( $matches[2] ) : null;

		if ( !$this->isFlatText( $page ) ) {
			return $matches[0];
		}

		if ( !$this->isFlatText( $name ) ) {
			return $matches[0];
		}

		$urlPrefix = PhabricatorEnv::getEnvConfig( 'mediawikilinks.base' );
		if ( !phutil_nonempty_string( $urlPrefix ) ) {
			return $matches[0];
		}

		$url = $urlPrefix . str_replace( ' ', '_', $page );
		if ( $name === null ) {
			$name = $page;
		} elseif ( $name === '' ) {
			$name = $this->doPipeTrick( $page );
		}

		return $this->getEngine()->storeText( $this->renderHyperlink( $url, $name ) );
	}
}

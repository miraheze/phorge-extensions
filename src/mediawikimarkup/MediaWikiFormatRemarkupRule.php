<?php

final class MediaWikiFormatRemarkupRule extends PhutilRemarkupRule {

	// Wikitext tags and the HTML they become.
	private const TAGS = [
		'b' => 'strong',
		'strong' => 'strong',
		'i' => 'em',
		'em' => 'em',
		's' => 'del',
		'strike' => 'del',
		'del' => 'del',
		'u' => 'u',
		'ins' => 'u',
		'sub' => 'sub',
		'sup' => 'sup',
		'small' => 'small',
		'big' => 'big',
		'mark' => 'mark',
		'kbd' => 'kbd',
		'code' => 'tt',
		'tt' => 'tt',
	];

	public function getPriority(): float {
		// After the built in bold rule, so that ** is gone before single asterisks are looked at.
		return 1001.0;
	}

	public function apply( $text ) {
		if ( $this->getEngine()->isTextMode() ) {
			return $text;
		}

		// The text is escaped before it is matched, so apostrophes and angle brackets are entities here.
		$q = '&#039;';

		$text = $this->replaceHTML(
			'@(?<!' . $q . ')(?:' . $q . '){5}(?!' . $q . ')(.+?)(?<!' . $q . ')(?:' . $q . '){5}(?!' . $q . ')@',
			[ $this, 'markupBoldItalic' ],
			$text
		);

		$text = $this->replaceHTML(
			'@(?<!' . $q . ')(?:' . $q . '){3}(?!' . $q . ')(.+?)(?<!' . $q . ')(?:' . $q . '){3}(?!' . $q . ')@',
			[ $this, 'markupBold' ],
			$text
		);

		$text = $this->replaceHTML(
			'@(?<!' . $q . ')(?:' . $q . '){2}(?!' . $q . ')(.+?)(?<!' . $q . ')(?:' . $q . '){2}(?!' . $q . ')@',
			[ $this, 'markupItalic' ],
			$text
		);

		// Markdown style italics, only when the asterisks hug the text and are not part of a word.
		$text = $this->replaceHTML(
			'@(?<![*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])@',
			[ $this, 'markupItalic' ],
			$text
		);

		$text = $this->replaceHTML(
			'@&lt;(' . implode( '|', array_keys( self::TAGS ) ) . ')&gt;(.+?)&lt;/\1&gt;@is',
			[ $this, 'markupTag' ],
			$text
		);

		return $this->replaceHTML(
			'@&lt;br\s*/?&gt;@i',
			[ $this, 'markupBreak' ],
			$text
		);
	}

	public function markupBoldItalic( array $matches ) {
		return hsprintf( '<strong><em>%s</em></strong>', $matches[1] );
	}

	public function markupBold( array $matches ) {
		if ( $this->getEngine()->isAnchorMode() ) {
			return $matches[1];
		}

		return hsprintf( '<strong>%s</strong>', $matches[1] );
	}

	public function markupItalic( array $matches ) {
		return hsprintf( '<em>%s</em>', $matches[1] );
	}

	public function markupTag( array $matches ) {
		$tag = self::TAGS[strtolower( (string)$matches[1] )];
		$attributes = $tag === 'tt' ? [ 'class' => 'remarkup-monospace' ] : [];

		return phutil_tag( $tag, $attributes, $matches[2] );
	}

	public function markupBreak( array $matches ) {
		return hsprintf( '<br />' );
	}
}

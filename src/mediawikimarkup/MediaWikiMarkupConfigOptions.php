<?php

final class MediaWikiMarkupConfigOptions extends PhabricatorApplicationConfigOptions {

	public function getName() {
		return pht( 'MediaWiki Markup' );
	}

	public function getDescription() {
		return pht( 'Configure MediaWiki-style markup and link URLs.' );
	}

	public function getIcon() {
		return 'fa-links';
	}

	public function getGroup() {
		return 'utilities';
	}

	public function getOptions() {
		return [
			$this->newOption( 'mediawikilinks.base', 'string', null )
				->addExample( 'https://www.mediawiki.org/wiki/', pht( 'MediaWiki wiki' ) )
				->addExample( 'https://meta.miraheze.org/wiki/', pht( 'Miraheze Meta' ) )
				->setDescription(
					pht(
						'URL prefix of where Wikitext-style links go to. The page will ' .
						'be appended to the URL.'
					)
				),
		];
	}
}

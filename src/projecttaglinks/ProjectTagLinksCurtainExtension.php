<?php

final class ProjectTagLinksCurtainExtension extends PHUICurtainExtension {
	public const EXTENSIONKEY = 'miraheze.project-tag-links';

	public function shouldEnableForObject( $object ) {
		return ( $object instanceof ManiphestTask );
	}

	public function getExtensionApplicationClass() {
		return PhabricatorManiphestApplication::class;
	}

	public function buildCurtainPanel( $object ) {
		$util = new ProjectTagLinksUtil( $this->getViewer() );

		$links = [];
		foreach ( $util->buildTaskLinks( $object ) as $link ) {
			$links[] = phutil_tag(
				'a',
				[
					'href' => $link['uri'],
					'target' => '_blank',
					'rel' => 'noreferrer',
				],
				$link['label']
			);
		}

		if ( !$links ) {
			return null;
		}

		return $this->newPanel()
			->setHeaderText( pht( 'Helpful Links' ) )
			->setOrder( 50000 )
			->appendChild( phutil_implode_html( phutil_tag( 'br' ), $links ) );
	}
}

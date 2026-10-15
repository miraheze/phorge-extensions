<?php

final class ProjectTagLinksCurtainExtension extends PHUICurtainExtension {
	public const EXTENSIONKEY = 'custom.project-tag-links';

	public function shouldEnableForObject( $object ) {
		return ( $object instanceof ManiphestTask );
	}

	public function getExtensionApplicationClass() {
		return PhabricatorManiphestApplication::class;
	}

	public function buildCurtainPanel( $object ) {
		$util = new ProjectTagLinksUtil( $this->getViewer() );

		$links = $util->buildTaskLinks( $object );
		if ( !$links ) {
			return null;
		}

		$list = id( new PHUIListView() )
			->setType( PHUIListView::SIDENAV_LIST );

		foreach ( $links as $link ) {
			$list->addMenuItem(
				id( new PHUIListItemView() )
					->setName( $link['label'] )
					->setHref( $link['uri'] )
					->setIcon( 'fa-external-link' )
					->setOpenInNewWindow( true )
					->setRel( 'noreferrer' )
			);
		}

		return $this->newPanel()
			->setHeaderText( pht( 'Helpful Links' ) )
			->setOrder( 50 )
			->appendChild( $list );
	}
}

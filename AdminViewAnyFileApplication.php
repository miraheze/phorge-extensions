<?php

class AdminViewAnyFileApplication extends PhabricatorApplication
{
	public function getName()
	{
		return pht('View Any File');
	}

	public function getBaseURI()
	{
		return '/file/';
	}

	public function getIconName()
	{
		return 'file';
	}

	public function getShortDescription()
	{
		return pht('Allow admins to view any file');
	}

	public function getApplicationGroup()
	{
		return self::GROUP_UTILITIES;
	}

	public function getRoutes()
	{
		return array(
			'/file/' => array(
				'' => 'AdminViewAnyFileController',
				'(?P<id>\d+)/' => 'AdminViewAnyFileController',
				'path/(?P<path>.+)/' => 'AdminViewAnyFileController',
			),
		);
	}

	public function buildApplicationMenu()
	{
		$menu = parent::buildApplicationMenu();

		$viewer = $this->getViewer();
		if ($viewer->getIsAdmin())
		{
			$item = id(new PHUIListItemView())
				->setName(pht('View Any File'))
				->setIcon('fa-file')
				->setHref('/file/');
			$menu->addMenuItem($item);
		}

		return $menu;
	}
}

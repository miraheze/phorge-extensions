<?php

final class ProjectTagLinksConfigOptions extends PhabricatorApplicationConfigOptions {

	public function getName() {
		return pht( 'Project Tag Links' );
	}

	public function getDescription() {
		return pht( 'Configure on-wiki links shown on tasks based on project tags.' );
	}

	public function getIcon() {
		return 'fa-book';
	}

	public function getGroup() {
		return 'utilities';
	}

	public function getOptions() {
		$links_example = <<<'EOT'
{
	"Database": {
		"Delete a wiki": "Tech:Delete_a_wiki",
		"Rename a wiki": {
			"url": "https://meta.miraheze.org/wiki/Tech:Rename_a_wiki",
			"columns": [ "Wiki rename" ]
		},
		"Resetting a wiki": {
			"url": "Tech:Wiki_reset",
			"visibility": "login"
		}
	},
	"MediaWiki": {
		"MediaWiki appserver": {
			"url": "Tech:MediaWiki_appserver",
			"projects": [ "acl*security", "tech-mediawiki" ]
		}
	}
}
EOT;

		$access_example = <<<'EOT'
{
	"visibility": "projects",
	"projects": [ "acl*security" ]
}
EOT;

		return [
			$this->newOption( 'projecttaglinks.links', 'wild', [] )
				->setSummary( pht( 'Map of project tags to documentation links.' ) )
				->setDescription(
					pht(
						"Maps a project name or hashtag to the links shown on tasks with that tag. " .
						"Each project maps link labels to a target.\n\n" .
						"A target is either a string or an object. A string is the link address. " .
						"An object has a `url` key and can also set `visibility` and `projects` " .
						"to control who sees that link. Links without their own access settings " .
						"use `projecttaglinks.default-access`.\n\n" .
						"An object can also set `columns`, a list of workboard column names. The " .
						"link then only shows on tasks sitting in one of those columns on the " .
						"workboard of the project it is listed under, and only if the viewer " .
						"passes its access settings too. Column names are matched without regard " .
						"to case. The first column of a board is called Backlog, and a task that " .
						"was never moved on a board counts as being in it. Links with `columns` " .
						"are not shown in the project menu item.\n\n" .
						"An address that starts with http:// or https:// is used as is. Any other " .
						"address is treated as a page title and appended to `mediawikilinks.base`.\n\n" .
						"Project names and hashtags are matched without regard to case."
					)
				)
				->addExample( $links_example, pht( 'Links with different access' ) ),
			$this->newOption(
				'projecttaglinks.default-access',
				'wild',
				[ 'visibility' => 'public' ]
			)
				->setSummary( pht( 'Who sees links that do not set their own access.' ) )
				->setDescription(
					pht(
						"Access settings for links that do not have their own. The `visibility` " .
						"key is one of:\n\n" .
						"  - `public`: everyone who can see the task.\n" .
						"  - `login`: any logged in user, no project needed.\n" .
						"  - `projects`: members of at least one project in the `projects` list.\n\n" .
						"Setting `projects` without `visibility` means `projects`. Projects are " .
						"given by name or hashtag. Members of a subproject count as members of " .
						"its parent. A project the viewer cannot see, or one that does not exist, " .
						"never grants access.\n\n" .
						"Anything that cannot be understood hides the link."
					)
				)
				->addExample( $access_example, pht( 'Only members of acl*security' ) ),
		];
	}
}

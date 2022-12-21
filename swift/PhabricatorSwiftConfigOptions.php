<?php

final class PhabricatorSwiftConfigOptions extends PhabricatorApplicationConfigOptions {

    public function getName() {
        return pht( 'Swift object storage' );
    }

    public function getDescription() {
        return pht( 'Configure Swift Object Storage for uploads.' );
    }

    public function getIcon() {
        return 'fa-hdd-o';
    }

    public function getGroup() {
        return 'core';
    }

    public function getOptions() {
        return array(
            $this->newOption( 'storage.swift.account', 'string', 'phab' )
                ->addExample('phab', pht('Swift account'))
                ->setDescription(pht('Swift account.')),

            $this->newOption('storage.swift.container', 'string', 'phab')
                ->addExample('phab-files', pht('Default container'))
                ->setSummary(pht('The name prefix for phabricator containers.'))
                ->setDescription(
                    pht('Phabricator will create a bunch of containers '.
                        'named with the given prefix followed by a short random suffix')),

            $this->newOption('storage.swift.key', 'string', null)
                ->setHidden(true)
                ->setDescription(pht('Secret key for swift.')),

            $this->newOption('storage.swift.endpoint', 'string', null)
                ->setDescription(pht('The hostname of the swift cluster frontend.'))
                ->addExample('https://ms-fe01', pht('MediaStorage FrontEnd 01')),
        );
    }
}

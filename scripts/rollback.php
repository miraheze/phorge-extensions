#!/usr/bin/env php
<?php

/**
 * @license http://www.apache.org/licenses/ Apache License 2.0
 */

$root = dirname( __DIR__ );
require_once $root . '/scripts/init/init-script.php';
init_script();
$args = new PhutilArgumentParser( $argv );
$args->setTagline( pht( 'Phabricator transaction rollback tool.' ) );
$args->setSynopsis( <<<EOSYNOPSIS
**rollback** __workflow__ [__options__]
    Roll back transactions

EOSYNOPSIS
);
$args->parseStandardArguments();

$workflows = id( new PhutilClassMapQuery() )
  ->setAncestorClass( 'MirahezeCLIWorkflow' )
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows( $workflows );

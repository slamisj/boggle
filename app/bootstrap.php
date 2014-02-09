<?php

/**
 * My Application bootstrap file.
 */


// Load Nette Framework
require LIBS_DIR . '/Nette/nette.min.php';
// Configure application
$configurator = new NConfigurator;

// Enable Nette Debugger for error visualisation & logging
//$configurator->setDebugMode();
$configurator->enableDebugger(dirname(__FILE__) . '/../log');

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(dirname(__FILE__) . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(APP_DIR)
	->addDirectory(LIBS_DIR)
	->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(dirname(__FILE__) . '/config/config.neon');

$container = $configurator->createContainer();

// Setup router
$container->router[] = new NRoute('index.php', 'Homepage:default', NRoute::ONE_WAY);
$container->router[] = new NRoute('<presenter>/<action>[/<id>]', 'Homepage:default');

$dbConf = (array) NEnvironment::getConfig()->database;

$dbConf['username'] = $dbConf['user'];
$dbConf['database'] = $dbConf['dbname'];
$dbConf['charset'] = 'utf8';

//echo '<!--';
//print_r($dbConf);
//echo '-->';

dibi::connect($dbConf);

// Configure and run the application!
$container->application->run();

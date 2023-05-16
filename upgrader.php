<?php

require 'vendor/autoload.php';
require './SilverstripeUpgrader.php';

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Application;

// create a log channel
$log = new Logger('upgrader');
$log->pushHandler(new StreamHandler('php://stdout', Level::Debug));

$application = new Application();
$application->setName("SilverStripe 4 to 5 Upgrader");
$application->add(new WakeWorks\SilverstripeFiveUpgrader\Console\SilverstripeUpgrader());
$application->run();
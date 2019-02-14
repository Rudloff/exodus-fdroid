#!/usr/bin/env php
<?php
use ExodusFdroid\ScanCommand;
use Symfony\Component\Console\Application;

require_once __DIR__.'/vendor/autoload.php';

$app = new Application();
$app->add(new ScanCommand());
if (isset($_SERVER['argv'])) {
    $app->run();
}

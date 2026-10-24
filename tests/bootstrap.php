<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Register OCP namespace manually since nextcloud/ocp doesn't have autoload config
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('OCP\\', __DIR__ . '/../vendor/nextcloud/ocp/OCP/');
$loader->addPsr4('NCU\\', __DIR__ . '/../vendor/nextcloud/ocp/NCU/');
$loader->register();

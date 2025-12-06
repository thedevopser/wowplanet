<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

new Dotenv()->bootEnv(__DIR__ . '/../.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
assert(is_string($env));
$debug = $_SERVER['APP_DEBUG'] ?? '0';

$kernel = new Kernel($env, (bool) $debug);
return new Application($kernel);

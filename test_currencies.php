<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

// Vous devez remplacer ces valeurs par votre token OAuth actuel
echo "Pour tester, veuillez :\n";
echo "1. Vous connecter sur le site\n";
echo "2. Copier votre access token depuis la session\n";
echo "3. Modifier ce script pour inclure le token\n";
echo "\nOu bien, allez dans var/log/dev.log après une recherche et cherchez 'available_currencies'\n";

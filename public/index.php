<?php

use App\Kernel;

// Structure sur InfinityFree (tout dans htdocs/) :
//   htdocs/index.php       <- ce fichier
//   htdocs/vendor/         <- autoloader
//   htdocs/src/            <- code PHP
//   htdocs/config/         <- configuration
//   htdocs/.env            <- variables d'environnement

require_once __DIR__ . '/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

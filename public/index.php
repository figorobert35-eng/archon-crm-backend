<?php

use App\Kernel;

// Sur Railway : /app/ est la racine du projet
// vendor/ est dans /app/vendor/
// public/ est dans /app/public/ (ce fichier est ici)
require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

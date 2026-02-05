<?php

use App\Kernel;

// Enforce consistent timezone for all date operations (DB, forms, templates)
date_default_timezone_set('Europe/Berlin');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

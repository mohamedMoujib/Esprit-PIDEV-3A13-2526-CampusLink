<?php
date_default_timezone_set($_SERVER['TZ'] ?? $_ENV['TZ'] ?? 'UTC');
use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

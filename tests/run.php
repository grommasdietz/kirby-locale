<?php

declare(strict_types=1);

require dirname(__DIR__) . '/playground/vendor/autoload.php';
require __DIR__ . '/Support/TestEnvironment.php';

use GrommasDietz\KirbyLocale\Tests\Support\TestEnvironment;
use Kirby\Cms\App;

$app = TestEnvironment::boot();
try {
    if (!isset($app->plugins()['grommasdietz/locale'])) {
        throw new RuntimeException('Locale plugin registration failed.');
    }
    if ($app->site()->homePage()->title()->value() !== 'Home') {
        throw new RuntimeException('Locale playground home page is unavailable.');
    }
    echo "Locale minimum-runtime smoke checks passed.\n";
} finally {
    TestEnvironment::restoreHandlers();
    App::destroy();
}

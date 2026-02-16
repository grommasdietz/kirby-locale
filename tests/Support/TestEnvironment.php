<?php

declare(strict_types=1);

namespace GrommasDietz\KirbyLocale\Tests\Support;

use Kirby\Cms\App;
use Kirby\Exception\DuplicateException;
use Kirby\Filesystem\Dir;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Prepares a Kirby instance that uses the playground as its site roots.
 * This helper keeps tests isolated from the Playwright fixtures and from
 * local user configuration.
 */
final class TestEnvironment
{
    private static mixed $prevErrorHandler = null;
    private static mixed $prevExceptionHandler = null;

    /**
     * Bootstraps a Kirby app configured for the playground installation.
     *
     * @param array<string,mixed> $overrides Provide Kirby config overrides for edge cases.
     */
    public static function boot(array $overrides = []): App
    {
        static::captureHandlers();
        static::destroyApp();

        $paths = static::resolvePaths();

        // Optionally load playground/.env when vlucas/phpdotenv is installed.
        $envFile = $paths['playground'] . '/.env';
        $dotenvClass = 'Dotenv\\Dotenv';

        if (is_file($envFile) && class_exists($dotenvClass)) {
            $dotenv = \call_user_func([$dotenvClass, 'createMutable'], $paths['playground'], '.env');
            \call_user_func([$dotenv, 'safeLoad']);
        }

        static::prepareContent($paths['playground'] . '/content');
        static::prepareCache($paths['cache']);
        static::prepareAccounts($paths['accounts']);
        static::preparePluginSandbox($paths['project'], $paths['plugins']);

        $defaults = [
            'roots' => [
                'index'    => $paths['playground'],
                'plugins'  => $paths['plugins'],
                'accounts' => $paths['accounts'],
            ],
            'options' => [
                'debug'  => false,
                'whoops' => false,
                'error'  => false,
                'cache'  => [
                    'pages' => [
                        'type' => 'file',
                        'root' => $paths['cache'] . '/pages',
                    ],
                ],
            ],
        ];

        $config = array_replace_recursive($defaults, $overrides);
        $pluginPath = $paths['project'] . '/index.php';

        static::loadPlugin($pluginPath);

        $app = new App($config);

        static::seedUsers($app);

        static::cleanupHandlers();

        return $app;
    }

    private static function captureHandlers(): void
    {
        $prevErrorHandler = set_error_handler(static fn () => false);
        restore_error_handler();

        $prevExceptionHandler = set_exception_handler(static fn () => false);
        restore_exception_handler();

        static::$prevErrorHandler = $prevErrorHandler;
        static::$prevExceptionHandler = $prevExceptionHandler;
    }

    /**
     * @return array{project:string, playground:string, cache:string, plugins:string, accounts:string}
     */
    private static function resolvePaths(): array
    {
        $testsRoot = realpath(__DIR__ . '/..');
        if ($testsRoot === false) {
            throw new \RuntimeException('Unable to resolve tests directory');
        }

        $projectRoot = realpath($testsRoot . '/..');
        if ($projectRoot === false) {
            throw new \RuntimeException('Unable to resolve project root');
        }

        $playgroundRoot = realpath($projectRoot . '/playground');
        if ($playgroundRoot === false) {
            throw new \RuntimeException('Playground root missing for tests');
        }

        return [
            'project'    => $projectRoot,
            'playground' => $playgroundRoot,
            'cache'      => $playgroundRoot . '/site/cache',
            'plugins'    => $playgroundRoot . '/site/plugins',
            'accounts'   => $playgroundRoot . '/site/accounts',
        ];
    }

    private static function prepareCache(string $cacheRoot): void
    {
        Dir::remove($cacheRoot);
        Dir::make($cacheRoot . '/pages', true);
    }

    /**
     * Removes generated content version folders (e.g. `_changes`) from the playground
     * and ensures required fixture directories/files exist.
     */
    private static function prepareContent(string $contentRoot): void
    {
        if (is_dir($contentRoot) === false) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && $item->getFilename() === '_changes') {
                Dir::remove($item->getPathname());
                continue;
            }

            if ($item->isFile() && $item->getFilename() === '.lock') {
                @unlink($item->getPathname());
            }
        }

        static::ensureContentFixtures($contentRoot);
    }

    /**
     * Recreates fixture content files that may have been deleted by
     * Kirby 5's content versioning during `$page->update()` calls.
     *
     * Format follows Kirby's standard: fields separated by `\n\n----\n\n`,
     * UUIDs only in the default language file, no trailing separator.
     */
    private static function ensureContentFixtures(string $contentRoot): void
    {
        $sep = "\n\n----\n\n";

        $fixtures = [
            'home' => [
                'home.en.txt' => implode($sep, [
                    'Title: Home',
                    'Text: Welcome to the playground.',
                    'Uuid: k7x2m9pq4a1nt5wz',
                ]),
                'home.de.txt' => implode($sep, [
                    'Title: Startseite',
                    'Text: Willkommen.',
                ]),
            ],
            'sample-article' => [
                'article.en.txt' => implode($sep, [
                    'Title: Sample Article',
                    'Title-locale: en',
                    'Text: <p>This is a <span class="notranslate" translate="no" lang="de">German phrase</span> inside English text.</p>',
                    'Uuid: v3h8qr1c6j9ws0yb',
                ]),
                'article.de.txt' => implode($sep, [
                    'Title: Beispielartikel',
                    'Title-locale: de',
                    'Text: <p>Das ist ein <span class="notranslate" translate="no" lang="en">English phrase</span> im deutschen Text.</p>',
                ]),
            ],
            'sandbox' => [
                'default.en.txt' => implode($sep, [
                    'Title: Sandbox',
                    'Text: Non-target template page.',
                    'Uuid: j1n4ct8x5p0rw3qa',
                ]),
                'default.de.txt' => implode($sep, [
                    'Title: Spielwiese',
                    'Text: Seite mit nicht aktiviertem Template.',
                ]),
            ],
        ];

        foreach ($fixtures as $subdir => $files) {
            $dir = $contentRoot . '/' . $subdir;

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            foreach ($files as $filename => $content) {
                $path = $dir . '/' . $filename;

                if (!is_file($path)) {
                    file_put_contents($path, $content);
                }
            }
        }
    }

    private static function prepareAccounts(string $accountsRoot): void
    {
        if (is_dir($accountsRoot) === false) {
            Dir::make($accountsRoot, true);
        }
    }

    private static function preparePluginSandbox(string $projectRoot, string $pluginsRoot): void
    {
        Dir::remove($pluginsRoot);
        Dir::make($pluginsRoot);

        $target = $pluginsRoot . '/kirby-locale';
        if (!symlink($projectRoot, $target)) {
            throw new \RuntimeException('Unable to link plugin into sandbox');
        }
    }

    private static function loadPlugin(string $pluginPath): void
    {
        if (!is_file($pluginPath)) {
            throw new \RuntimeException('Plugin entry file missing for tests');
        }

        try {
            require $pluginPath;
        } catch (DuplicateException) {
            // Plugin already registered for this process; safe to ignore.
        }
    }

    private static function cleanupHandlers(): void
    {
        $currErrorHandler = set_error_handler(static fn () => false);
        restore_error_handler();

        if ($currErrorHandler !== static::$prevErrorHandler) {
            restore_error_handler();
        }

        $currExceptionHandler = set_exception_handler(static fn () => false);
        restore_exception_handler();

        if ($currExceptionHandler !== static::$prevExceptionHandler) {
            restore_exception_handler();
        }
    }

    private static function seedUsers(App $app): void
    {
        if ($app->users()->count() > 0) {
            return;
        }

        $app->impersonate('kirby');

        $app->users()->create([
            'email'    => 'admin@kirby-locale.test',
            'name'     => 'Test Admin',
            'role'     => 'admin',
            'password' => 'test-password',
            'language' => 'en',
        ]);

        $app->impersonate(null);
    }

    public static function restoreHandlers(): void
    {
        $maxRestoreSteps = 10;
        for ($step = 0; $step < $maxRestoreSteps; $step++) {
            $currentErrorHandler = set_error_handler(static fn () => false);
            restore_error_handler();

            if ($currentErrorHandler === static::$prevErrorHandler) {
                break;
            }

            restore_error_handler();
        }

        $maxRestoreSteps = 10;
        for ($step = 0; $step < $maxRestoreSteps; $step++) {
            $currentExceptionHandler = set_exception_handler(static fn () => false);
            restore_exception_handler();

            if ($currentExceptionHandler === static::$prevExceptionHandler) {
                break;
            }

            restore_exception_handler();
        }
    }

    private static function destroyApp(): void
    {
        App::destroy();
    }
}

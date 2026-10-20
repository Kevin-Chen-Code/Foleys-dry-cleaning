<?php
declare(strict_types=1);

// Deliberately independent of config/bootstrap.php: app_local.php is NEVER loaded.
require dirname(__DIR__, 3) . '/config/paths.php';
require CORE_PATH . 'config/bootstrap.php';
require CAKE . 'functions.php';
require_once dirname(__DIR__) . '/harness.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Mailer\TransportFactory;
use Cake\Routing\Router;
use Cake\Utility\Security;

if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    throw new RuntimeException('This bootstrap is local CLI testing only.');
}
$m = FoleysLoadHarness::manifest((string)getenv('FOLEYS_LOAD_MANIFEST'));
Configure::config('default', new PhpConfig(CONFIG));
Configure::load('app', 'default', false);
Configure::write('debug', false);
Configure::write('App.fullBaseUrl', $m['baseUrl']);
Configure::write('Foleys', ['adminUsername' => '', 'adminPassword' => '']);

$connection = [
    'className' => Connection::class, 'driver' => Sqlite::class, 'database' => $m['database'],
    'encoding' => 'utf8', 'persistent' => false, 'cacheMetadata' => true,
    'init' => ['PRAGMA foreign_keys = ON', 'PRAGMA busy_timeout = 10000'],
];
// Replace, do not merge with any DATABASE_URL, SMTP URL, API key or live datasource.
Configure::write('Datasources', ['default' => $connection, 'test' => $connection]);
Configure::write('EmailTransport', ['default' => ['className' => DebugTransport::class]]);
Configure::write('Email', ['default' => ['transport' => 'default', 'from' => 'loadtest@example.com']]);
Configure::write('Session', [
    'defaults' => 'php', 'cookie' => 'FoleysLoad' . substr(hash('sha256', $m['runId']), 0, 16),
    'ini' => ['session.save_path' => $m['directory'] . '/sessions', 'session.use_strict_mode' => 1],
]);
$cache = [];
foreach (['default', '_cake_model_', '_cake_translations_'] as $name) {
    $cache[$name] = ['className' => 'File', 'path' => $m['directory'] . '/cache/', 'prefix' => $name, 'serialize' => true, 'duration' => '+1 day'];
}
Cache::setConfig($cache);
ConnectionManager::setConfig(Configure::consume('Datasources'));
TransportFactory::setConfig(Configure::consume('EmailTransport'));
Mailer::setConfig(Configure::consume('Email'));
Log::setConfig('load-test', [
    'className' => 'File', 'path' => $m['directory'] . '/', 'file' => 'application',
    'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
]);
Security::setSalt($m['token']);
Router::fullBaseUrl($m['baseUrl']);
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
ini_set('intl.default_locale', 'en_US');
(new ErrorTrap(Configure::read('Error')))->register();
(new ExceptionTrap(Configure::read('Error')))->register();
unset($m, $connection, $cache, $name);

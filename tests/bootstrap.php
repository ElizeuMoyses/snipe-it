<?php

/*
|--------------------------------------------------------------------------
| PHPUnit Test Bootstrap
|--------------------------------------------------------------------------
|
| Force test-specific environment variables before the application boots.
| This ensures Docker container env vars don't override test settings.
|
*/

$_SERVER['APP_ENV'] = 'testing';
$_SERVER['SESSION_DRIVER'] = 'array';
$_SERVER['CACHE_DRIVER'] = 'array';
$_SERVER['QUEUE_DRIVER'] = 'sync';
$_SERVER['MAIL_MAILER'] = 'array';

$_ENV['APP_ENV'] = 'testing';
$_ENV['SESSION_DRIVER'] = 'array';
$_ENV['CACHE_DRIVER'] = 'array';
$_ENV['QUEUE_DRIVER'] = 'sync';
$_ENV['MAIL_MAILER'] = 'array';

putenv('APP_ENV=testing');
putenv('SESSION_DRIVER=array');
putenv('CACHE_DRIVER=array');
putenv('QUEUE_DRIVER=sync');
putenv('MAIL_MAILER=array');

require __DIR__ . '/../bootstrap/autoload.php';

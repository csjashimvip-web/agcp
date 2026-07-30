<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root.'/.env.example';
$target = $root.'/.env';
$force = in_array('--force', $argv, true);

if (! is_file($source)) {
    fwrite(STDERR, ".env.example was not found.\n");
    exit(1);
}

if (is_file($target) && ! $force) {
    fwrite(STDERR, ".env already exists. Use --force to replace it.\n");
    exit(1);
}

$env = file_get_contents($source);
if ($env === false) {
    fwrite(STDERR, "Unable to read .env.example.\n");
    exit(1);
}

$random = static fn (int $bytes = 32): string => rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');

$replacements = [
    'CHANGE_ME_APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'CHANGE_ME_DB_PASSWORD' => $random(32),
    'CHANGE_ME_MYSQL_ROOT_PASSWORD' => $random(40),
    'CHANGE_ME_REDIS_PASSWORD' => $random(40),
];

$env = str_replace(array_keys($replacements), array_values($replacements), $env);

if (file_put_contents($target, $env, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write .env.\n");
    exit(1);
}

@chmod($target, 0600);
fwrite(STDOUT, "Generated secure local .env file.\n");

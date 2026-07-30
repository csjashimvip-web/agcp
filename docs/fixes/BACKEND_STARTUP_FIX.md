# Backend startup fix

The development entrypoint previously ran:

```sh
composer dump-autoload --no-interaction --no-progress
```

`--no-progress` is an install/update option, not a `dump-autoload` option. Because the entrypoint uses `set -e`, Composer exited non-zero and the backend entered a restart loop. Nginx therefore remained stopped because it depends on a healthy backend.

The entrypoint now uses Laravel's package discovery command directly when the named bootstrap cache volume is empty:

```sh
php artisan package:discover --ansi --no-interaction
```

The Composer autoloader is already created in the dependency image and seeded into the development vendor volume.

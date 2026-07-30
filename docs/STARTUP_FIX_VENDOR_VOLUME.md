# Development dependency-volume startup fix

## Symptom

The backend repeatedly restarted with:

```text
Warning: require(/var/www/html/vendor/autoload.php): Failed to open stream
Fatal error: Failed opening required '/var/www/html/vendor/autoload.php'
```

## Root cause

The development override bind-mounted the backend source over `/var/www/html` and mounted a new named volume at `/var/www/html/vendor`. The development image did not contain a dependency seed, so the new vendor volume was empty. Runtime dependency installation was also incompatible with the intentionally private application network.

The frontend had the same latent risk for its `node_modules` volume.

## Fix

- Composer dependencies are resolved in a Docker build stage.
- Development dependencies are copied to `/opt/agcp/vendor` in the backend image.
- The backend entrypoint seeds the shared vendor volume before Laravel starts.
- NPM dependencies are copied to `/opt/agcp/node_modules` in the frontend image.
- The frontend entrypoint seeds the shared node_modules volume before Next.js starts.
- Runtime containers no longer require internet access to install dependencies.
- Backend health now verifies both `vendor/autoload.php` and PHP-FPM configuration.
- Setup failures print focused backend/frontend diagnostics and stop before any success message.

## Safe reset

Run `scripts/reset-dev-dependencies.ps1` before rebuilding. It removes only development dependency cache volumes. MySQL and Redis data are preserved.

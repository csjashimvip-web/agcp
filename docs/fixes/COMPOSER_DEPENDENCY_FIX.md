# Composer Dependency Resolution Fix

The backend dependency set was aligned for Laravel 13.23 and PHP 8.4:

- Laravel Framework: `^13.23`
- Laravel Tinker: `^3.0`
- Pest: `^5.0.2`
- Pest Laravel Plugin: `^5.0.1`
- Collision: `^8.9.5`

The previous `nunomaduro/collision:^9.0` constraint had no stable matching release and the Pest 4 dependency set did not match the current Laravel 13 test stack.

The backend Dockerfile now copies `composer.*`, so a future committed `composer.lock` will automatically be used without another Dockerfile change.

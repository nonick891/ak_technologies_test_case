# Application requirements

1. PHP 8;
2. Composer;
3. Docker.

# Application installation

    $ composer install

# Local development

### Installation

    $ php artisan sail:install

### Default usage of sail

    $ ./vendor/bin/sail

### Alias

To use a short version of command `./vendor/bin/sail` add next alias in `~/.zshrc` or `~/.bashrc`:

    alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'

# Docker container over laravel sail

**Note**: all further commands without the short [alias](#alias) could be run only with `./vendor/bin/sail`

Run container

    $ sail up

Silent run container

    $ sail up -d

Shutdown the container

    $ sail stop

### Migration

    $ sail artisan migrate:fresh

    $ sail artisan db:seed

### CURL commands

Create a hold

    curl -i -X POST "http://localhost/slots/1/hold" \
        -H "Content-Type: application/json" \
        -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111"

Check created hold

    curl -i -X POST "http://localhost/slots/1/hold" \
        -H "Content-Type: application/json" \
        -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111"

Confirm created hold

    curl -X POST "http://localhost/holds/10/confirm" \
        -H "Content-Type: application/json"

Create a new hold

    curl -X POST "http://localhost/slots/1/hold" \
        -H "Content-Type: application/json" \
        -H "Idempotency-Key: 11111111-1111-1111-1111-111111111112"

Cancel the new hold

    curl -X DELETE "http://localhost/holds/11" \  
        -H "Content-Type: application/json"  

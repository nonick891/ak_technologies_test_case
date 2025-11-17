# Application installation

    $ composer install

# Server via laravel sail

### Installation

    $ php artisan sail:install

### Default usage of sail

    $ ./vendor/bin/sail

### Short command

To use a short version of command `./vendor/bin/sail` add next alias in `~/.zshrc` or `~/.bashrc`:

    alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'

# Run docker container over laravel sail

**Note**: all further commands without the short command could be run only with `./vendor/bin/sail`

Run container

    $ sail up

Silent run container

    $ sail up -d

Shutdown container

    $ sail stop

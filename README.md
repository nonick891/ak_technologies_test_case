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

Shutdown container

    $ sail stop



# Installation

If you'd like to install CDash in a [Docker](https://www.docker.com) container, please see our
[Docker installation guide](docker.md).

## Prerequisite software

Before installing CDash, you will need:

- A web server: [Apache](https://httpd.apache.org) or [NGINX](https://www.nginx.com)
- A database: [PostgreSQL v9.2+](https://www.postgresql.org)
- [PHP 8.x](https://www.php.net)
- [Composer v2.x](https://getcomposer.org) (to install PHP dependencies)
- [npm v8](https://www.npmjs.com/) (to install Javascript dependencies)

## PHP modules

CDash needs the following PHP modules installed and enabled.

- bcmath
- bz2
- curl
- gd
- ldap
- mbstring
- pdo_pgsql
- xsl
- posix
- simplexml
- tokenizer
- fileinfo
- session
- zlib

## Web server configuration

CDash is built on top of the [Laravel framework](https://laravel.com).

Laravel's routing system requires your web server to have the `mod_rewrite` module enabled.

It also requires your web server to honor .htaccess files `(AllowOverride All)`.

See [Laravel documentation](https://laravel.com/docs/6.x/installation#pretty-urls) for more information.

## Download CDash

If you haven't already done so, [download CDash from GitHub](https://github.com/Kitware/CDash/releases) or clone it using git.

```bash
git clone https://github.com/Kitware/CDash
```

## Expose CDash to the web

Only CDash's `public` subdirectory should be served to the web.

The easiest way to achieve this is to create a symbolic link in your DocumentRoot
(typically `/var/www`) that points to `/path/to/CDash/public`.

## Adjust filesystem permissions

The user that your web server is running under will need write access to the CDash directory.
In the following example, we assume your web server is run by the `www-data` user.

```bash
# Modify CDash directory to belong to the www-data group
chgrp -R www-data /path/to/CDash

# Make the CDash directory writeable by group.
chmod -R g+rw /path/to/CDash
```

## Install/upgrade steps

Perform the follow steps when you initially install CDash and upon each subsequent upgrade.

```bash
bash ./install.sh
```

## Configure CDash and generate build files

The next step is to configure your CDash installation. See [the configuration guide](config.md) for more details.

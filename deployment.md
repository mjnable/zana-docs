# Deployment

This page puts a Zana website on a server: a VPS where you control the
web server, or shared hosting (cPanel and the like) where the document
root is fixed and there may be no SSH. The
[website skeleton](https://github.com/mjnable/zana-skeleton-web) (2.3)
ships everything it uses:

```
composer create-project mj-zana/skeleton-web my-site
```

The [API skeleton](https://github.com/mjnable/zana-skeleton-api) (2.9)
deploys the same way, with `release:build --folder=api`; run
`php bin/console migrate` on the server too.

## Before any upload

1. **Commit.** Releases are built from commits, so what you test is what
   ships.
2. **Configuration from the server.** Secrets and the public address never
   go into `config/`. On the server, set them in the environment or in a
   `.env` file beside `composer.json`:

   ```
   APP_ENV=production
   APP_APP__URL=https://www.acme.example
   APP_SECURITY__SECRET=<64 random hexadecimal characters>
   ```

   `php -r "echo bin2hex(random_bytes(32));"` generates the secret. In
   production, the application refuses to start with a placeholder secret,
   or with debug on and a public address, rather than leak internals.
3. **The fallback page.** Keep `public/503.html` small and static: it is
   what visitors see when the application cannot start, while the reason
   goes to PHP's error log.

## The document root

The document root must be `public/`, so that `.env`, `config/`, `vendor/`
and `var/` can never be downloaded. There are three layouts.

| Hosting | Layout |
|---|---|
| You choose the document root (VPS, most panels for add-on domains) | Point it at `my-site/public` |
| The document root is the project folder | Upload the whole project; the root `.htaccess` rewrites every request into `public/` |
| The document root is fixed (`public_html` of a cPanel primary domain) | **Split:** the project beside `public_html`, and only the contents of `public/` in it |

The rewrite was checked on a cPanel host: `/.env`,
`/vendor/autoload.php`, `/config/app.php` and `/src/...` all answer 404.
Without `mod_rewrite`, the root `.htaccess` denies everything rather than
serve the project's files.

In the split layout, `public/index.php` finds the project one level up
when `vendor/` is there, and otherwise in a folder beside the document
root, named by its `PROJECT_FOLDER` constant (`website` by default):

```
/home/acme/
├── website/          the project: config/, modules/, vendor/, var/, .env
└── public_html/      the contents of public/: index.php, .htaccess, assets/
```

## With SSH

```
git pull                       # or upload a release archive
composer install --no-dev --optimize-autoloader
php bin/console cache:clear
php bin/console cache:warm
php bin/console doctor
```

- `cache:clear` deletes the compiled container, the configuration cache
  (2.4), and compiled Twig templates when Twig is installed.
  A container compiled for the previous code is an error in production,
  so clear it on every deployment.
- `cache:warm` compiles them again, so the first visitor does not wait.
  It fails when a configuration file returns something other than plain
  values, naming the key.
- `doctor` checks the PHP version and extensions (`app.extensions`), that
  `var/` is writable, the secret, and the deployment checks. It exits
  with 1 when something is fatal. Since 2.6 they include writable log
  files and, in production, a real mail transport.
- `mail:test you@example.com` (2.6) sends a test message and prints the
  mail server's answer.

**When a page shows the error page,** the reason is in `var/log/app.log`
with the skeleton's logging (2.6), or else in PHP's error log (the
`error_log` file on most shared hosting). Neither holds stack traces or
passwords.

## Without SSH

When Composer cannot run on the server, build the upload on your machine:

```
php bin/console release:build
```

It takes the last commit (`--ref` another one), installs the production
dependencies in a copy, and writes two archives:

- `build/website.zip`: the project, with `vendor/` and an empty `var/`,
  ready to run. Unpack it beside `public_html`.
- `build/public_html.zip`: the contents of `public/`. Unpack it into
  `public_html`. Its `index.php` points at `../website`; `--folder` picks
  another name for both.

[What the archives hold](#what-the-archives-hold) explains what they leave
out.

The command comes with `mj-zana/console` (2.5), so `composer update` brings
its fixes. It finds Composer on the `PATH`, including the `composer.phar` that
the Windows installer puts beside `composer.bat`; `--composer` points at
another one. `-v` shows git's and Composer's output as it comes, and
`--timeout` (600 seconds by default) stops a build that does not finish,
showing the end of its output.

A project created from the skeleton 2.3 or 2.4 has its own copy of the
command, which can hang on Windows: delete `src/Release/`,
`src/Console/ReleaseBuildCommand.php` and its registration in
`src/Bootstrap.php`, and require `mj-zana/console` ^2.5.

Then, in the file manager:

1. create `website/.env` once, with the settings above;
2. make `website/var/` writable (755 for folders is usually enough when PHP
   runs as your user);
3. after each upload, delete `website/var/cache/`, so the container and
   the configuration are read again on the next request;
4. try the mail settings once with a cron job running
   `php /home/<user>/website/bin/console mail:test you@example.com`: its
   output, the mail server's answer, arrives by email.

Composer runs on your machine, so it must use the server's PHP version and
extensions: set `config.platform.php` in `composer.json` when your machine
runs a newer PHP.

## What the archives hold

`release:build` starts from the commit, so uncommitted files never ship.
Since 2.18, the code archive holds what the server runs, and leaves out:

- the tests and the development tooling: `tests/`, `.github/`, PHPUnit's and
  PHPStan's files, `.gitattributes`, `.gitignore`, `.editorconfig` and
  `*.plan.json`;
- local and test settings: `.env`, `.env.testing`, `.env.example` and
  `config/testing/`;
- the project's documents: `README.md`, `CHANGELOG.md`, `LICENSE`,
  `CONTRIBUTING.md`, `SECURITY.md`, `UPGRADE.md`, `AGENTS.md` and `CLAUDE.md`;
- at the root of each package in `vendor/`, the documents for its readers:
  READMEs, changelogs and similar Markdown files, `docs/`, `.github/`,
  `.gitignore`, `.editorconfig` and `.psalm/`.

Project paths count from the root, so a `README.md` inside a module stays.

Three things that do not run stay on purpose:

- the licence files in `vendor/`, because the licences of most packages
  require them to travel with the code;
- `composer.json` and `composer.lock`, so that Composer can run on an upload;
- the root `.htaccess`, which keeps `.env` private if the project is ever
  unpacked inside the document root by mistake.

**Your own files.** To leave out anything else, such as a `docs/` folder of
project notes, mark it `export-ignore` in the application's `.gitattributes`
and commit that. The path is then in neither archive:

```
/docs export-ignore
```

The files stay in the repository. Do not add this to a skeleton you publish:
`composer create-project` installs from the same kind of archive, so a new
project would not receive them.

**`public/` in the split layout.** `public/` is in both archives by default,
because `website.zip` is the only upload when the document root is `public/`
or the project folder. In the split layout, the copy in `website/public` is
not served, so leave it out:

```
php bin/console release:build --no-public
```

`public_html.zip` does not change. Use this option only in the split layout:
the other two layouts have no `public/` without it. Two settings then point
at `public_html`, in `website/.env`:

```
APP_FILESYSTEM__DISKS__PUBLIC__ROOT=/home/acme/public_html/files
APP_VIEW__ASSETS__PATH=/home/acme/public_html
```

- The first is for a public disk: uploaded files must be written where the web
  server finds them. The split layout needs it with or without the option.
- The second is for commands. Web pages already read `public_html`, because
  its `index.php` says so, but `bin/console` looks in `website/public`. Without
  this setting, an asset URL built in a command, such as a link in an email
  sent by a worker, has no version. With the `manifest` asset strategy, it fails.

## Behind a CDN or a load balancer

Every request then comes from the proxy. List its addresses in
`http.trusted_proxies` and keep `TrustedProxiesMiddleware` first in
`http.middleware`, so that rate limits see visitors' addresses and
generated URLs see HTTPS (see [HTTP](guides/http.md#behind-a-proxy)).

## Lean dependencies

Since 2.3, the packages' archives leave out their tests and tooling
(`export-ignore`), so `vendor/` holds only what runs.

## Moving from an old site

Redirect the old URLs to the new pages, permanently, so links and search
rankings follow (see [Routing](guides/routing.md#new-in-23-redirect-routes)):

```php
$router->redirect('/qui-sommes-nous', 'about', parameters: ['_locale' => 'fr']);
```

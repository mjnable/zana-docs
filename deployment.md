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
php bin/console release:clean
php bin/console cache:clear
php bin/console cache:warm
php bin/console doctor
```

- `release:clean` (2.19) applies [the same exclusion rules](#the-same-rules-on-every-route)
  as the release archives.
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

- the tests and the development tooling: `tests/`, `.github/`,
  `phpunit.xml.dist`, `phpunit.xml`, `.gitattributes` and `.gitignore`;
- local settings: `.env`, `.env.testing` and `.env.example`;
- the project's documents: `README.md`, `AGENTS.md` and `CLAUDE.md`.

These are top-level names, so a `README.md` inside a module stays.

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

## The same rules on every route

Since 2.19, the list above applies however the files reach a live server.
Each route keeps its own way of deploying:

| Route | How the rules apply |
|---|---|
| `release:build` | The archive is built without the files. Nothing else to do. |
| `git pull` | `release:clean` keeps the files out of the checkout, and `git pull` keeps working. |
| FTP, rsync or a copied folder | `release:clean` deletes the files. |

```
php bin/console release:clean --dry-run   # what would go; nothing changes
php bin/console release:clean
```

It runs only with `APP_ENV=production`, so it never touches a development
or test machine. Only `--dry-run` works anywhere.

- **In a Git checkout,** it uses Git's sparse-checkout, so the files leave the
  folder, `git status` stays clean, and later pulls skip them. Run it after each
  pull, so that new rules apply. It needs Git 2.35 or later, and it refuses a
  checkout that has sparse-checkout patterns of its own. The files stay in the history
  inside `.git`, which the document root keeps out of reach.
- **In any other folder,** it lists the files and asks, because a deleted file
  comes back only with the next upload. `--force` skips the question, for a
  script or a cron job.
- **What stays.** `.env`, `.git`, `var/` and `vendor/` are never touched: the
  server's `.env` is its configuration. A file changed on the server stays in
  a checkout, and the command names it.
- **Your own files.** `export-ignore` entries count when they name a plain path
  such as `/docs` or `/docs/**`, in ASCII letters, digits, `.`, `_` and `-`.
  Patterns such as `*.log` do not apply here, and the command names them, as it
  names a path that leads through a link. It also names `docs` without a leading slash, which
  Git matches in any folder and this command only at the root: write the full
  path, such as `/src/docs`, for a nested one.
- **`public/`** goes only with `--no-public`, for the split layout. In the other
  layouts it is the site.

`doctor` warns in production when any of these files are on the server, so a
route that missed the step shows up. The warning does not fail it.

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

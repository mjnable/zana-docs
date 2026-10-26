# Deployment

This page puts a Zana website on a server: a VPS where you control the
web server, or shared hosting (cPanel and the like) where the document
root is fixed and there may be no SSH. The
[website skeleton](https://github.com/mjnable/zana-skeleton-web) (2.3)
ships everything it uses:

```
composer create-project mj-zana/skeleton-web my-site
```

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

- `cache:clear` deletes the compiled container, and compiled Twig
  templates when Twig is installed.
  A container compiled for the previous code is an error in production,
  so clear it on every deployment.
- `cache:warm` compiles them again, so the first visitor does not wait.
- `doctor` checks the PHP version and extensions (`app.extensions`), that
  `var/` is writable, the secret, and the deployment checks. It exits
  with 1 when something is fatal.

## Without SSH

When Composer cannot run on the server, build the upload on your machine:

```
php bin/console release:build
```

It takes the last commit (`--ref` another one), installs the production
dependencies in a copy, and writes two archives:

- `build/website.zip`: the project, with `vendor/` and an empty `var/`,
  but not the tests or `.env`. Unpack it beside `public_html`.
- `build/public_html.zip`: the contents of `public/`. Unpack it into
  `public_html`. Its `index.php` points at `../website`; `--folder` picks
  another name for both.

Then, in the file manager:

1. create `website/.env` once, with the settings above;
2. make `website/var/` writable (755 for folders is usually enough when PHP
   runs as your user);
3. after each upload, delete `website/var/cache/`, so the container is
   compiled again on the next request.

Composer runs on your machine, so it must use the server's PHP version and
extensions: set `config.platform.php` in `composer.json` when your machine
runs a newer PHP.

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

# Contributing

Thank you for helping with Zana. This page explains how the project is
organised and what a change needs before it can be merged.

## The repositories

Every package has its own repository, `github.com/mjnable/zana-<name>`,
published on Packagist as `mj-zana/<name>`. This documentation is
`zana-docs`.

All packages share one version number. A release tags every package with
the same `vX.Y.Z` tag, even packages that did not change, so applications
can require them all with one constraint.

## Working on a package

```
git clone https://github.com/mjnable/zana-database
cd zana-database
composer install
composer test        # PHPUnit
composer analyse     # PHPStan, level 8
```

- **Local development across packages:** use path repositories, so
  changes in one package are visible in another without a release. Add one
  entry per package you work on to `composer.json`:

  ```json
  "repositories": [
      { "type": "path", "url": "../zana-core", "options": { "symlink": true, "versions": { "mj-zana/core": "2.0.0" } } }
  ]
  ```

  Don't commit `composer.lock`; packages are libraries.
- **Branches:** work on a branch, and keep `main` releasable. Merges into
  `main` are fast-forwards.
- **Code style:**
  - `declare(strict_types=1)` and `final` classes everywhere;
  - `readonly` wherever state never changes;
  - no service locator: classes declare their dependencies in their
    constructors.
  - Match the surrounding code: its comments, names and idioms.

## What a change needs

1. **Tests:** every behaviour change comes with tests. A bug fix starts
   with a test that fails without it.
2. **A green build:** PHPUnit and PHPStan level 8 must pass in the package,
   and in every package that depends on it.
3. **A spec or design note for public API changes.** Each package has a
   specification, and larger decisions are recorded as ADRs. A change to
   the public API updates them in the same pull request. After the 2.0
   betas, breaking changes wait for the next major version.
4. **Documentation:** update the package README, and this documentation
   when behaviour that users see changes.
5. **Commit messages:** a short summary line in the imperative ("Add
   Schema::table()"), then what changed and why.

## Working on this documentation

The guides embed code from `examples/`, and the tests keep the two in step:

- **Embedding:** a code block preceded by `<!-- example: path -->` or
  `<!-- example: path#region -->` must match that file, or the lines
  between `// region: name` and `// endregion: name` in it.
- **Outputs:** every script in `examples/guides/` must print exactly its
  `.out` file.
- **Example apps:** they run their own PHPUnit tests.
- **No orphans:** every example file must be shown on some page.

```
composer install             # the released packages, from Packagist
composer test
php bin/sync-examples.php    # after changing an example: rewrite the embedded blocks
php examples/guides/cache.php > examples/guides/cache.out   # after changing a script's output
```

Write for PHP developers who are new to Zana. Explain why before how, and
prefer a short, runnable example to a long description.

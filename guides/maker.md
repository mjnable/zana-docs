# Maker

`mj-zana/maker` generates code that follows the module layout: modules,
features (commands and queries with handlers), entities and controllers. It
writes plain code you own, and wires it into the module class, so a
generated module works straight away.

```
composer require --dev mj-zana/maker
```

## Setup

- **Namespace:** generated classes use `Modules\<Module>` under
  `modules/<Module>/`, so map `"Modules\\": "modules/"` in `composer.json`.
- **Registration:** maker is a dev dependency, so register its provider
  only where it is installed, e.g. in `bin/zana`:

  ```php
  if (class_exists(\Zana\Maker\MakerServiceProvider::class)) {
      $builder->withProviders(\Zana\Maker\MakerServiceProvider::class);
  }
  ```

- **Environment:** run the generators with `APP_ENV=development`. Every
  `make:*` command changes the registrations, and in production a compiled
  container that no longer matches is an error.

## Generating a module

```
php bin/zana make:module Clinics
php bin/zana make:entity Clinics Clinic
php bin/zana make:feature Clinics OpenClinic             # --query for a query
php bin/zana make:controller Clinics Clinics             # routed at /api/clinics
```

The same steps through the `Generator`, as the commands use it:

<!-- example: examples/guides/maker.php#generate -->
```php
// The make:* commands do this; here the Generator is used directly.
// A plan is a snapshot of the files: write each plan before making the next one.
$generator = new Generator($base);
$steps = [
    'make:module Clinics' => static fn (): array => $generator->planModule('Clinics'),
    'make:entity Clinics Clinic' => static fn (): array => $generator->planEntity('Clinics', 'Clinic'),
    'make:feature Clinics OpenClinic' => static fn (): array => $generator->planFeature('Clinics', 'OpenClinic'),
    'make:controller Clinics Clinics' => static fn (): array => $generator->planController('Clinics', 'Clinics'),
];
foreach ($steps as $command => $plan) {
    $files = $plan();
    $generator->write($files);
    echo $command, "\n";
    foreach (array_filter($files, static fn (GeneratedFile $f): bool => !str_ends_with($f->relativePath, '.gitkeep')) as $file) {
        echo '  ', $file->update ? 'updated ' : 'created ', $file->relativePath, "\n";
    }
}
```

The module class now registers the handler and the controller, maps the
handler, and loads the controller's routes:

<!-- example: examples/guides/maker.php#wiring -->
```php
$module = (string) file_get_contents("{$base}/modules/Clinics/ClinicsModule.php");
foreach (explode("\n", $module) as $line) {
    if (str_starts_with(ltrim($line), '$')) {     // the lines maker added to register() and boot()
        echo trim($line), "\n";
    }
}
```

<!-- example: examples/guides/maker.out -->
```text
make:module Clinics
  created modules/Clinics/ClinicsModule.php
  updated config/modules.php
make:entity Clinics Clinic
  created modules/Clinics/Domain/Clinic.php
  created modules/Clinics/Domain/ClinicId.php
  created modules/Clinics/Domain/ClinicRepositoryInterface.php
make:feature Clinics OpenClinic
  created modules/Clinics/Application/Features/OpenClinic/OpenClinicCommand.php
  created modules/Clinics/Application/Features/OpenClinic/OpenClinicHandler.php
  created modules/Clinics/Tests/Application/OpenClinicHandlerTest.php
  updated modules/Clinics/ClinicsModule.php
make:controller Clinics Clinics
  created modules/Clinics/Presentation/Api/ClinicsController.php
  updated modules/Clinics/ClinicsModule.php
$services->scoped(\Modules\Clinics\Application\Features\OpenClinic\OpenClinicHandler::class);
$services->transient(\Modules\Clinics\Presentation\Api\ClinicsController::class);
$container->get(\Zana\Mediator\HandlerMap::class)->register(\Modules\Clinics\Application\Features\OpenClinic\OpenClinicHandler::class);
$container->get(\Zana\Routing\Router::class)->loadAttributes(\Modules\Clinics\Presentation\Api\ClinicsController::class);
```

## Rules worth knowing

- **Wiring markers:** `make:module` writes the markers
  `// zana:maker services` and `// zana:maker boot`, and the generators add
  lines above them. Keep the marker lines.
- **Generating twice wires once.**
- **Hand-written modules** without markers are not edited. The command
  prints the lines to add instead.
- **Existing files are never replaced.** The command lists them and writes
  nothing. Add `--force` to replace them, or `--dry-run` to see the plan.
- **`config/modules.php`:** `make:module` adds the module and keeps the
  file's comments. The file must only return a list of `::class` entries.
- **Stubs:** override any of them with `<project>/stubs/<name>.stub`. An
  unknown `{{ placeholder }}` is an error.
- **Using the `Generator` directly:** a plan is a snapshot of the files.
  Write each plan before making the next, or a later plan can overwrite an
  earlier plan's changes to the module class.

Reference: the [maker README](https://github.com/mjnable/zana-maker).

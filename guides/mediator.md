# Mediator

`mj-zana/mediator` sends commands and queries to their handlers through
pipeline behaviours, the CQRS style. Presentations (HTTP actions, console
commands) build a message and send it; they don't need to know which class
handles it.

```
composer require mj-zana/mediator
```

## Messages and handlers

A handler declares the message it handles with `#[Handles]`, or through the
type of its `__invoke()` or `handle()` parameter:

<!-- example: examples/guides/mediator.php#messages -->
```php
final readonly class RegisterPatient
{
    public function __construct(public string $name)
    {
    }
}

#[Handles(RegisterPatient::class)]
final class RegisterPatientHandler
{
    public function __invoke(RegisterPatient $command): string
    {
        return "registered {$command->name}";
    }
}
```

## Behaviours

Behaviours wrap every handler, for validation, transactions, logging and
the like. The first configured behaviour is the outermost:

<!-- example: examples/guides/mediator.php#behavior -->
```php
final readonly class Timing implements PipelineBehavior
{
    public function handle(object $message, \Closure $next): mixed
    {
        echo 'before ', $message::class, "\n";
        $result = $next($message);                 // or return early to short-circuit
        echo 'after ', $message::class, "\n";

        return $result;
    }
}
```

## Sending

<!-- example: examples/guides/mediator.php#send -->
```php
// In an application, MediatorServiceProvider wires this up; modules call
// HandlerMap::register() in boot() and list behaviours in mediator.behaviors.
$container = (new ServiceCollection())
    ->scoped(RegisterPatientHandler::class)        // handlers are resolved from the current scope
    ->singleton(Timing::class)
    ->build();
$handlers = (new HandlerMap())->register(RegisterPatientHandler::class);

$mediator = new Mediator($container->createScope(), $handlers, [Timing::class]);
echo $mediator->send(new RegisterPatient('Ada Lovelace')), "\n";
```

<!-- example: examples/guides/mediator.out -->
```text
before RegisterPatient
after RegisterPatient
registered Ada Lovelace
```

## In an application

- **Wiring:** register `MediatorServiceProvider` and inject
  `MediatorInterface`. It is scoped, so handlers get the current request's
  or command's services.
- **Handlers:** register each one as a service, and map it in its module's
  `boot()`:
  `$container->get(HandlerMap::class)->register(RegisterPatientHandler::class)`.
  [Maker](maker.md) writes both lines for you.
- **Behaviours:** list them in `config/mediator.php` under `behaviors`. A
  typical first one validates every message (see [validation](validation.md)):

  ```php
  final readonly class ValidationBehavior implements PipelineBehavior
  {
      public function __construct(private Validator $validator) {}

      public function handle(object $message, \Closure $next): mixed
      {
          $this->validator->assertValid($message);

          return $next($message);
      }
  }
  ```

## Rules worth knowing

- **Lookup:** the handler for a message's exact class wins, then its
  parents, then its interfaces. No handler means `HandlerNotFoundException`.
- **One handler per message class.** Mapping a second one throws.
- **Handlers return whatever the caller needs.** Return a plain result
  object rather than an aggregate, so presentations stay away from the
  domain.

Reference: the [mediator README](https://github.com/mjnable/zana-mediator).

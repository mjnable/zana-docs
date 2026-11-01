<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\ServerRequest;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Http\ErrorMiddleware;
use Zana\Http\HttpServiceProvider;
use Zana\Mediator\Attribute\Handles;
use Zana\Mediator\HandlerMap;
use Zana\Mediator\MediatorInterface;
use Zana\Mediator\MediatorServiceProvider;
use Zana\Validation\Constraint\Email;
use Zana\Validation\Constraint\NotBlank;
use Zana\Validation\ValidationBehavior;
use Zana\Validation\ValidationException;
use Zana\Validation\ValidationServiceProvider;

final class InviteMember
{
    public function __construct(
        #[NotBlank, Email]
        public readonly string $email,
        #[NotBlank]
        public readonly string $name,
    ) {
    }
}

#[Handles(InviteMember::class)]
final class InviteMemberHandler
{
    public function __invoke(InviteMember $command): string
    {
        return "Invited {$command->email}.";
    }
}

// region: enable
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-validation-behavior')
    ->withProviders(HttpServiceProvider::class, MediatorServiceProvider::class, ValidationServiceProvider::class)
    ->withConfiguration(['mediator.behaviors' => [ValidationBehavior::class]])   // config/mediator.php
    ->withServices(static fn (ServiceCollectionInterface $s) => $s->scoped(InviteMemberHandler::class))
    ->build();
$app->container()->get(HandlerMap::class)->register(InviteMemberHandler::class);
// endregion: enable

// region: send
// From a console command, a queue job or another handler: the handler never sees invalid input.
$scope = $app->container()->createScope();
$mediator = $scope->get(MediatorInterface::class);

echo $mediator->send(new InviteMember('ada@example.com', 'Ada')), "\n";
try {
    $mediator->send(new InviteMember('ada at example.com', ''));
} catch (ValidationException $e) {
    foreach ($e->violations->toArray() as $field => $messages) {
        echo "{$field}: ", implode(' ', $messages), "\n";
    }
}
// endregion: send

// region: http
// Over HTTP, the same exception becomes a 422 with the field errors.
$response = $scope->get(ErrorMiddleware::class)->render($e, new ServerRequest('POST', '/members', ['Accept' => 'application/json']));

echo $response->getStatusCode(), ' ', json_encode(json_decode((string) $response->getBody(), true)['errors']), "\n";
// endregion: http

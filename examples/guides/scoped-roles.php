<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Http\Exception\ForbiddenHttpException;
use Zana\Mediator\PipelineBehavior;
use Zana\Security\Authorizer;
use Zana\Security\RoleAssignment\HasAuthorizationScope;
use Zana\Security\RoleAssignment\InMemoryRoleAssignmentStore;
use Zana\Security\RoleAssignment\RoleAssignments;
use Zana\Security\RoleAssignment\Scope;
use Zana\Security\RoleAssignment\ScopeHierarchyInterface;
use Zana\Security\SecurityContext;
use Zana\Security\UserInterface;

final readonly class Member implements UserInterface
{
    public function __construct(public string $id)
    {
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function roles(): array
    {
        return [];
    }
}

// region: hierarchy
// Events belong to organizations: an organization's roles count at its events.
final class EventsInOrganizations implements ScopeHierarchyInterface
{
    /** @param array<string, string> $organizationOf event id => organization id */
    public function __construct(private array $organizationOf)
    {
    }

    public function parentsOf(Scope $scope): array
    {
        $organization = $scope->type === 'event' ? ($this->organizationOf[$scope->id] ?? null) : null;

        return $organization === null ? [] : [new Scope('organization', $organization)];
    }
}

final readonly class ConcertEvent implements HasAuthorizationScope
{
    public function __construct(public string $id)
    {
    }

    public function authorizationScope(): Scope
    {
        return new Scope('event', $this->id);
    }
}
// endregion: hierarchy

// In an application: security.role_assignments => 'database', and the provider builds these.
$store = new InMemoryRoleAssignmentStore();
$authorizer = new Authorizer(
    ['owner' => ['events.publish', 'tickets.scan'], 'scanner' => ['tickets.scan']],   // security.roles
    [],
    [],
    $store,
    new EventsInOrganizations(['jazz-kin' => 'org-1', 'rumba-goma' => 'org-2']),
);

// region: assign
$roles = new RoleAssignments($store);
$roles->assign('amani', 'owner', new Scope('organization', 'org-1'));
$roles->assign('bisimwa', 'scanner', new Scope('event', 'rumba-goma'));

$can = static fn (string $user, string $permission, object $subject): string => $authorizer->can(new Member($user), $permission, $subject) ? 'yes' : 'no';
echo 'Amani publishes Jazz Kinshasa (org-1): ', $can('amani', 'events.publish', new ConcertEvent('jazz-kin')), "\n";
echo 'Amani publishes Rumba Goma (org-2): ', $can('amani', 'events.publish', new ConcertEvent('rumba-goma')), "\n";
echo 'Bisimwa scans at Rumba Goma: ', $can('bisimwa', 'tickets.scan', new ConcertEvent('rumba-goma')), "\n";
echo 'Bisimwa scans at Jazz Kinshasa: ', $can('bisimwa', 'tickets.scan', new ConcertEvent('jazz-kin')), "\n";
// endregion: assign

// region: behavior
// Every command that names an event is checked against the signed-in user's roles there.
interface EventCommand
{
    public function eventId(): string;

    public function permission(): string;
}

final readonly class EventScopeBehavior implements PipelineBehavior
{
    public function __construct(private SecurityContext $context, private Authorizer $authorizer)
    {
    }

    public function handle(object $message, Closure $next): mixed
    {
        if ($message instanceof EventCommand) {
            $this->authorizer->authorize($this->context->requireUser(), $message->permission(), new Scope('event', $message->eventId()));
        }

        return $next($message);
    }
}
// endregion: behavior

final readonly class ScanTicket implements EventCommand
{
    public function __construct(public string $event, public string $ticket)
    {
    }

    public function eventId(): string
    {
        return $this->event;
    }

    public function permission(): string
    {
        return 'tickets.scan';
    }
}

$context = new SecurityContext();
$context->setUser(new Member('bisimwa'));
$behavior = new EventScopeBehavior($context, $authorizer);
foreach (['rumba-goma', 'jazz-kin'] as $event) {
    try {
        $result = $behavior->handle(new ScanTicket($event, 'T-1'), static fn (): string => 'scanned');
    } catch (ForbiddenHttpException) {
        $result = 'forbidden';
    }
    echo "Scan at {$event}: {$result}\n";
}

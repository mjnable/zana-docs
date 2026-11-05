<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Audit\AuditEntry;
use Zana\Audit\AuditLog;
use Zana\Audit\AuditLogMigration;
use Zana\Audit\AuditLogStore;
use Zana\Audit\SecurityEventsRecorder;
use Zana\Database\ConnectionFactory;
use Zana\Events\EventDispatcher;
use Zana\Events\ListenerProvider;
use Zana\Security\Event\SecurityEvent;
use Zana\Security\Event\SignInFailed;
use Zana\Security\Event\SignInSucceeded;

$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
(new AuditLogMigration())->up($db->schema());   // php bin/zana audit:install-log, then migrate
$store = new AuditLogStore($db, chain: true);    // audit.log.chain

// region: events
// With audit.log.enabled, the provider adds this listener: mj-zana/security's sign-in events go
// into the log. SessionLogin dispatches them.
$events = new EventDispatcher((new ListenerProvider())->listen(SecurityEvent::class, new SecurityEventsRecorder($store)));
$events->dispatch(new SignInFailed('amani@example.com', SignInFailed::INVALID_CREDENTIALS, '198.51.100.7'));
$events->dispatch(new SignInSucceeded('7', '198.51.100.7'));
// endregion: events

// region: record
// In an application, AuditLog is scoped: the actor is the signed-in user, and the client address and
// correlation id are the request's.
$audit = new AuditLog($store, static fn (): string => '7', static fn (): string => '198.51.100.7');
$audit->record('payout.account_changed', subject: 'organizer:12', details: ['bank' => 'Rawbank']);
// endregion: record

foreach (array_reverse($store->entries()) as $entry) {
    assert($entry instanceof AuditEntry);
    echo "#{$entry->sequence} {$entry->action}", $entry->actor === null ? '' : " by {$entry->actor}", $entry->subject === null ? '' : " on {$entry->subject}", ' ', json_encode($entry->details), "\n";
}

// region: verify
// php bin/zana audit:verify. Someone with database access changes the bank of entry 3:
echo 'Chain intact: ', $store->verify()->intact ? 'yes' : 'no', "\n";
$db->table('audit_log')->where('sequence', 3)->update(['details' => '{"bank":"Elsewhere"}']);
$report = $store->verify();
echo 'Chain intact: ', $report->intact ? 'yes' : 'no', " (entry {$report->brokenAt}: {$report->problem})\n";
// endregion: verify

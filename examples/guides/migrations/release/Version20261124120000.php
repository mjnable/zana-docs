<?php

declare(strict_types=1);

namespace Docs\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A data migration, as make:migration add_launch_dates --data writes it, filled in. It runs in a transaction on
 * every database, and has no down(): it cannot be rolled back.
 */
final class Version20261124120000 extends AbstractMigration
{
    // Copied in: the migration must give the same result after the code changes.
    private const string OLD = 'The site went live in November.';
    private const string NEW = 'The site first went live in July 2024, and its new version in November 2026.';

    public function getDescription(): string
    {
        return 'add_launch_dates';
    }

    public function up(Schema $schema): void
    {
        // Read with $this->connection: it runs at once.
        $found = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM case_studies WHERE summary = ?', [self::OLD]);
        $this->write("{$found} case study to change.");
        $this->warnIf($found === 0, 'No case study has the old summary: nothing to change.');

        // Write with addSql(): queued until up() returns, so after every read. Matching the old value leaves a
        // summary an editor has changed alone, and makes a second run change nothing.
        $this->addSql('UPDATE case_studies SET summary = ? WHERE summary = ?', [self::NEW, self::OLD]);
    }
}

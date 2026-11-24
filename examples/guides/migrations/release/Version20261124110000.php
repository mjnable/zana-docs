<?php

declare(strict_types=1);

namespace Docs\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * A schema migration, as make:migration add_launched_on writes it, filled in.
 */
final class Version20261124110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add_launched_on';
    }

    // A schema change commits the transaction on MySQL and MariaDB anyway.
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('case_studies')->addColumn('launched_on', Types::DATE_MUTABLE, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('case_studies')->dropColumn('launched_on');
    }
}

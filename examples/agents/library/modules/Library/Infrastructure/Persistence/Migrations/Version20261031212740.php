<?php

declare(strict_types=1);

namespace Modules\Library\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * A schema migration: the books table, where DatabaseBookRepository stores each book.
 *
 * It runs in a transaction where a schema change can be undone (SQLite, PostgreSQL), and without one on MySQL and
 * MariaDB, where a schema change commits the transaction anyway. Change data in a migration of its own:
 * `make:migration <name> --data`.
 *
 * Once it has run, never rename this class or change its namespace: it is recorded by that name, and would run
 * again as a new migration.
 */
final class Version20261031212740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'create_books_table';
    }

    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('books');
        $table->addColumn('id', Types::GUID);
        $table->addColumn('version', Types::INTEGER);
        $table->addColumn('title', Types::STRING, ['length' => 200]);
        $table->addColumn('author', Types::STRING, ['length' => 200]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['title'], 'books_title_index');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('books');
    }
}

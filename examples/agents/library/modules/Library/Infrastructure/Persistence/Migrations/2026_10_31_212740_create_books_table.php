<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('books', static function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->integer('version');
            $t->string('title', 200);
            $t->string('author', 200);
            $t->index('title');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('books');
    }
};

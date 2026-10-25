<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('patients', static function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 100);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('patients');
    }
};

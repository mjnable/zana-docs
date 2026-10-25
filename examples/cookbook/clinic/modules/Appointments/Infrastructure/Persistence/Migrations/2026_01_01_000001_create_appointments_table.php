<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('appointments', static function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('patient_id');
            $t->dateTime('starts_at');
            $t->string('reason', 200);
            $t->index('patient_id');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('appointments');
    }
};

<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

// A migration written before 2.37: it keeps running as it is.
return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('case_studies', static function (Blueprint $t): void {
            $t->id();
            $t->string('slug', 100);
            $t->text('summary');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('case_studies');
    }
};

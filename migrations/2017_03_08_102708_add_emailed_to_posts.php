<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up'   => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) {
            $table->string('notified')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('posts', function (Blueprint $table) {
            $table->dropColumn('notified');
            $table->dropColumn('emailed');
        });
    }
];
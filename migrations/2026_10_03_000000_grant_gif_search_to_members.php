<?php

use Illuminate\Database\Schema\Builder;

// Members may search for GIFs; it's a row in the Permissions grid after this.
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        if (! $db->table('group_permission')->where(['group_id' => 3, 'permission' => 'reel.use'])->exists()) {
            $db->table('group_permission')->insert(['group_id' => 3, 'permission' => 'reel.use']);
        }
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()->table('group_permission')->where('permission', 'reel.use')->delete();
    },
];

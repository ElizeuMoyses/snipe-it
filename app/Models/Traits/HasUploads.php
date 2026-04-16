<?php

namespace App\Models\Traits;

use App\Models\Actionlog;

trait HasUploads
{
    public function uploads()
    {
        return $this->hasMany(Actionlog::class, 'item_id')
            ->where('item_type', self::class)
            ->where('action_type', '=', 'uploaded')
            ->whereNotNull('filename')
            ->whereNotIn('filename', function ($query) {
                $query->select('filename')
                    ->from('action_logs as deleted_uploads')
                    ->where('deleted_uploads.item_type', '=', self::class)
                    ->where('deleted_uploads.action_type', '=', 'upload deleted')
                    ->whereColumn('deleted_uploads.item_id', 'action_logs.item_id');
            });
    }
}

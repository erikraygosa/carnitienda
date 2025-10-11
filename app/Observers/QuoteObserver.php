<?php

namespace App\Observers;

use App\Models\Quote;
use App\Services\DocumentLogService;

class QuoteObserver
{
    public function created(Quote $q): void
    {
        app(DocumentLogService::class)->log($q, 'CREATED', null, $q->status, $q->created_by);
    }

    public function updated(Quote $q): void
    {
        if ($q->wasChanged('status')) {
            $old = $q->getOriginal('status');
            $new = $q->status;
            app(DocumentLogService::class)->log($q, 'STATUS_CHANGED', $old, $new, $q->owner_id);
        }
    }
}

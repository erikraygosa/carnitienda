<?php

namespace App\Services;

use App\Models\DocumentActivityLog;
use Illuminate\Database\Eloquent\Model;

class DocumentLogService
{
    public function log(Model $document, string $action, ?string $old = null, ?string $new = null, ?int $userId = null, ?string $note = null): void
    {
        DocumentActivityLog::create([
            'document_type' => get_class($document),
            'document_id'   => $document->id,
            'action'        => $action,
            'old_status'    => $old,
            'new_status'    => $new,
            'user_id'       => $userId,
            'nota'          => $note,
        ]);
    }
}

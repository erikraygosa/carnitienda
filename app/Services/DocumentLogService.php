<?php

namespace App\Services;

use App\Models\DocumentActivityLog;
use Illuminate\Database\Eloquent\Model;

class DocumentLogService
{
    public function log(Model $document, string $action,
        ?string $old = null, ?string $new = null,
        ?int $userId = null, ?string $note = null,
        ?array $changes = null): void
    {
        DocumentActivityLog::create([
            'document_type' => get_class($document),
            'document_id'   => $document->id,
            'action'        => $action,
            'old_status'    => $old,
            'new_status'    => $new,
            'user_id'       => $userId ?? auth()->id(),
            'nota'          => $note,
            'changes'       => $changes ?: null,
        ]);
    }

    public function diff(Model $model, array $skip = []): array
    {
        $always = ['updated_at','created_at','updated_by','sello_cfdi','sello_sat','xml_timbrado'];
        $changes = [];
        foreach ($model->getDirty() as $field => $new) {
            if (in_array($field, array_merge($always, $skip))) continue;
            $changes[$field] = ['old' => $model->getOriginal($field), 'new' => $new];
        }
        return $changes;
    }
}

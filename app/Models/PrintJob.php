<?php

namespace App\Models;

use App\Enums\PrintDocumentType;
use App\Enums\PrintJobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'print_agent_id', 'requested_by_id', 'document_type', 'document_id', 'document_label',
        'status', 'attempts', 'copies', 'adjunto_ids', 'available_at', 'claimed_at',
        'lease_expires_at', 'completed_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => PrintDocumentType::class,
            'status' => PrintJobStatus::class,
            'adjunto_ids' => 'array',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}

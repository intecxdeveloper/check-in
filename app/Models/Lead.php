<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    public const STATUSES = [
        'new',
        'follow-up',
        'contacted',
        'qualified',
        'closed',
    ];

    protected $fillable = [
        'visitor_id',
        'status',
        'assigned_to',
        'staff_notes',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

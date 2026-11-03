<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    /**
     * NADI follow-up with this visitor. These are not visitor-company roles.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'new',
        'follow-up',
        'contacted',
        'qualified',
        'closed',
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'new' => 'New',
        'follow-up' => 'Follow up',
        'contacted' => 'Contacted',
        'qualified' => 'Good fit',
        'closed' => 'Done',
    ];

    /**
     * @var array<string, string>
     */
    public const MEANINGS = [
        'new' => 'Just checked in. Nobody from NADI has acted yet.',
        'follow-up' => 'Still need to call, email, or meet them.',
        'contacted' => 'You already reached out.',
        'qualified' => 'Worth a real NADI conversation.',
        'closed' => 'Finished — no more follow-up.',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function meaning(string $status): string
    {
        return self::MEANINGS[$status] ?? '';
    }

    protected $fillable = [
        'visitor_id',
        'status',
        'staff_notes',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}

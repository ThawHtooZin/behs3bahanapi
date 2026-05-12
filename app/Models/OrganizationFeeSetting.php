<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationFeeSetting extends Model
{
    protected $fillable = [
        'start_year',
        'start_month',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'start_month' => 'integer',
        ];
    }

    /**
     * Singleton accessor. Lazily creates a row defaulting to the current month
     * so the system always has a defined collection start.
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        $now = now();

        return static::create([
            'start_year' => (int) $now->year,
            'start_month' => (int) $now->month,
        ]);
    }
}

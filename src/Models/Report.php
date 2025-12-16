<?php

namespace Meita\ReportsGenerator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'connection',
        'base_query',
        'filters',
        'options',
        'cache_ttl',
        'is_active',
    ];

    protected $casts = [
        'filters' => 'array',
        'options' => 'array',
        'is_active' => 'boolean',
        'cache_ttl' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTable()
    {
        return config('reports-generator.table', $this->table);
    }
}

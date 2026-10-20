<?php

namespace ReelMaker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ReelMaker\Rendering\TemplateSpec;

class Template extends Model
{
    protected $table = 'reel_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(Render::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function spec(): TemplateSpec
    {
        return TemplateSpec::fromArray($this->config);
    }
}

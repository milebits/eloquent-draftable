<?php

namespace Milebits\EloquentDraftable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use function constVal;
use function now;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @method Builder withDrafts()
 * @method Builder onlyDrafts()
 */
trait Draftable
{
    /**
     * Exclude draft records from query results by default.
     *
     * @return void
     */
    public static function bootDraftable()
    {
        self::addGlobalScope('published', function (Builder $query) {
            $column = constVal(static::class, 'PUBLISHED_AT_COLUMN', 'published_at');
            $query->whereNotNull($column)->where($column, '<=', now());
        });
    }

    /**
     * @return string
     */
    public function getPublishedAtColumn(): string
    {
        return constVal($this, 'PUBLISHED_AT_COLUMN', 'published_at');
    }

    /**
     * @return string
     */
    public function getQualifiedPublishedAtColumn(): string
    {
        return $this->qualifyColumn($this->getPublishedAtColumn());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return string
     */
    public function decidePublishedAtColumn(Builder $builder): string
    {
        return count(property_exists($builder, 'joins') ? $builder->joins : []) > 0 ? $this->getQualifiedPublishedAtColumn() : $this->getPublishedAtColumn();
    }

    /**
     * Include draft records in query results.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDrafts(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('published');
    }

    /**
     * Exclude published records from query results.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyDrafts(Builder $builder): Builder
    {
        return $this->scopeWithDrafts($builder)->where(function (Builder $builder) {
            $builder->whereNull('published_at')->orWhere($this->decidePublishedAtColumn($builder), '>', now());
        });
    }

    /**
     * Determine if the model is published.
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return !is_null($this->{$this->getPublishedAtColumn()}) && $this->{$this->getPublishedAtColumn()} <= now();
    }

    /**
     * Determine if the model is draft.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return !$this->isPublished();
    }

    /**
     * Set the value of the model's published at column.
     *
     * @param \Illuminate\Support\Carbon|string|null $date
     *
     * @return $this
     */
    public function setPublishedAt(Carbon|string|null $date): self
    {
        if (!is_null($date)) {
            $date = Carbon::parse($date);
        }

        $this->{$this->getPublishedAtColumn()} = $date;

        return $this;
    }

    /**
     * Set the value of the model's published status.
     *
     * @param bool $published
     *
     * @return $this
     */
    public function setPublished(bool $published = true): self
    {
        if (!$published) return $this->setPublishedAt(null);
        if ($this->isDraft()) return $this->setPublishedAt(now());
        return $this;
    }

    /**
     * Schedule the model to be published.
     *
     * @param \Illuminate\Support\Carbon|string|null $date
     *
     * @return $this
     */
    public function publishAt(Carbon|string|null $date): static
    {
        $this->setPublishedAt($date)->save();
        return $this;
    }

    /**
     * Mark the model as published.
     *
     * @param bool $publish
     *
     * @return $this
     */
    public function publish(bool $publish = true): static
    {
        $this->setPublished($publish)->save();
        return $this;
    }

    /**
     * Mark the model as draft.
     *
     * @return $this
     */
    public function draft(): static
    {
        return $this->publish(false);
    }
}
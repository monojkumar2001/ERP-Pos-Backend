<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompanyScope
{
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($query->getModel()->getTable() . '.company_id', $companyId);
    }
}

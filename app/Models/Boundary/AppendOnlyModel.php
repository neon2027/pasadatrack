<?php

namespace App\Models\Boundary;

use App\Models\Fleet\TeamScopedModel;
use LogicException;

/**
 * Instance writes preserve ledger/history rows. Bulk queries, quiet writes and raw
 * SQL bypass events: services and database permissions must forbid those mutations.
 */
abstract class AppendOnlyModel extends TeamScopedModel
{
    protected static function booted(): void
    {
        static::updating(function (self $model): never {
            throw new LogicException($model::class.' is append-only.');
        });

        static::deleting(function (self $model): never {
            throw new LogicException($model::class.' is append-only.');
        });
    }
}

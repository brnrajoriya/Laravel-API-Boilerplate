<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner check used by generated policies:
 *  - a record with a `user_id` column belongs to that user,
 *  - a record without it is not owned (any authenticated user may change it).
 */
trait ChecksOwnership
{
    protected function isOwner(User $user, Model $model, string $ownerKey = 'user_id'): bool
    {
        if (! array_key_exists($ownerKey, $model->getAttributes())) {
            return true;
        }

        return (int) $model->getAttribute($ownerKey) === (int) $user->getKey();
    }
}

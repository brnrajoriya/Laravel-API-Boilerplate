<?php

namespace App\Policies;

use App\Models\Dummy;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

/**
 * Who may do what with dummies. Checked by DummyController through Gate::authorize().
 *
 * Default: any authenticated user may list, view and create; a dummy with a `user_id`
 * column may only be changed by its owner. Change any rule below (roles, teams, admins, ...).
 */
class DummyPolicy
{
    use ChecksOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Dummy $dummy): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Dummy $dummy): bool
    {
        return $this->isOwner($user, $dummy);
    }

    public function delete(User $user, Dummy $dummy): bool
    {
        return $this->isOwner($user, $dummy);
    }

    public function restore(User $user, Dummy $dummy): bool
    {
        return $this->isOwner($user, $dummy);
    }
}

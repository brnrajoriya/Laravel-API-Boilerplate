<?php

namespace App\Policies;

use App\Models\Upload;
use App\Models\User;

/**
 * Uploaded files are private to the user who uploaded them.
 */
class UploadPolicy
{
    public function view(User $user, Upload $upload): bool
    {
        return $user->id === $upload->user_id;
    }

    public function delete(User $user, Upload $upload): bool
    {
        return $user->id === $upload->user_id;
    }
}

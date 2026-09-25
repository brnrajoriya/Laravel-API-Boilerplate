<?php

namespace Tests\Feature;

use App\Models\Dummy;
use App\Models\Upload;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_records_can_only_be_changed_by_their_owner(): void
    {
        $policy = new class
        {
            use ChecksOwnership;

            public function check(User $user, Model $model): bool
            {
                return $this->isOwner($user, $model);
            }
        };

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $upload = Upload::factory()->for($owner)->create();

        $this->assertTrue($policy->check($owner, $upload));
        $this->assertFalse($policy->check($other, $upload));

        // No `user_id` column: not an owned resource.
        $this->assertTrue($policy->check($other, Dummy::factory()->create()));
    }

    public function test_generated_policy_is_used_by_the_controller(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $dummy = Dummy::factory()->create();

        $this->patchJson("/api/v1/dummies/{$dummy->id}", ['title' => 'Changed'])->assertOk();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Dummy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_index_returns_a_paginator_in_the_envelope(): void
    {
        Dummy::factory()->count(30)->create();

        $this->getJson('/api/v1/dummies?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 30)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_index_sorts_searches_and_counts(): void
    {
        Dummy::factory()->create(['title' => 'Banana']);
        Dummy::factory()->create(['title' => 'Apple', 'description' => 'fruit']);
        Dummy::factory()->create(['title' => 'Cherry', 'description' => '100% fruit_juice']);

        $this->getJson('/api/v1/dummies?order_by=title&order_type=asc')
            ->assertJsonPath('data.data.*.title', ['Apple', 'Banana', 'Cherry']);

        $this->getJson('/api/v1/dummies?keyword=fruit')->assertJsonCount(2, 'data.data');

        // LIKE wildcards in the keyword are escaped.
        $this->getJson('/api/v1/dummies?keyword='.urlencode('100%'))->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/dummies?keyword='.urlencode('_'))->assertJsonCount(1, 'data.data');

        $this->getJson('/api/v1/dummies?return_type=count')->assertJsonPath('data', 3);
    }

    public function test_operations_filter_records(): void
    {
        Dummy::factory()->create(['category' => 'tech', 'title' => 'A']);
        Dummy::factory()->create(['category' => 'news', 'title' => 'B']);
        Dummy::factory()->create(['category' => 'finance', 'title' => 'C', 'description' => null]);

        $this->getJson('/api/v1/dummies?'.http_build_query(['operations' => [
            ['code' => 'where_in', 'parameters' => ['column' => 'category', 'values' => ['tech', 'news']]],
        ]]))->assertJsonPath('data.total', 2);

        $this->getJson('/api/v1/dummies?'.http_build_query(['operations' => [
            ['code' => 'where', 'parameters' => ['column' => 'title', 'operator' => '=', 'value' => 'B']],
        ]]))->assertJsonPath('data.data.0.title', 'B');

        $this->getJson('/api/v1/dummies?'.http_build_query(['operations' => [
            ['code' => 'where_null', 'parameters' => ['column' => 'description']],
        ]]))->assertJsonPath('data.data.0.title', 'C');
    }

    public function test_or_where_cannot_escape_the_other_constraints(): void
    {
        Dummy::factory()->create(['title' => 'visible', 'category' => 'tech']);
        Dummy::factory()->create(['title' => 'other', 'category' => 'news']);

        // keyword=visible AND (category = news OR title = other) → nothing, not "everything with title other".
        $response = $this->getJson('/api/v1/dummies?'.http_build_query([
            'keyword' => 'visible',
            'operations' => [
                ['code' => 'where', 'parameters' => ['column' => 'category', 'operator' => '=', 'value' => 'news']],
                ['code' => 'or_where', 'parameters' => ['column' => 'title', 'operator' => '=', 'value' => 'other']],
            ],
        ]));

        $response->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_non_whitelisted_columns_operators_and_codes_are_rejected(): void
    {
        $cases = [
            ['operations' => [['code' => 'where', 'parameters' => ['column' => 'id; drop table users', 'operator' => '=', 'value' => 1]]]],
            ['operations' => [['code' => 'where', 'parameters' => ['column' => 'title', 'operator' => 'sounds like', 'value' => 1]]]],
            ['operations' => [['code' => 'raw', 'parameters' => []]]],
            ['operations' => [['code' => 'where_has', 'relation' => 'secrets']]],
            ['order_by' => 'password'],
            ['select' => ['title', 'secret_column']],
            ['with' => 'user'],
            ['per_page' => 1000],
        ];

        foreach ($cases as $query) {
            $this->getJson('/api/v1/dummies?'.http_build_query($query))
                ->assertUnprocessable()
                ->assertJsonPath('status', 'fail')
                ->assertJsonPath('hasError', true);
        }
    }

    public function test_select_always_keeps_the_primary_key(): void
    {
        Dummy::factory()->create(['title' => 'Only title']);

        $row = $this->getJson('/api/v1/dummies?select[]=title')->assertOk()->json('data.data.0');

        $this->assertSame(['id', 'title'], array_keys($row));
    }

    public function test_store_validates_and_creates(): void
    {
        $this->postJson('/api/v1/dummies', ['title' => '', 'category' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['title', 'category']]);

        $this->postJson('/api/v1/dummies', ['title' => 'New', 'category' => 'tech', 'ignored' => 'x'])
            ->assertCreated()
            ->assertJsonPath('message', 'Dummy created successfully.')
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.category', 'tech')
            ->assertJsonMissingPath('data.ignored');
    }

    public function test_show_update_and_destroy(): void
    {
        $dummy = Dummy::factory()->create(['title' => 'Old']);

        $this->getJson("/api/v1/dummies/{$dummy->id}")->assertOk()->assertJsonPath('data.title', 'Old');

        $this->patchJson("/api/v1/dummies/{$dummy->id}", ['title' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.category', $dummy->category->value);

        $this->deleteJson("/api/v1/dummies/{$dummy->id}")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'Dummy deleted successfully.');

        $this->assertSoftDeleted($dummy);
        $this->getJson("/api/v1/dummies/{$dummy->id}")->assertNotFound();
    }

    public function test_filter_shorthand_and_grouped_count(): void
    {
        Dummy::factory()->count(3)->create(['category' => 'tech']);
        Dummy::factory()->create(['category' => 'news']);

        $this->getJson('/api/v1/dummies?filter[category]=tech')->assertJsonPath('data.total', 3);
        $this->getJson('/api/v1/dummies?filter[category][]=tech&filter[category][]=news')->assertJsonPath('data.total', 4);

        $this->getJson('/api/v1/dummies?return_type=count&group_by=category')
            ->assertOk()
            ->assertJsonPath('data', [
                ['category' => 'news', 'count' => 1],
                ['category' => 'tech', 'count' => 3],
            ]);

        // group_by alone would return one arbitrary row per group - rejected.
        $this->getJson('/api/v1/dummies?group_by=category')->assertUnprocessable();
    }

    public function test_simple_and_cursor_pagination(): void
    {
        Dummy::factory()->count(3)->create();

        $this->getJson('/api/v1/dummies?pagination=simple&per_page=2')
            ->assertOk()
            ->assertJsonMissingPath('data.total')
            ->assertJsonCount(2, 'data.data');

        $next = $this->getJson('/api/v1/dummies?pagination=cursor&per_page=2')
            ->assertOk()
            ->json('data.next_cursor');

        $this->getJson('/api/v1/dummies?pagination=cursor&per_page=2&cursor='.$next)->assertJsonCount(1, 'data.data');
    }

    public function test_resource_hides_internal_columns_and_respects_select(): void
    {
        Dummy::factory()->create();

        $row = $this->getJson('/api/v1/dummies')->json('data.data.0');
        $this->assertSame(['id', 'title', 'category', 'description', 'created_at', 'updated_at'], array_keys($row));

        $row = $this->getJson('/api/v1/dummies?select=title')->json('data.data.0');
        $this->assertSame(['id', 'title'], array_keys($row));
    }

    public function test_trashed_and_restore(): void
    {
        $dummy = Dummy::factory()->create();
        $dummy->delete();

        $this->getJson('/api/v1/dummies')->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/dummies?trashed=only')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $dummy->id);

        $this->postJson("/api/v1/dummies/{$dummy->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Dummy restored successfully.');

        $this->assertNotSoftDeleted($dummy);
    }

    public function test_bulk_destroy_is_all_or_nothing(): void
    {
        $dummies = Dummy::factory()->count(3)->create();

        $this->deleteJson('/api/v1/dummies', ['ids' => [$dummies[0]->id, 999]])->assertNotFound();
        $this->assertSame(3, Dummy::count());

        $this->deleteJson('/api/v1/dummies', ['ids' => []])->assertUnprocessable();
        $this->deleteJson('/api/v1/dummies', ['ids' => range(1, 101)])->assertUnprocessable();

        $this->deleteJson('/api/v1/dummies', ['ids' => [$dummies[0]->id, $dummies[1]->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertSame(1, Dummy::count());
    }

    public function test_missing_record_is_a_404_envelope(): void
    {
        $this->getJson('/api/v1/dummies/999')
            ->assertNotFound()
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('errors.0', 'Resource not found.');
    }
}

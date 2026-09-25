<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyRequest;
use App\Http\Requests\Dummy\IndexRequest;
use App\Http\Requests\Dummy\StoreRequest;
use App\Http\Requests\Dummy\UpdateRequest;
use App\Http\Resources\DummyResource;
use App\Models\Dummy;
use BrnRajoriya\QueryFlow\QueryFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Dummies
 *
 * This API allows you to manage dummies.
 */
class DummyController extends Controller
{
    /**
     * List dummies.
     *
     * Powered by QueryFlow: pagination, sorting, keyword search, filters, operations, relations,
     * counts and aggregates. Allowed columns and relations are declared on the Dummy model.
     */
    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Dummy::class);

        // Add your own constraints to the query, e.g. ->where('user_id', $request->user()->id).
        // Client filters are grouped, so they can never escape them.
        $result = QueryFlow::for(Dummy::query())
            ->apply($request->queryFlow())
            ->get();

        return $this->resource($result, DummyResource::class);
    }

    /**
     * Create a dummy.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Dummy::class);

        $dummy = Dummy::create($request->validated());

        return $this->resource($dummy, DummyResource::class, 'Dummy created successfully.', 201);
    }

    /**
     * Get a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     *
     * @queryParam with string Comma separated relations to include.
     * @queryParam with_count string Comma separated relations to count.
     */
    public function show(Request $request, Dummy $dummy): JsonResponse
    {
        Gate::authorize('view', $dummy);

        return $this->resource(QueryFlow::load($dummy, $request->query()), DummyResource::class);
    }

    /**
     * Update a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     */
    public function update(UpdateRequest $request, Dummy $dummy): JsonResponse
    {
        Gate::authorize('update', $dummy);

        $dummy->update($request->validated());

        return $this->resource($dummy, DummyResource::class, 'Dummy updated successfully.');
    }

    /**
     * Delete a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     */
    public function destroy(Dummy $dummy): JsonResponse
    {
        Gate::authorize('delete', $dummy);

        $dummy->delete();

        return $this->success(null, 'Dummy deleted successfully.');
    }

    /**
     * Delete many dummies.
     *
     * All or nothing: fails with 404 / 403 if any id is missing or not allowed.
     */
    public function bulkDestroy(BulkDestroyRequest $request): JsonResponse
    {
        $ids = $request->ids();
        $dummies = Dummy::query()->findMany($ids);

        if ($dummies->count() !== count($ids)) {
            return $this->fail('Some dummies were not found.', 404);
        }

        $dummies->each(fn (Dummy $dummy) => Gate::authorize('delete', $dummy));

        DB::transaction(fn () => $dummies->each->delete());

        return $this->success(['deleted' => count($ids)], count($ids).' dummies deleted successfully.');
    }

    /**
     * Restore a deleted dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     */
    public function restore(Dummy $dummy): JsonResponse
    {
        Gate::authorize('restore', $dummy);

        $dummy->restore();

        return $this->resource($dummy, DummyResource::class, 'Dummy restored successfully.');
    }
}

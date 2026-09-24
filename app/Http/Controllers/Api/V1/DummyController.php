<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dummy\IndexRequest;
use App\Http\Requests\Dummy\StoreRequest;
use App\Http\Requests\Dummy\UpdateRequest;
use App\Models\Dummy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Paginated list with sorting, keyword search, filters (`operations`), `select`, `with`,
     * `group_by` and `return_type=count`. Columns must be whitelisted on the Dummy model.
     */
    public function index(IndexRequest $request): JsonResponse
    {
        $query = Dummy::query()
            ->apiSelect($request->selectColumns())
            ->apiWith($request->relations())
            ->search($request->keyword())
            ->applyOperations($request->operations())
            ->apiGroupBy($request->groupBy())
            ->apiOrderBy($request->orderBy(), $request->orderType());

        if ($request->wantsCount()) {
            return $this->success($query->count());
        }

        return $this->success($query->paginate($request->perPage())->withQueryString());
    }

    /**
     * Create a dummy.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $dummy = Dummy::create($request->validated());

        return $this->success($dummy, 'Dummy created successfully.', 201);
    }

    /**
     * Get a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     *
     * @queryParam with string Comma separated relations to include. Example: user
     */
    public function show(Request $request, Dummy $dummy): JsonResponse
    {
        return $this->success($dummy->loadIncludes($this->relations($request)));
    }

    /**
     * Update a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     */
    public function update(UpdateRequest $request, Dummy $dummy): JsonResponse
    {
        $dummy->update($request->validated());

        return $this->success($dummy, 'Dummy updated successfully.');
    }

    /**
     * Delete a dummy.
     *
     * @urlParam dummy integer required The ID of the dummy. Example: 1
     */
    public function destroy(Dummy $dummy): JsonResponse
    {
        $dummy->delete();

        return $this->success(null, 'Dummy deleted successfully.');
    }
}

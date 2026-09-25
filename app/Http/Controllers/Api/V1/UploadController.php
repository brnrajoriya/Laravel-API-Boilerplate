<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\IndexRequest;
use App\Http\Requests\Upload\StoreRequest;
use App\Http\Resources\UploadResource;
use App\Models\Upload;
use BrnRajoriya\QueryFlow\QueryFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Uploads
 *
 * Upload images, videos and documents. Every user only sees and deletes their own files.
 */
class UploadController extends Controller
{
    /**
     * List my uploads.
     *
     * Supports the QueryFlow list parameters (keyword, filter, operations, order_by, pagination, ...).
     */
    public function index(IndexRequest $request): JsonResponse
    {
        // Scoped to the current user; client filters cannot escape this constraint.
        $result = QueryFlow::for($request->user()->uploads())
            ->apply($request->queryFlow())
            ->get();

        return $this->resource($result, UploadResource::class);
    }

    /**
     * Upload a file.
     *
     * Send `multipart/form-data` with a `file` field.
     *
     * @bodyParam file file required The image, video or document (see config/api.php for types and size).
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = (string) config('api.uploads.disk');
        $directory = config('api.uploads.directory').'/'.now()->format('Y/m');

        // `store()` uses a random, unguessable file name; the original name is only kept as metadata.
        $upload = $request->user()->uploads()->create([
            'disk' => $disk,
            'path' => $file->store($directory, $disk),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return $this->resource($upload, UploadResource::class, 'File uploaded successfully.', 201);
    }

    /**
     * Get an upload.
     *
     * @urlParam upload integer required The ID of the upload. Example: 1
     */
    public function show(Upload $upload): JsonResponse
    {
        Gate::authorize('view', $upload);

        return $this->resource($upload, UploadResource::class);
    }

    /**
     * Delete an upload.
     *
     * Deletes the record and the stored file.
     *
     * @urlParam upload integer required The ID of the upload. Example: 1
     */
    public function destroy(Upload $upload): JsonResponse
    {
        Gate::authorize('delete', $upload);

        $upload->delete();

        return $this->success(null, 'File deleted successfully.');
    }
}

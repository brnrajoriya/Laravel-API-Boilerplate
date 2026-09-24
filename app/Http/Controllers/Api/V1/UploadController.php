<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\Upload\StoreRequest;
use App\Models\Upload;
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
     */
    public function index(ApiIndexRequest $request): JsonResponse
    {
        $query = $request->user()->uploads()->getQuery()
            ->search($request->keyword())
            ->applyOperations($request->operations())
            ->apiOrderBy($request->orderBy(), $request->orderType());

        if ($request->wantsCount()) {
            return $this->success($query->count());
        }

        return $this->success($query->paginate($request->perPage())->withQueryString());
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

        return $this->success($upload, 'File uploaded successfully.', 201);
    }

    /**
     * Get an upload.
     *
     * @urlParam upload integer required The ID of the upload. Example: 1
     */
    public function show(Upload $upload): JsonResponse
    {
        Gate::authorize('view', $upload);

        return $this->success($upload);
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

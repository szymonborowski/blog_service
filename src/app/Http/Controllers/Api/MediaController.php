<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private MediaService $mediaService,
    ) {}

    public function index(Request $request)
    {
        $query = Media::query()->orderByDesc('created_at');

        if ($request->filled('search')) {
            $query->where('filename', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('mime_type')) {
            $query->where('mime_type', 'like', $request->input('mime_type') . '%');
        }

        $perPage = $request->input('per_page', 24);

        return MediaResource::collection($query->paginate($perPage));
    }

    public function store(StoreMediaRequest $request)
    {
        $media = $this->mediaService->upload(
            $request->file('file'),
            $request->input('alt'),
        );

        return new MediaResource($media);
    }

    public function show(Media $media)
    {
        return new MediaResource($media);
    }

    public function destroy(Media $media)
    {
        $this->mediaService->delete($media);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlideRequest;
use App\Http\Requests\UpdateSlideRequest;
use App\Http\Resources\SlideResource;
use App\Models\Slide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class SlideController extends Controller
{
    #[OA\Get(
        path: '/api/v1/slides',
        summary: 'List active slides',
        description: 'Returns active slides ordered by position (public endpoint)',
        tags: ['Slides'],
        responses: [
            new OA\Response(response: 200, description: 'List of active slides', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Slide')),
                ]
            )),
        ]
    )]
    public function index()
    {
        $slides = Cache::remember('slides.active', 300, function () {
            return Slide::active()->ordered()->get();
        });

        return SlideResource::collection($slides);
    }

    #[OA\Get(
        path: '/api/internal/slides',
        summary: 'List all slides (internal)',
        description: 'Returns all slides including inactive, ordered by position',
        tags: ['Slides'],
        responses: [
            new OA\Response(response: 200, description: 'List of all slides'),
        ]
    )]
    public function indexAll()
    {
        $slides = Slide::ordered()->get();

        return SlideResource::collection($slides);
    }

    #[OA\Get(
        path: '/api/internal/slides/{slide}',
        summary: 'Show a slide',
        tags: ['Slides'],
        parameters: [
            new OA\Parameter(name: 'slide', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Slide details', content: new OA\JsonContent(ref: '#/components/schemas/Slide')),
            new OA\Response(response: 404, description: 'Slide not found'),
        ]
    )]
    public function show(Slide $slide)
    {
        return new SlideResource($slide);
    }

    #[OA\Post(
        path: '/api/internal/slides',
        summary: 'Create a slide',
        tags: ['Slides'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'type'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'type', type: 'string', enum: ['image', 'html']),
                new OA\Property(property: 'image_url', type: 'string', maxLength: 2048, nullable: true),
                new OA\Property(property: 'html_content', type: 'string', nullable: true),
                new OA\Property(property: 'link_url', type: 'string', maxLength: 2048, nullable: true),
                new OA\Property(property: 'link_text', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'position', type: 'integer', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Slide created', content: new OA\JsonContent(ref: '#/components/schemas/Slide')),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreSlideRequest $request)
    {
        $slide = Slide::create($request->validated());
        Cache::forget('slides.active');

        return (new SlideResource($slide))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/api/internal/slides/{slide}',
        summary: 'Update a slide',
        tags: ['Slides'],
        parameters: [
            new OA\Parameter(name: 'slide', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'type', type: 'string', enum: ['image', 'html']),
                new OA\Property(property: 'image_url', type: 'string', maxLength: 2048, nullable: true),
                new OA\Property(property: 'html_content', type: 'string', nullable: true),
                new OA\Property(property: 'link_url', type: 'string', maxLength: 2048, nullable: true),
                new OA\Property(property: 'link_text', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'position', type: 'integer', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Slide updated', content: new OA\JsonContent(ref: '#/components/schemas/Slide')),
            new OA\Response(response: 404, description: 'Slide not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateSlideRequest $request, Slide $slide)
    {
        $slide->update($request->validated());
        Cache::forget('slides.active');

        return new SlideResource($slide);
    }

    #[OA\Delete(
        path: '/api/internal/slides/{slide}',
        summary: 'Delete a slide',
        description: 'Soft deletes the slide',
        tags: ['Slides'],
        parameters: [
            new OA\Parameter(name: 'slide', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Slide deleted'),
            new OA\Response(response: 404, description: 'Slide not found'),
        ]
    )]
    public function destroy(Slide $slide)
    {
        $slide->delete();
        Cache::forget('slides.active');

        return response()->json([
            'message' => 'Slide deleted successfully',
        ], 200);
    }

    #[OA\Patch(
        path: '/api/internal/slides/reorder',
        summary: 'Reorder slides',
        tags: ['Slides'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['slides'],
            properties: [
                new OA\Property(property: 'slides', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'position', type: 'integer'),
                    ]
                )),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Slides reordered'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function reorder(Request $request)
    {
        $request->validate([
            'slides' => ['required', 'array'],
            'slides.*.id' => ['required', 'integer', 'exists:slides,id'],
            'slides.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->slides as $item) {
            Slide::where('id', $item['id'])->update(['position' => $item['position']]);
        }
        Cache::forget('slides.active');

        return response()->json([
            'message' => 'Slides reordered successfully',
        ], 200);
    }
}

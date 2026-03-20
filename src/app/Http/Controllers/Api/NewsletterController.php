<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NewsletterController extends Controller
{
    #[OA\Post(
        path: '/v1/newsletter/subscribe',
        summary: 'Subscribe to newsletter',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Subscribed successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $validated['email']]);

        if ($subscriber->exists && $subscriber->unsubscribed_at === null) {
            return response()->json([
                'message' => 'Already subscribed.',
            ], 200);
        }

        $subscriber->unsubscribed_at = null;
        $subscriber->confirmed_at = now();
        $subscriber->save();

        return response()->json([
            'message' => 'Subscribed successfully.',
        ], 201);
    }

    #[OA\Post(
        path: '/v1/newsletter/unsubscribe',
        summary: 'Unsubscribe from newsletter',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Unsubscribed'),
        ]
    )]
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $subscriber = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($subscriber) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return response()->json([
            'message' => 'Unsubscribed successfully.',
        ]);
    }
}

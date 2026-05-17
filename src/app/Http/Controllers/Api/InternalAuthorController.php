<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;

class InternalAuthorController extends Controller
{
    public function index(): JsonResponse
    {
        $authors = Author::orderBy('name')->get(['user_id', 'name', 'email']);

        $data = $authors->map(fn($a) => [
            'id'    => $a->user_id,
            'name'  => $a->name,
            'email' => $a->email,
        ]);

        return response()->json(['data' => $data]);
    }
}

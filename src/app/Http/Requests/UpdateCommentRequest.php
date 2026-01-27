<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth handled by middleware
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'required', 'string', 'min:3'],
            'status' => ['sometimes', 'required', 'in:pending,approved,rejected'],
        ];
    }
}

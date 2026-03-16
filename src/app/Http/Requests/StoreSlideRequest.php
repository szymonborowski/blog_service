<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:image,html'],
            'image_url' => ['required_if:type,image', 'nullable', 'url', 'max:2048'],
            'html_content' => ['required_if:type,html', 'nullable', 'string'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'link_text' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

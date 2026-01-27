<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth handled by middleware
        return true;
    }

    public function rules(): array
    {
        $tagId = $this->route('tag');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'slug' => ['sometimes', 'required', 'string', 'max:50', 'unique:tags,slug,' . $tagId],
        ];
    }
}

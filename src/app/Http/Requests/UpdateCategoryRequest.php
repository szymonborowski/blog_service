<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Auth handled by middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category   = $this->route('category');
        $categoryId = $category instanceof \App\Models\Category
            ? $category->getKey()
            : $category;

        return [
            'name'      => ['sometimes', 'required', 'string', 'max:100'],
            'slug'      => ['sometimes', 'required', 'string', 'max:100', 'unique:categories,slug,' . $categoryId],
            'color'     => ['nullable', 'string', 'max:20'],
            'icon'      => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}

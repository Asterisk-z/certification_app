<?php

namespace App\Http\Requests;

use App\Enums\BlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(BlockType::class)],
            'value' => ['nullable', 'string', 'max:2000'],
            'is_dynamic' => ['boolean'],
            'is_visible' => ['boolean'],
            'pos_x' => ['nullable', 'numeric', 'min:0'],
            'pos_y' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:10'],
            'height' => ['nullable', 'numeric', 'min:10'],
            'font_family' => ['sometimes', 'string', 'max:100'],
            'font_size' => ['sometimes', 'integer', 'min:6', 'max:200'],
            'font_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_weight' => ['sometimes', Rule::in(['normal', 'bold', '300', '400', '500', '600', '700', '800'])],
            'text_align' => ['sometimes', Rule::in(['left', 'center', 'right'])],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,svg', 'max:5120'],
        ];
    }
}

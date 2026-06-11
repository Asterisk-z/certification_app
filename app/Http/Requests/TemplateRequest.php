<?php

namespace App\Http\Requests;

use App\Enums\DurationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('template')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash:ascii',
                Rule::unique('certificate_templates', 'code')->ignore($templateId)->withoutTrashed(),
            ],
            'duration' => ['nullable', 'integer', 'min:1', 'max:1000', 'required_with:duration_type'],
            'duration_type' => ['nullable', Rule::enum(DurationType::class), 'required_with:duration'],
            'status' => ['sometimes', Rule::in(['draft', 'ready', 'archived'])],
            'background' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'image', 'mimes:jpeg,png,webp', 'max:10240',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }
}

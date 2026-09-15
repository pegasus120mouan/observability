<?php

namespace App\Http\Requests;

use App\Models\Dashboard;
use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Dashboard::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'seed_layout' => ['sometimes', 'boolean'],
        ];
    }
}

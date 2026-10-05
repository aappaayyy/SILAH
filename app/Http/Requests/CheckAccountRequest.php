<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['identifier' => trim((string) $this->input('identifier'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:100', 'regex:/^[\w.@+\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return ['identifier.*' => 'Masukkan NIM/NIDN, email, atau nomor HP yang valid.'];
    }
}

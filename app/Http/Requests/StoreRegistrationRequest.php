<?php

namespace App\Http\Requests;

use App\Enums\Tipe;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
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
        $this->merge([
            'nim_nidn' => trim((string) $this->input('nim_nidn')),
            'nama'     => preg_replace('/\s+/', ' ', trim((string) $this->input('nama'))),
            'email'    => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'no_wa'    => Phone::normalize((string) $this->input('no_wa')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mahasiswa = $this->input('tipe') === Tipe::Mahasiswa->value;
        return [
            'tipe'     => ['required', Rule::enum(Tipe::class)],
            'nama'     => ['required', 'string', 'min:3', 'max:255', "regex:/^[\pL\s.,'\-]+$/u"],
            'nim_nidn' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'email'    => ['nullable', 'email:rfc', 'max:255'],
            'no_wa'    => ['required', 'regex:/^628\d{7,13}$/'],
            'unit'     => ['nullable', 'string', 'max:100'],

            // KTM wajib hanya untuk mahasiswa; peran lain mengabaikan berkas
            'dokumen'  => $mahasiswa
                ? ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:2048']
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'dokumen.required' => 'Mahasiswa wajib mengunggah foto KTM.',
            'dokumen.max'      => 'Ukuran berkas maksimal 2 MB.',
            'dokumen.mimes'    => 'Berkas harus berformat JPG, PNG, atau PDF.',
            'no_wa.regex'      => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'nama.regex'       => 'Nama hanya boleh berisi huruf, spasi, titik, dan koma.',
        ];
    }
}

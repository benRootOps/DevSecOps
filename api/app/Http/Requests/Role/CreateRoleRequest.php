<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'nom'           => ['required','string','max:100'],
            'code'          => ['required','string','max:60','regex:/^[a-z_]+$/'],
            'description'   => ['nullable','string'],
            'permissions'   => ['nullable','array'],
            'permissions.*' => ['string','exists:permissions,code'],
        ];
    }
    public function messages(): array {
        return ['code.regex' => 'Le code doit contenir uniquement des lettres minuscules et underscores.'];
    }
}

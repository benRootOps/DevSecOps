<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['email' => ['required','email'], 'mot_de_passe' => ['required','string','min:6']];
    }
    public function messages(): array {
        return ['email.required' => "L'email est obligatoire.", 'mot_de_passe.required' => 'Le mot de passe est obligatoire.'];
    }
}

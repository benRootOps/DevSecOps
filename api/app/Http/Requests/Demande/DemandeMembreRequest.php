<?php

namespace App\Http\Requests\Demande;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire soumis par l'admin universitaire pour créer
 * un compte enseignant ou étudiant.
 */
class DemandeMembreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'       => ['required', 'string', 'max:100'],
            'prenom'    => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'genre'     => ['nullable', 'string', 'max:20'],

            // Champs spécifiques enseignant
            'specialite'         => ['nullable', 'string', 'max:200'],
            'grade'              => ['nullable', 'string', 'max:100'],
            'type_contrat'       => ['nullable', 'string', 'max:50'],
            'date_prise_service' => ['nullable', 'date'],

            // Champs spécifiques étudiant
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite'    => ['nullable', 'string', 'max:100'],
            'adresse'        => ['nullable', 'string'],
            'tuteur_nom'     => ['nullable', 'string', 'max:200'],
            'tuteur_telephone'=> ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'    => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required'  => "L'email est obligatoire.",
            'email.unique'    => 'Cette adresse email est déjà utilisée.',
        ];
    }
}

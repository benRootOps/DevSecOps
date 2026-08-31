<?php

namespace App\Http\Requests\Demande;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire public soumis par l'université sur le site Edusphere.
 * Pas de JWT requis → authorize() retourne true.
 */
class DemandeEtablissementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Informations de l'établissement
            'etablissement.nom'       => ['required', 'string', 'max:200'],
            'etablissement.adresse'   => ['nullable', 'string'],
            'etablissement.ville'     => ['nullable', 'string', 'max:100'],
            'etablissement.pays'      => ['nullable', 'string', 'max:100'],
            'etablissement.telephone' => ['nullable', 'string', 'max:30'],
            'etablissement.email'     => ['nullable', 'email', 'max:150'],

            // Informations de l'admin universitaire (futur compte)
            'admin.nom'               => ['required', 'string', 'max:100'],
            'admin.prenom'            => ['required', 'string', 'max:100'],
            'admin.email'             => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'admin.telephone'         => ['nullable', 'string', 'max:30'],

            // Informations complémentaires de la demande
            'message'                 => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'etablissement.nom.required' => "Le nom de l'établissement est obligatoire.",
            'admin.nom.required'         => "Le nom de l'administrateur est obligatoire.",
            'admin.prenom.required'      => "Le prénom de l'administrateur est obligatoire.",
            'admin.email.required'       => "L'email de l'administrateur est obligatoire.",
            'admin.email.unique'         => "Cette adresse email est déjà utilisée.",
        ];
    }
}

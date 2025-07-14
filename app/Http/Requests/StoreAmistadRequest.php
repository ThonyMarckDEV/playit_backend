<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreAmistadRequest extends FormRequest
{
    // /**
    //  * Determine if the user is authorized to make this request.
    //  */
    // public function authorize(): bool
    // {
    //     return Auth::check(); // Solo usuarios autenticados pueden crear amistades
    // }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idAmigo' => 'required|exists:usuarios,idUsuario|different:idUsuario',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'idAmigo.required' => 'El ID del amigo es obligatorio.',
            'idAmigo.exists' => 'El usuario especificado no existe.',
            'idAmigo.different' => 'No puedes añadirte a ti mismo como amigo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'idUsuario' => Auth::id(),
        ]);
    }
}
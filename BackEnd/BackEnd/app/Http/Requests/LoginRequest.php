<?php
// app/Http/Requests/LoginRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permitir todos los requests de login
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-zA-Z0-9._@-]+$/' // Solo caracteres alfanuméricos, puntos, guiones y @
            ],
            'pass' => [
                'required',
                'string',
                'min:6',
                'max:255'
            ],
            'remember_me' => [
                'nullable',
                'boolean'
            ],
            'device_id' => [
                'nullable',
                'string',
                'max:100'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user.required' => 'El usuario es obligatorio',
            'user.min' => 'El usuario debe tener al menos 3 caracteres',
            'user.max' => 'El usuario no puede exceder 100 caracteres',
            'user.regex' => 'El usuario solo puede contener letras, números, puntos, guiones y @',
            'pass.required' => 'La contraseña es obligatoria',
            'pass.min' => 'La contraseña debe tener al menos 6 caracteres',
            'pass.max' => 'La contraseña no puede exceder 255 caracteres'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            // Validaciones personalizadas adicionales
            $user = $this->input('user');
            
            // Verificar si es email válido o username válido
            if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-zA-Z0-9._-]+$/', $user)) {
                $validator->errors()->add('user', 'Debe ser un email válido o un nombre de usuario válido');
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Errores de validación en el login',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422)
        );
    }
}
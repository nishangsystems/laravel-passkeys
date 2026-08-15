<?php

namespace NishangSystems\Passkeys\Http\Requests;

class GetPasskeyLoginOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            config('passkeys.user_lookup_field', 'email') => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }
}

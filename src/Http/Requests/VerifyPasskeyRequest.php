<?php

namespace NishangSystems\Passkeys\Http\Requests;

class VerifyPasskeyRequest extends FormRequest
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
            'passkey' => ['required', 'array'],
            'session_id' => ['required', 'string'],
        ];
    }
}

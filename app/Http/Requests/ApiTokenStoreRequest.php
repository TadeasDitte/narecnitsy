<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiTokenStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

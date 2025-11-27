<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'role_id' => 'required',
            'law_firm_id' => 'required_if:role_id,3', // Asume que 2 es el ID de "Abogado"
        ];
    }
}

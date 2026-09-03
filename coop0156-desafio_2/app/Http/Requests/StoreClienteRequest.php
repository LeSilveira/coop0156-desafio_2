<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCpf;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    use NormalizaCpf;
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'digits:11', 'unique:clientes,cpf'],
            'email' => ['required', 'email', 'max:255', 'unique:clientes,email'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'renda_mensal' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

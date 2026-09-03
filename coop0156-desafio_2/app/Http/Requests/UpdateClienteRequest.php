<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    use NormalizaCpf;

    public function rules(): array
    {
        $cliente = $this->route('cliente');

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'cpf' => ['sometimes', 'required', 'digits:11', Rule::unique('clientes', 'cpf')->ignore($cliente)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente)],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'renda_mensal' => ['sometimes', 'required', 'numeric', 'gt:0'],
        ];
    }
}

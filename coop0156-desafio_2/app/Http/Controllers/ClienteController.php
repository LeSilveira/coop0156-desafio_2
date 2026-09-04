<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    /**
     * Lista todos os clientes cadastrados.
     *
     * GET /api/clientes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Cliente::latest()->paginate(15)
        );
    }

    /**
     * Cadastra um novo cliente.
     *
     * POST /api/clientes
     *
     * Validações esperadas:
     *  - nome: obrigatório, string
     *  - cpf: obrigatório, 11 dígitos numéricos, único na tabela clientes
     *  - email: obrigatório, formato e-mail válido, único na tabela clientes
     *  - telefone: opcional, string
     *  - renda_mensal: obrigatório, numérico, mínimo de 0
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return response()->json($cliente, Response::HTTP_CREATED);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json($cliente);
    }

    /**
     * Atualiza os dados de um cliente existente.
     *
     * PUT /api/clientes/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return response()->json($cliente);
    }

    /**
     * Remove um cliente do sistema.
     *
     * DELETE /api/clientes/{id}
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Cliente $cliente): Response
    {
        $cliente->delete();

        return response()->noContent();
    }
}

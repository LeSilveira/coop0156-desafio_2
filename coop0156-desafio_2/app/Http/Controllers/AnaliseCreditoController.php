<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Enums\StatusAnalise;
use App\Http\Requests\SolicitarAnaliseRequest;
use App\Jobs\ProcessarContratacaoJob;
use App\Models\AnaliseCredito;
use App\Services\AnaliseCreditoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AnaliseCreditoController extends Controller
{
    public function __construct(private readonly AnaliseCreditoService $analises) {}
    /**
     * Solicita uma nova análise de crédito.
     *
     * POST /api/analise-credito
     *
     * Campos esperados no body (JSON):
     *  - nome: string, obrigatório
     *  - cpf: string, obrigatório (11 dígitos)
     *  - renda_mensal: numeric, obrigatório
     *  - tipo_credito: string, obrigatório (pessoal | imobiliario | automotivo)
     *  - valor_solicitado: numeric, obrigatório
     *
     * Fluxo esperado:
     *  1. Validar os dados de entrada.
     *  2. Persistir a análise no banco com status 'pendente'.
     *  3. Consultar a API do Bureau de Crédito (GET /api/mock/bureau/{cpf}) via Http::.
     *  4. Tratar falhas de comunicação com o Bureau (timeout, HTTP 500, resposta malformada).
     *  5. Aplicar as regras de negócio (renda mínima, faixas de score, comprometimento de renda).
     *  6. Atualizar e retornar a análise persistida com o resultado final.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function solicitar(SolicitarAnaliseRequest $request): JsonResponse
    {
        // TODO: Implementar validação, consulta ao Bureau e regras de análise.
        $analise = $this->analises->solicitar($request->validated());

        return response()->json($analise, Response::HTTP_CREATED);
    }

    /**
     * Confirma a contratação de uma análise de crédito aprovada.
     *
     * POST /api/analise-credito/{id}/contratar
     *
     * Fluxo esperado:
     *  1. Buscar a análise pelo ID (retornar 404 se não encontrada).
     *  2. Verificar se o status é 'aprovado' (retornar 422 se não for).
     *  3. Atualizar o status para 'contratado'.
     *  4. Retornar confirmação de sucesso.
     *
     * ⭐ DIFERENCIAL OPCIONAL: Em vez de atualizar diretamente para 'contratado',
     *    atualize para 'processando_contratacao' e dispare o Job ProcessarContratacaoJob
     *    para a fila. O Job ficará responsável por finalizar e atualizar para 'contratado'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function contratar($id): JsonResponse
    {
        $analise = AnaliseCredito::findOrFail($id);

        if ($analise->status !== StatusAnalise::APROVADO) {
            return response()->json([
                'message' => 'Somente análises aprovadas podem ser contratadas.',
                'status' => $analise->status,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $analise->update(['status' => StatusAnalise::PROCESSANDO_CONTRATACAO]);

        ProcessarContratacaoJob::dispatch($analise->id);

        return response()->json([
            'message' => 'Contratação enviada para processamento.',
            'analise' => $analise->fresh(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function solicitar(SolicitarAnaliseRequest $request): JsonResponse
    {
        $analise = $this->analises->solicitar($request->validated());

        return response()->json($analise, Response::HTTP_CREATED);
    }

    /**
     * Confirma a contratação de uma análise de crédito aprovada.
     *
     * POST /api/analise-credito/{id}/contratar
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function contratar(int $id): JsonResponse
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

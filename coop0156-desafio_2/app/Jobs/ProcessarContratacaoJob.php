<?php

namespace App\Jobs;

use App\Enums\StatusAnalise;
use App\Models\AnaliseCredito;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarContratacaoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $analiseId) {}

    public function handle(): void
    {
        $analise = AnaliseCredito::find($this->analiseId);

        if (! $analise) {
            Log::warning('Contratação ignorada: análise não encontrada.', ['analise_id' => $this->analiseId]);

            return;
        }

        if ($analise->status !== StatusAnalise::PROCESSANDO_CONTRATACAO) {
            Log::warning('Contratação ignorada: status inesperado.', [
                'analise_id' => $analise->id,
                'status' => $analise->status->value,
            ]);

            return;
        }

        $analise->update(['status' => StatusAnalise::CONTRATADO]);

        Log::info('Contratação de crédito finalizada.', [
            'analise_id' => $analise->id,
            'cliente_id' => $analise->cliente_id,
            'valor_solicitado' => $analise->valor_solicitado,
            'valor_parcela' => $analise->valor_parcela,
        ]);
    }
}

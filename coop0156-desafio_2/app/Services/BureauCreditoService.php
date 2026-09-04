<?php

namespace App\Services;

use App\Exceptions\BureauIndisponivelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BureauCreditoService
{
    /**
     * @throws \Exception
     */
    public function consultarScore(string $cpf): int
    {
        $url = rtrim((string) config('services.score_bureau.url'), '/').'/'.$cpf;

        try {
            $resposta = Http::timeout((int) config('services.score_bureau.timeout', 3))
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $e) {
            $this->falhar($cpf, 'Não foi possível conectar ao Bureau de Crédito.', $e->getMessage());
        }

        if ($resposta->failed()) {
            $this->falhar($cpf, 'O Bureau de Crédito retornou um erro.', 'HTTP '.$resposta->status());
        }

        $score = $resposta->json('score');

        if (! is_numeric($score)) {
            $this->falhar($cpf, 'O Bureau de Crédito retornou uma resposta inválida.', 'resposta sem a chave "score"');
        }

        return (int) $score;
    }

    private function falhar(string $cpf, string $mensagem, string $motivo): never
    {
        Log::warning('Falha na consulta ao Bureau de Crédito.', [
            'cpf' => substr($cpf, 0, 3).'********',
            'motivo' => $motivo,
        ]);

        throw new BureauIndisponivelException($mensagem);
    }
}

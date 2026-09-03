<?php

namespace App\Services;

use App\Enums\StatusAnalise;
use App\Exceptions\BureauIndisponivelException;
use App\Models\AnaliseCredito;
use App\Models\Cliente;

/**
 * Regras de negócio da análise de crédito.
 */
class AnaliseCreditoService
{
    public const RENDA_MINIMA = 1500.00;

    public const SCORE_MINIMO = 400;

    public const SCORE_TAXA_REDUZIDA = 700;

    public const TAXA_SCORE_ALTO = 2.9;

    public const TAXA_SCORE_MEDIO = 4.5;

    public const TOTAL_PARCELAS = 12;

    public const COMPROMETIMENTO_MAXIMO = 0.30;

    public function __construct(private readonly BureauCreditoService $bureau) {}

    /**
     *
     * @throws \Exception 
     */
    public function solicitar(array $dados): AnaliseCredito
    {
        $cliente = Cliente::firstOrCreate(
            ['cpf' => $dados['cpf']],
            [
                'nome' => $dados['nome'],
                'renda_mensal' => $dados['renda_mensal'],
            ]
        );

        $analise = $cliente->analises()->create([
            'cpf' => $dados['cpf'],
            'nome' => $dados['nome'],
            'renda_mensal' => $dados['renda_mensal'],
            'tipo_credito' => $dados['tipo_credito'],
            'valor_solicitado' => $dados['valor_solicitado'],
            'status' => StatusAnalise::PENDENTE,
        ]);

        $score = $this->bureau->consultarScore($dados['cpf']);

        $analise->update($this->avaliar(
            $score,
            (float) $dados['renda_mensal'],
            (float) $dados['valor_solicitado'],
        ));

        return $analise;
    }

    public function avaliar(int $score, float $rendaMensal, float $valorSolicitado): array
    {
        if ($rendaMensal < self::RENDA_MINIMA) {
            return [
                'score' => $score,
                'status' => StatusAnalise::REPROVADO,
                'motivo_rejeicao' => 'Renda mínima insuficiente',
            ];
        }

        if ($score < self::SCORE_MINIMO) {
            return [
                'score' => $score,
                'status' => StatusAnalise::REPROVADO,
                'motivo_rejeicao' => 'Score de crédito muito baixo',
            ];
        }

        $taxa = $score >= self::SCORE_TAXA_REDUZIDA ? self::TAXA_SCORE_ALTO : self::TAXA_SCORE_MEDIO;
        $parcela = $this->calcularParcela($valorSolicitado, $taxa);
        $comprometeuRenda = $parcela > round($rendaMensal * self::COMPROMETIMENTO_MAXIMO, 2);

        return [
            'score' => $score,
            'taxa_juros' => $taxa,
            'valor_parcela' => $parcela,
            'status' => $comprometeuRenda ? StatusAnalise::REPROVADO : StatusAnalise::APROVADO,
            'motivo_rejeicao' => $comprometeuRenda ? 'Comprometimento de renda superior a 30%' : null,
        ];
    }

    public function calcularParcela(float $valorSolicitado, float $taxaMensal): float
    {
        $juros = $valorSolicitado * ($taxaMensal / 100) * self::TOTAL_PARCELAS;

        return round(($valorSolicitado + $juros) / self::TOTAL_PARCELAS, 2);
    }
}

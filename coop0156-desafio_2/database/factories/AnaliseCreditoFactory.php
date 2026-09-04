<?php

namespace Database\Factories;

use App\Enums\StatusAnalise;
use App\Enums\TipoCredito;
use App\Models\AnaliseCredito;
use App\Models\Cliente;
use App\Services\AnaliseCreditoService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnaliseCredito>
 */
class AnaliseCreditoFactory extends Factory
{
    protected $model = AnaliseCredito::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cliente = Cliente::factory();

        return [
            'cliente_id' => $cliente,
            'cpf' => fake()->unique()->numerify('###########'),
            'nome' => fake()->name(),
            'renda_mensal' => 10000.00,
            'tipo_credito' => TipoCredito::PESSOAL,
            'valor_solicitado' => 10000.00,
            'status' => StatusAnalise::PENDENTE,
        ];
    }

    /**
     * Análise já aprovada — ponto de partida para testar a contratação.
     */
    public function aprovada(): static
    {
        return $this->state(fn () => [
            'status' => StatusAnalise::APROVADO,
            'score' => 850,
            'taxa_juros' => AnaliseCreditoService::TAXA_SCORE_ALTO,
            'valor_parcela' => 1123.33,
            'motivo_rejeicao' => null,
        ]);
    }

    /**
     * Análise reprovada.
     */
    public function reprovada(): static
    {
        return $this->state(fn () => [
            'status' => StatusAnalise::REPROVADO,
            'score' => 150,
            'motivo_rejeicao' => 'Score de crédito muito baixo',
        ]);
    }
}

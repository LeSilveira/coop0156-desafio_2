<?php

namespace Tests\Feature;

use App\Enums\StatusAnalise;
use App\Jobs\ProcessarContratacaoJob;
use App\Models\AnaliseCredito;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnaliseCreditoTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '12345678900';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }


    public function test_aprova_analise_com_score_alto_aplicando_taxa_de_2_9(): void
    {
        $this->fakeBureau(score: 850);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('status', StatusAnalise::APROVADO->value)
            ->assertJsonPath('score', 850)
            ->assertJsonPath('motivo_rejeicao', null);

        $analise = AnaliseCredito::sole();
        $this->assertEquals(2.9, (float) $analise->taxa_juros);
        // (10.000 + 10.000 * 2,9% * 12) / 12 = 1.123,33
        $this->assertEquals(1123.33, (float) $analise->valor_parcela);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/mock/bureau/'.self::CPF));
    }

    public function test_aprova_analise_com_score_medio_aplicando_taxa_de_4_5(): void
    {
        $this->fakeBureau(score: 550);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 20000.00,
        ]));

        $response->assertCreated()
            ->assertJsonPath('status', StatusAnalise::APROVADO->value)
            ->assertJsonPath('score', 550);

        $analise = AnaliseCredito::sole();
        $this->assertEquals(4.5, (float) $analise->taxa_juros);
        // (10.000 + 10.000 * 4,5% * 12) / 12 = 1.283,33
        $this->assertEquals(1283.33, (float) $analise->valor_parcela);
    }

    public function test_reprova_analise_por_renda_mensal_insuficiente(): void
    {
        $this->fakeBureau(score: 850);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 1000.00,
        ]));

        $response->assertCreated()
            ->assertJsonPath('status', StatusAnalise::REPROVADO->value)
            ->assertJsonPath('motivo_rejeicao', 'Renda mínima insuficiente');

        $analise = AnaliseCredito::sole();
        $this->assertNull($analise->valor_parcela);
        $this->assertNull($analise->taxa_juros);
    }

    public function test_reprova_analise_por_score_muito_baixo(): void
    {
        $this->fakeBureau(score: 150);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('status', StatusAnalise::REPROVADO->value)
            ->assertJsonPath('score', 150)
            ->assertJsonPath('motivo_rejeicao', 'Score de crédito muito baixo');

        $this->assertNull(AnaliseCredito::sole()->valor_parcela);
    }

    public function test_reprova_analise_por_comprometimento_de_renda_acima_de_30_porcento(): void
    {
        $this->fakeBureau(score: 850);

        // Parcela de R$ 1.123,33 contra 30% de R$ 2.000,00 (R$ 600,00).
        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 2000.00,
        ]));

        $response->assertCreated()
            ->assertJsonPath('status', StatusAnalise::REPROVADO->value)
            ->assertJsonPath('motivo_rejeicao', 'Comprometimento de renda superior a 30%');

        $this->assertEquals(1123.33, (float) AnaliseCredito::sole()->valor_parcela);
    }

    public function test_retorna_503_quando_bureau_responde_com_erro_500(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['error' => 'Erro interno'], 500),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503)
            ->assertJsonPath('erro', 'bureau_indisponivel')
            ->assertJsonStructure(['message', 'erro']);

        // A análise permanece pendente, permitindo uma nova tentativa.
        $this->assertDatabaseHas('analises_credito', [
            'cpf' => self::CPF,
            'status' => StatusAnalise::PENDENTE->value,
            'score' => null,
        ]);
    }

    public function test_retorna_503_quando_bureau_responde_sem_a_chave_score(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['cpf' => self::CPF, 'status_bureau' => 'ok']),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503)
            ->assertJsonPath('erro', 'bureau_indisponivel');

        $this->assertDatabaseHas('analises_credito', [
            'cpf' => self::CPF,
            'status' => StatusAnalise::PENDENTE->value,
        ]);
    }

    public function test_retorna_503_quando_bureau_atinge_o_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503)
            ->assertJsonPath('erro', 'bureau_indisponivel');
    }

    public function test_valida_os_campos_obrigatorios_da_solicitacao(): void
    {
        $response = $this->postJson('/api/analise-credito', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'cpf', 'renda_mensal', 'tipo_credito', 'valor_solicitado']);
    }

    public function test_valida_cpf_e_tipo_de_credito_da_solicitacao(): void
    {
        $response = $this->postJson('/api/analise-credito', $this->payload([
            'cpf' => '123',
            'tipo_credito' => 'consignado',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf', 'tipo_credito']);
    }

    public function test_cria_o_cliente_automaticamente_quando_o_cpf_e_novo(): void
    {
        $this->fakeBureau(score: 850);

        // CPF enviado com máscara, para validar também a normalização.
        $response = $this->postJson('/api/analise-credito', $this->payload([
            'cpf' => '123.456.789-00',
        ]));

        $response->assertCreated();

        $this->assertDatabaseCount('clientes', 1);
        $cliente = Cliente::sole();
        $this->assertSame(self::CPF, $cliente->cpf);
        $this->assertSame('João da Silva', $cliente->nome);
        $this->assertSame($cliente->id, AnaliseCredito::sole()->cliente_id);
    }

    public function test_reaproveita_o_cliente_existente_com_o_mesmo_cpf(): void
    {
        $this->fakeBureau(score: 850);
        $cliente = Cliente::factory()->create(['cpf' => self::CPF]);

        $this->postJson('/api/analise-credito', $this->payload())->assertCreated();
        $this->postJson('/api/analise-credito', $this->payload())->assertCreated();

        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseCount('analises_credito', 2);
        $this->assertSame(2, $cliente->analises()->count());
    }

    public function test_contratar_analise_aprovada_envia_o_job_para_a_fila(): void
    {
        Queue::fake();
        $analise = AnaliseCredito::factory()->aprovada()->create();

        $response = $this->postJson("/api/analise-credito/{$analise->id}/contratar");

        $response->assertOk()
            ->assertJsonPath('analise.status', StatusAnalise::PROCESSANDO_CONTRATACAO->value);

        Queue::assertPushed(
            ProcessarContratacaoJob::class,
            fn (ProcessarContratacaoJob $job) => $job->analiseId === $analise->id
        );
    }

    public function test_contratar_finaliza_a_analise_quando_a_fila_e_processada(): void
    {
        // QUEUE_CONNECTION=sync no phpunit.xml: o job roda na mesma requisição.
        $analise = AnaliseCredito::factory()->aprovada()->create();

        $response = $this->postJson("/api/analise-credito/{$analise->id}/contratar");

        $response->assertOk()
            ->assertJsonPath('analise.status', StatusAnalise::CONTRATADO->value);

        $this->assertSame(StatusAnalise::CONTRATADO, $analise->refresh()->status);
    }

    public function test_contratar_retorna_422_quando_a_analise_nao_esta_aprovada(): void
    {
        Queue::fake();
        $analise = AnaliseCredito::factory()->reprovada()->create();

        $response = $this->postJson("/api/analise-credito/{$analise->id}/contratar");

        $response->assertStatus(422);
        $this->assertSame(StatusAnalise::REPROVADO, $analise->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_contratar_retorna_404_quando_a_analise_nao_existe(): void
    {
        $this->postJson('/api/analise-credito/999/contratar')->assertNotFound();
    }

    public function test_job_finaliza_a_contratacao(): void
    {
        $analise = AnaliseCredito::factory()->aprovada()->create([
            'status' => StatusAnalise::PROCESSANDO_CONTRATACAO,
        ]);

        (new ProcessarContratacaoJob($analise->id))->handle();

        $this->assertSame(StatusAnalise::CONTRATADO, $analise->refresh()->status);
    }

    public function test_job_ignora_analise_que_nao_esta_processando_contratacao(): void
    {
        $analise = AnaliseCredito::factory()->reprovada()->create();

        (new ProcessarContratacaoJob($analise->id))->handle();

        $this->assertSame(StatusAnalise::REPROVADO, $analise->refresh()->status);
    }

    private function fakeBureau(int $score): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response([
                'cpf' => self::CPF,
                'score' => $score,
                'situacao' => 'ativo',
            ]),
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nome' => 'João da Silva',
            'cpf' => self::CPF,
            'renda_mensal' => 10000.00,
            'tipo_credito' => 'pessoal',
            'valor_solicitado' => 10000.00,
        ], $override);
    }
}

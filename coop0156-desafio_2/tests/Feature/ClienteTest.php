<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_cliente_com_dados_validos(): void
    {
        $response = $this->postJson('/api/clientes', [
            'nome' => 'Maria Oliveira',
            'cpf' => '98765432100',
            'email' => 'maria@example.com',
            'telefone' => '51999998888',
            'renda_mensal' => 4500.50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('nome', 'Maria Oliveira')
            ->assertJsonPath('cpf', '98765432100')
            ->assertJsonStructure(['id', 'nome', 'cpf', 'email', 'telefone', 'renda_mensal', 'created_at']);

        $this->assertDatabaseHas('clientes', [
            'cpf' => '98765432100',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_falha_ao_criar_cliente_sem_os_campos_obrigatorios(): void
    {
        $response = $this->postJson('/api/clientes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'cpf', 'email', 'renda_mensal']);
    }

    public function test_falha_ao_criar_cliente_com_cpf_duplicado(): void
    {
        Cliente::factory()->create(['cpf' => '11122233344']);

        $response = $this->postJson('/api/clientes', [
            'nome' => 'Outro Cliente',
            'cpf' => '11122233344',
            'email' => 'outro@example.com',
            'renda_mensal' => 3000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('cpf');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_falha_ao_criar_cliente_com_email_duplicado(): void
    {
        Cliente::factory()->create(['email' => 'repetido@example.com']);

        $response = $this->postJson('/api/clientes', [
            'nome' => 'Outro Cliente',
            'cpf' => '55566677788',
            'email' => 'repetido@example.com',
            'renda_mensal' => 3000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_falha_ao_criar_cliente_com_cpf_e_email_invalidos(): void
    {
        $response = $this->postJson('/api/clientes', [
            'nome' => 'Cliente Inválido',
            'cpf' => '123abc',
            'email' => 'nao-e-um-email',
            'renda_mensal' => -100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf', 'email', 'renda_mensal']);
    }

    public function test_lista_clientes_de_forma_paginada(): void
    {
        Cliente::factory()->count(3)->create();

        $response = $this->getJson('/api/clientes');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'nome', 'cpf', 'email', 'renda_mensal']],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonPath('total', 3);
    }

    public function test_exibe_um_cliente_existente(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->getJson("/api/clientes/{$cliente->id}");

        $response->assertOk()
            ->assertJsonPath('id', $cliente->id)
            ->assertJsonPath('cpf', $cliente->cpf);
    }

    public function test_retorna_404_ao_buscar_cliente_inexistente(): void
    {
        $this->getJson('/api/clientes/999')->assertNotFound();
    }

    public function test_atualiza_parcialmente_um_cliente_existente(): void
    {
        $cliente = Cliente::factory()->create(['nome' => 'Nome Antigo']);

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'nome' => 'Nome Novo',
        ]);

        $response->assertOk()->assertJsonPath('nome', 'Nome Novo');

        $cliente->refresh();
        $this->assertSame('Nome Novo', $cliente->nome);
        // Os demais campos permanecem intactos.
        $this->assertNotNull($cliente->email);
    }

    public function test_atualiza_cliente_mantendo_o_proprio_cpf_e_email(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'nome' => 'Nome Atualizado',
            'cpf' => $cliente->cpf,
            'email' => $cliente->email,
            'renda_mensal' => 7500,
        ]);

        $response->assertOk()->assertJsonPath('nome', 'Nome Atualizado');
        $this->assertEquals(7500, (float) $cliente->refresh()->renda_mensal);
    }

    public function test_falha_ao_atualizar_cliente_com_email_de_outro_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $outro = Cliente::factory()->create();

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'email' => $outro->email,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_retorna_404_ao_atualizar_cliente_inexistente(): void
    {
        $this->putJson('/api/clientes/999', ['nome' => 'Fantasma'])->assertNotFound();
    }

    public function test_remove_um_cliente_existente(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_retorna_404_ao_remover_cliente_inexistente(): void
    {
        $this->deleteJson('/api/clientes/999')->assertNotFound();
    }
}

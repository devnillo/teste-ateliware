<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.pokeapi.base_url' => 'https://pokeapi.co/api']);
});

test('GET /api/{name} retorna pokemon com envelope de sucesso', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu'), 200),
    ]);

    $this->getJson('/api/pikachu')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Pokémon recuperado com sucesso.',
            'data' => ['name' => 'pikachu'],
        ]);
});

test('GET /api/{name} retorna erro padronizado quando pokeapi falha', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(null, 500),
    ]);

    $this->getJson('/api/pikachu')
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Falha ao consultar a PokeAPI.',
            'errors' => [
                'pokeapi' => 'Não foi possível obter dados do Pokémon.',
            ],
        ]);
});

test('GET /api/battle retorna empate quando stats sao iguais', function () {
    $payload = pokemonApiPayload('pikachu', 50);

    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response($payload, 200),
        'https://pokeapi.co/api/v2/pokemon/raichu' => Http::response(pokemonApiPayload('raichu', 50), 200),
    ]);

    $this->getJson('/api/battle/pikachu/raichu')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Empate.',
            'data' => [
                'message' => 'Empate entre os Pokémons.',
            ],
        ]);
});

test('GET /api/battle retorna vitoria do primeiro pokemon', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu', 60), 200),
        'https://pokeapi.co/api/v2/pokemon/charmander' => Http::response(pokemonApiPayload('charmander', 39), 200),
    ]);

    $this->getJson('/api/battle/pikachu/charmander')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Pokémon pikachu venceu.',
        ])
        ->assertJsonPath('data.0.base_stat', 60);
});

test('GET /api/battle retorna vitoria do segundo pokemon', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu', 35), 200),
        'https://pokeapi.co/api/v2/pokemon/charmander' => Http::response(pokemonApiPayload('charmander', 39), 200),
    ]);

    $this->getJson('/api/battle/pikachu/charmander')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Pokémon charmander venceu.',
        ])
        ->assertJsonPath('data.name', 'charmander');
});

test('GET /api/battle retorna 404 quando pokemon nao existe', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu'), 200),
        'https://pokeapi.co/api/v2/pokemon/inexistente' => Http::response(null, 404),
    ]);

    $this->getJson('/api/battle/pikachu/inexistente')
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Falha ao consultar a PokeAPI.',
            'errors' => [
                'pokeapi' => 'Pokémon não encontrado.',
            ],
        ]);
});

test('nomes sao normalizados para minusculas antes da consulta', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu'), 200),
    ]);

    $this->getJson('/api/PIKACHU')
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertSent(fn ($request) => $request->url() === 'https://pokeapi.co/api/v2/pokemon/pikachu');
});

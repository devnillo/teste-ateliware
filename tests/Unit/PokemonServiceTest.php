<?php

use App\Http\Services\PokemonService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.pokeapi.base_url' => 'https://pokeapi.co/api']);
});

test('getPokemonByName consulta a url correta da pokeapi', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/pikachu' => Http::response(pokemonApiPayload('pikachu'), 200),
    ]);

    $service = new PokemonService;
    $response = $service->getPokemonByName('pikachu');

    expect($response->successful())->toBeTrue()
        ->and($response->json('name'))->toBe('pikachu');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://pokeapi.co/api/v2/pokemon/pikachu';
    });
});

test('getPokemonByName propaga falha da api', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon/invalido' => Http::response(null, 404),
    ]);

    $service = new PokemonService;
    $response = $service->getPokemonByName('invalido');

    expect($response->failed())->toBeTrue()
        ->and($response->status())->toBe(404);
});

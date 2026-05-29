<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\PokemonService;

class PokemonController extends Controller
{
    public function __construct(
        protected PokemonService $pokemonService,
    ) {}

    public function index(string $name)
    {
        $response = $this->pokemonService->getPokemonByName($name);

        if ($response->failed()) {
            return ApiResponse::error(
                message: 'Falha ao consultar a PokeAPI.',
                status: $response->status() ?: 502,
                errors: [
                    'pokeapi' => 'Não foi possível obter dados do Pokémon.',
                ],
            );
        }

        return ApiResponse::success(
            data: $response->json(),
            message: 'Pokémon recuperado com sucesso.',
        );
    }

    public function battle(string $pokemonName1, string $pokemonName2)
    {
        $pokemon1 = $this->pokemonService->getPokemonByName($pokemonName1);
        $pokemon2 = $this->pokemonService->getPokemonByName($pokemonName2);

        if($pokemon2->json() == null || $pokemon2->json() == null){
            return ApiResponse::error(
                message: 'Falha ao consultar a PokeAPI.',
                status: 404,
                errors: [
                    'pokeapi' => 'Pokémon não encontrado.',
                ],
            );
        }

        if($pokemon1->json()['stats'] == $pokemon2->json()['stats']){
            return ApiResponse::success(
                message: 'Empate.',
                status: 200,
                data: [
                    'message' => 'Empate entre os Pokémons.',
                    'pokemon1' => $pokemon1->json(),
                    'pokemon2' => $pokemon2->json(),
                ],
            );
        }

        if($pokemon1->json()['stats'][0]['base_stat'] > $pokemon2->json()['stats'][0]['base_stat']){
            return ApiResponse::success(
                message: "Pokémon {$pokemonName1} venceu.",
                status: 200,
                data: $pokemon1->json()['stats'],
            );
        }

        return ApiResponse::success(
            message: "Pokémon {$pokemonName2} venceu.",
            status: 200,
            data: $pokemon2->json(),
        );

    }


}

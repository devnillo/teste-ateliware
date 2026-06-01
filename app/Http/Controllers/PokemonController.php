<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\PokemonService;
use Illuminate\Http\Client\Response;

class PokemonController extends Controller
{
    public function __construct(
        protected PokemonService $pokemonService,
    ) {}

    public function index(string $name)
    {
        $response = $this->pokemonService->getPokemonByName($name);
        $pokemon = $this->parsePokemon($response);

        if ($pokemon === null) {
            return ApiResponse::error(
                message: 'Falha ao consultar a PokeAPI.',
                status: $response->status() ?: 502,
                errors: [
                    'pokeapi' => $response->notFound()
                        ? 'Pokémon não encontrado.'
                        : 'Não foi possível obter dados do Pokémon.',
                ]
            );
        }

        return ApiResponse::success(
            data: $pokemon,
            message: 'Pokémon recuperado com sucesso.',
        );
    }

    public function battle(string $pokemonName1, string $pokemonName2)
    {
        $response1 = $this->pokemonService->getPokemonByName($pokemonName1);
        $response2 = $this->pokemonService->getPokemonByName($pokemonName2);

        $pokemon1 = $this->parsePokemon($response1);
        $pokemon2 = $this->parsePokemon($response2);

        $invalidNames = [];

        if ($pokemon1 === null) {
            $invalidNames[] = $pokemonName1;
        }

        if ($pokemon2 === null) {
            $invalidNames[] = $pokemonName2;
        }

        if ($invalidNames !== []) {
            return ApiResponse::error(
                message: 'Falha ao consultar a PokeAPI.',
                status: 404,
                errors: [
                    'pokeapi' => count($invalidNames) === 1
                        ? "Pokémon '{$invalidNames[0]}' não encontrado."
                        : "Pokémons não encontrados: '".implode("', '", $invalidNames)."'.",
                ],
            );
        }

        if ($pokemon1['stats'][0]['base_stat'] === $pokemon2['stats'][0]['base_stat']) {
            return ApiResponse::success(
                message: 'Empate.',
                data: [
                    'message' => 'Empate entre os Pokémons.',
                    'pokemon1' => $pokemon1,
                    'pokemon2' => $pokemon2,
                ],
            );
        }

        if ($pokemon1['stats'][0]['base_stat'] > $pokemon2['stats'][0]['base_stat']) {
            return ApiResponse::success(
                message: "Pokémon {$pokemonName1} venceu.",
                data: [
                    'pokemon1' => $pokemon1,
                    'pokemon2' => $pokemon2,
                ],
            );
        }

        return ApiResponse::success(
            message: "Pokémon {$pokemonName2} venceu.",
            data: [
                'pokemon1' => $pokemon1,
                'pokemon2' => $pokemon2,
            ],
        );
    }

    /**
     * @return array{name: string, stats: array<int, array{base_stat: int, stat: array{name: string}}>}|null
     */
    private function parsePokemon(Response $response): ?array
    {
        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['name'], $data['stats']) || ! is_array($data['stats']) || $data['stats'] === []) {
            return null;
        }

        return [
            'name' => $data['name'],
            'stats' => $data['stats'],
        ];
    }
}

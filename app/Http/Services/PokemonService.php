<?php

namespace App\Http\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PokemonService
{
    public function getPokemonByName(string $name): Response
    {
        $baseUrl = rtrim(config('services.pokeapi.base_url'), '/');
        $name = strtolower(trim($name));

        return Http::get("{$baseUrl}/v2/pokemon/{$name}");
    }
}

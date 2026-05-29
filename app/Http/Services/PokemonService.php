<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class PokemonService
{
    public function getPokemonByName($name)
    {
        $baseUrl = rtrim(config('services.pokeapi.base_url'), '/');
        $pokemon = Http::get("{$baseUrl}/v2/pokemon/{$name}");

        return $pokemon;
    }
}

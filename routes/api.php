<?php

use App\Http\Controllers\PokemonController;
use Illuminate\Support\Facades\Route;

Route::get('/battle/{pokemonName1}/{pokemonName2}', [PokemonController::class, 'battle']);
Route::get('/{name}', [PokemonController::class, 'index']);

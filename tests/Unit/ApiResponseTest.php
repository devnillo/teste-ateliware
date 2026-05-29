<?php

use App\Http\Responses\ApiResponse;

test('success retorna envelope padronizado', function () {
    $response = ApiResponse::success(['id' => 1], 'Operação concluída.');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'success' => true,
            'message' => 'Operação concluída.',
            'data' => ['id' => 1],
        ])
        ->and($response->getData(true))->not->toHaveKey('errors');
});

test('success inclui meta quando informada', function () {
    $response = ApiResponse::success(null, 'OK', 200, ['page' => 1]);

    expect($response->getData(true)['meta']['page'])->toBe(1);
});

test('error retorna envelope padronizado', function () {
    $response = ApiResponse::error('Algo deu errado.', 422, ['campo' => 'inválido']);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => 'Algo deu errado.',
            'data' => null,
            'errors' => ['campo' => 'inválido'],
        ]);
});

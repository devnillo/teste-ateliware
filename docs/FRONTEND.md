# Orientações para o front-end (React)

Uma única tela em React: dois Pokémon, batalha via API Laravel, resultado com visual **gamificado** (conceito **Duelo no Estádio**).

## API

**Base:** `http://localhost:8000/api`

### Envelope padrão

```json
{ "success": true, "message": "...", "data": {} }
```

Erro: `success: false`, `data: null`, `errors: { "pokeapi": "..." }`.

### Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/{name}` | Detalhes do Pokémon |
| GET | `/api/battle/{name1}/{name2}` | Batalha por HP (`stats[0].base_stat`) |

### Fluxo recomendado na UI

1. `GET /api/{name1}` e `GET /api/{name2}` em paralelo → cards (sprite, nome, HP).
2. `GET /api/battle/{name1}/{name2}` → exibir `message` (vitória / empate / erro).

### Regra da batalha

- Compara apenas `stats[0]` (HP).
- Stats idênticos → empate (`data` traz `pokemon1` e `pokemon2`).
- Vitória → `message` contém o nome do vencedor; formato de `data` varia (ver controller).

## Campos úteis em `data`

- `sprites.other['official-artwork'].front_default` (fallback: `sprites.front_default`)
- `stats[0].base_stat` — barra de HP
- `name` — capitalize na UI

## CORS / dev

```ts
// vite.config.ts — proxy recomendado
server: { proxy: { '/api': 'http://localhost:8000' } }
```

Ou `VITE_API_URL=http://localhost:8000/api`.

## Design: Duelo no Estádio

- Fundo `#0d1f1a`, cards glass, acentos coral `#ff6b4a` e menta `#3dffa8`.
- Fontes: Rubik Mono One (título), Sora (nome), DM Sans (UI).
- Layout: card A | divisor | card B; botão pill “Comparar e batalhar”.
- HP em 10 segmentos; resultado em faixa inferior (slide-up).
- Vitória: outline menta + selo “KO”; empate: âmbar; perdedor: grayscale leve.
- Sem confetes nem fontes pixeladas.

## Back-end local

```bash
cp .env.example .env && php artisan key:generate
php artisan serve
php artisan test
```

`HTTP_VERIFY_SSL=false` no `.env` apenas se houver erro cURL 60 (proxy corporativo).

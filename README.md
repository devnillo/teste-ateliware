# Poké Battle API

API REST em **Laravel** para consultar Pokémon e simular batalhas usando dados da [PokeAPI](https://pokeapi.co/). O projeto **não utiliza banco de dados**: toda informação é obtida em tempo real da API externa.

Desenvolvido como back-end de um teste técnico, com respostas padronizadas e testes automatizados.

---

## Sumário

- [Funcionalidades](#funcionalidades)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Executando o projeto](#executando-o-projeto)
- [Uso da API](#uso-da-api)
- [Regras da batalha](#regras-da-batalha)
- [Testes automatizados](#testes-automatizados)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Licença](#licença)

---

## Funcionalidades

- Consulta de Pokémon por nome (`GET /api/{name}`)
- Batalha entre dois Pokémon por comparação de HP (`GET /api/battle/{name1}/{name2}`)
- Envelope JSON padronizado para sucesso e erro (`ApiResponse`)
- Integração com PokeAPI via `PokemonService`
- Testes unitários e de feature com [Pest](https://pestphp.com/) e `Http::fake()`
- Configuração sem banco de dados (sessão, cache e fila em arquivo/sync)

---

## Requisitos

| Ferramenta | Versão mínima |
|------------|----------------|
| PHP        | 8.3            |
| Composer   | 2.x            |
| Extensões PHP | `curl`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |

---

## Instalação

### 1. Clonar o repositório

```bash
git clone <url-do-repositorio> teste-ateliware
cd teste-ateliware
```

### 2. Instalar dependências PHP

```bash
composer install
```

### 3. Criar o arquivo de ambiente

```bash
cp .env.example .env
php artisan key:generate
```

### 4. (Opcional) Ajustar permissões de escrita

```bash
chmod -R 775 storage bootstrap/cache
```

Não é necessário rodar `php artisan migrate` — o projeto não persiste dados localmente.

---

## Configuração

Edite o arquivo `.env` conforme o ambiente:

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `APP_URL` | URL base da aplicação | `http://localhost` |
| `API_BASE_URL` | Base da PokeAPI | `https://pokeapi.co/api` |

### Exemplo `.env` para desenvolvimento local

```env
APP_URL=http://localhost:8000
API_BASE_URL=https://pokeapi.co/api
```

### Banco de dados

Não é utilizado. As variáveis `DB_*` permanecem comentadas no `.env.example`. Sessão e cache usam driver `file`; fila usa `sync`.

---

## Executando o projeto

### Servidor de desenvolvimento

```bash
php artisan serve
```

A API ficará disponível em: **http://localhost:8000**

Health check do Laravel: `GET http://localhost:8000/up`

### Limpar cache de configuração (após alterar `.env`)

```bash
php artisan config:clear
```

---

## Uso da API

**Base URL:** `http://localhost:8000/api`

Todas as respostas seguem o envelope abaixo.

### Envelope de resposta

**Sucesso:**

```json
{
  "success": true,
  "message": "Mensagem legível",
  "data": { }
}
```

**Erro:**

```json
{
  "success": false,
  "message": "Falha ao consultar a PokeAPI.",
  "data": null,
  "errors": {
    "pokeapi": "Descrição do erro"
  }
}
```

Sempre verifique o campo `success` antes de consumir `data`.

---

### Listar / buscar um Pokémon

```http
GET /api/{name}
```

| Parâmetro | Local | Descrição |
|-----------|--------|-----------|
| `name` | path | Nome do Pokémon em minúsculas (ex.: `pikachu`, `charizard`) |

**Exemplo com cURL:**

```bash
curl -s http://localhost:8000/api/pikachu | jq
```

**Resposta (200):**

```json
{
  "success": true,
  "message": "Pokémon recuperado com sucesso.",
  "data": {
    "id": 25,
    "name": "pikachu",
    "sprites": { "..." : "..." },
    "stats": [
      { "base_stat": 35, "stat": { "name": "hp" } },
      { "base_stat": 55, "stat": { "name": "attack" } }
    ]
  }
}
```

**Erros comuns:**

| HTTP | Situação |
|------|----------|
| 404 | Pokémon inexistente na PokeAPI |
| 502 | Falha de comunicação com a PokeAPI |

---

### Batalha entre dois Pokémon

```http
GET /api/battle/{pokemonName1}/{pokemonName2}
```

| Parâmetro | Local | Descrição |
|-----------|--------|-----------|
| `pokemonName1` | path | Primeiro lutador |
| `pokemonName2` | path | Segundo lutador |

**Exemplo com cURL:**

```bash
curl -s http://localhost:8000/api/battle/pikachu/charmander | jq
```

**Resposta — vitória (200):**

```json
{
  "success": true,
  "message": "Pokémon pikachu venceu.",
  "data": [ "... array de stats do vencedor ..." ]
}
```

**Resposta — empate (200):**

```json
{
  "success": true,
  "message": "Empate.",
  "data": {
    "message": "Empate entre os Pokémons.",
    "pokemon1": { "... payload completo ..." },
    "pokemon2": { "... payload completo ..." }
  }
}
```

**Resposta — Pokémon não encontrado (404):**

```json
{
  "success": false,
  "message": "Falha ao consultar a PokeAPI.",
  "data": null,
  "errors": {
    "pokeapi": "Pokémon não encontrado."
  }
}
```

> **Nota:** o formato de `data` varia entre empate e vitória. Para obter o payload completo de cada lutador, use `GET /api/{name}` antes ou depois da batalha.

---

## Regras da batalha

1. São buscados os dois Pokémon na PokeAPI.
2. Compara-se o **primeiro stat** do array (`stats[0]`, em geral **HP** / `base_stat`).
3. Se **todos** os stats forem iguais entre os dois → **empate**.
4. Se o HP do Pokémon 1 for maior → vitória do Pokémon 1.
5. Caso contrário → vitória do Pokémon 2.

Nomes devem ser enviados em **minúsculas**, como na PokeAPI (`mr-mime`, `pikachu`).

---

## Testes automatizados

O projeto usa **Pest** com suítes **Unit** e **Feature**. As chamadas à PokeAPI são simuladas com `Http::fake()` — os testes não dependem de internet.

```bash
# Todos os testes
php artisan test

# Apenas unitários
php artisan test --testsuite=Unit

# Apenas feature (endpoints HTTP)
php artisan test --testsuite=Feature
```

**Cobertura principal:**

| Arquivo | O que valida |
|---------|----------------|
| `tests/Unit/ApiResponseTest.php` | Envelope JSON padronizado |
| `tests/Unit/PokemonServiceTest.php` | Integração HTTP com PokeAPI |
| `tests/Feature/PokemonApiTest.php` | Endpoints `/api/{name}` e `/api/battle/...` |

---

## Estrutura do projeto

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── PokemonController.php   # Endpoints index e battle
│   ├── Responses/
│   │   └── ApiResponse.php         # Envelope success/error
│   └── Services/
│       └── PokemonService.php      # Cliente HTTP PokeAPI
├── Providers/
│   └── AppServiceProvider.php
routes/
├── api.php                         # Rotas /api/*
bootstrap/
└── app.php                         # Exception handler da API
tests/
├── Feature/PokemonApiTest.php
└── Unit/
    ├── ApiResponseTest.php
    └── PokemonServiceTest.php
```

---

## Licença

Este projeto utiliza o framework [Laravel](https://laravel.com), licenciado sob [MIT](https://opensource.org/licenses/MIT).

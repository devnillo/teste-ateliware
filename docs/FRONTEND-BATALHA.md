# Front-end — implementação da batalha

Guia para integrar a tela de batalha (React) com esta API.

**Base URL (dev):** `http://localhost:8000/api`

---

## Endpoints usados na batalha

| Ordem | Método | Rota | Para quê |
|-------|--------|------|----------|
| 1 (opcional) | `GET` | `/api/{name}` | Preview dos cards antes de batalhar |
| 2 (obrigatório) | `GET` | `/api/battle/{name1}/{name2}` | Resultado oficial da batalha |

### Batalha

```http
GET /api/battle/pikachu/charmander
```

**Nomes:** minúsculas, como na PokeAPI (`pikachu`, `mr-mime`, `charizard`).

---

## Envelope (sempre igual)

```ts
type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T | null;
  errors?: { pokeapi?: string };
};
```

Sempre validar `success` antes de usar `data`.

---

## Contrato da resposta de batalha

### Sucesso — vitória ou empate (HTTP 200)

O campo `data` **sempre** traz `pokemon1` e `pokemon2` com a mesma forma:

```ts
type PokemonSummary = {
  name: string;
  stats: Array<{
    base_stat: number;
    stat: { name: string };
  }>;
};

type BattleData = {
  message?: string; // só no empate: "Empate entre os Pokémons."
  pokemon1: PokemonSummary;
  pokemon2: PokemonSummary;
};
```

| Situação | `message` (exemplo) | Como detectar no front |
|----------|---------------------|-------------------------|
| Empate | `Empate.` | `message === 'Empate.'` |
| Vitória do 1º | `Pokémon pikachu venceu.` | `message` contém o nome do 1º |
| Vitória do 2º | `Pokémon charmander venceu.` | `message` contém o nome do 2º |

**Regra do back-end:** compara apenas `stats[0].base_stat` (HP). Empate = HP igual.

### Erro — Pokémon inexistente (HTTP 404)

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

---

## Fluxo recomendado na tela

```text
[Usuário preenche nome1 e nome2]
        │
        ▼
[Opcional] GET /api/{name1} + GET /api/{name2}  → sprites, HP nas barras
        │
        ▼
[Clica "Batalhar"]
        │
        ▼
GET /api/battle/{name1}/{name2}
        │
        ├── success → destacar vencedor / empate
        └── error   → exibir errors.pokeapi
```

**Dica:** use o endpoint de batalha como **fonte da verdade** do resultado. O preview (`GET /api/{name}`) serve só para UX (imagens, HP antes do duelo).

---

## CORS em desenvolvimento

**Proxy Vite (recomendado):**

```ts
// vite.config.ts
export default defineConfig({
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
});
```

Chamadas: `fetch('/api/battle/pikachu/charmander')`.

**Ou variável de ambiente:**

```env
VITE_API_URL=http://localhost:8000/api
```

---

## Implementação (React + TypeScript)

### 1. Cliente HTTP

```ts
// src/api/client.ts
const BASE = import.meta.env.VITE_API_URL ?? '/api';

export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T | null;
  errors?: { pokeapi?: string };
};

export type PokemonSummary = {
  name: string;
  stats: { base_stat: number; stat: { name: string } }[];
};

export type BattleData = {
  message?: string;
  pokemon1: PokemonSummary;
  pokemon2: PokemonSummary;
};

async function request<T>(path: string): Promise<ApiEnvelope<T>> {
  const res = await fetch(`${BASE}${path}`);
  return res.json();
}

export function getPokemon(name: string) {
  return request<PokemonSummary>(`/${encodeURIComponent(name.toLowerCase())}`);
}

export function battle(name1: string, name2: string) {
  const a = encodeURIComponent(name1.toLowerCase());
  const b = encodeURIComponent(name2.toLowerCase());
  return request<BattleData>(`/battle/${a}/${b}`);
}

export function getHp(pokemon: PokemonSummary): number {
  return pokemon.stats[0]?.base_stat ?? 0;
}

export function formatName(name: string): string {
  return name.charAt(0).toUpperCase() + name.slice(1);
}
```

### 2. Quem venceu?

```ts
// src/utils/battleResult.ts
import type { BattleData } from '../api/client';

export type BattleOutcome = 'tie' | 'pokemon1' | 'pokemon2';

export function resolveOutcome(
  message: string,
  name1: string,
  name2: string,
): BattleOutcome {
  if (message === 'Empate.') return 'tie';
  if (message.toLowerCase().includes(name1.toLowerCase())) return 'pokemon1';
  if (message.toLowerCase().includes(name2.toLowerCase())) return 'pokemon2';
  // fallback: comparar HP no client (mesma regra do back)
  return 'pokemon2';
}

export function outcomeFromData(
  message: string,
  data: BattleData,
  inputName1: string,
  inputName2: string,
): BattleOutcome {
  if (message === 'Empate.') return 'tie';

  const hp1 = data.pokemon1.stats[0]?.base_stat ?? 0;
  const hp2 = data.pokemon2.stats[0]?.base_stat ?? 0;

  if (hp1 === hp2) return 'tie';
  if (hp1 > hp2) return 'pokemon1';
  if (hp2 > hp1) return 'pokemon2';

  return resolveOutcome(message, inputName1, inputName2);
}
```

### 3. Hook da batalha

```tsx
// src/hooks/useBattle.ts
import { useState } from 'react';
import {
  battle,
  getPokemon,
  type BattleData,
  type PokemonSummary,
} from '../api/client';
import { outcomeFromData, type BattleOutcome } from '../utils/battleResult';

type Status = 'idle' | 'loading' | 'success' | 'error';

export function useBattle() {
  const [status, setStatus] = useState<Status>('idle');
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<{
    message: string;
    data: BattleData;
    outcome: BattleOutcome;
  } | null>(null);
  const [preview, setPreview] = useState<[PokemonSummary?, PokemonSummary?]>([
    undefined,
    undefined,
  ]);

  async function runBattle(name1: string, name2: string) {
    const n1 = name1.trim().toLowerCase();
    const n2 = name2.trim().toLowerCase();

    if (!n1 || !n2) {
      setError('Informe os dois Pokémon.');
      setStatus('error');
      return;
    }

    setStatus('loading');
    setError(null);
    setResult(null);

    try {
      // Preview paralelo (opcional — melhora UX)
      const [r1, r2] = await Promise.all([getPokemon(n1), getPokemon(n2)]);
      if (!r1.success || !r2.success) {
        throw new Error(
          r1.errors?.pokeapi ?? r2.errors?.pokeapi ?? 'Pokémon inválido.',
        );
      }
      setPreview([r1.data!, r2.data!]);

      const res = await battle(n1, n2);

      if (!res.success || !res.data) {
        throw new Error(res.errors?.pokeapi ?? res.message);
      }

      setResult({
        message: res.message,
        data: res.data,
        outcome: outcomeFromData(res.message, res.data, n1, n2),
      });
      setStatus('success');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro inesperado');
      setStatus('error');
    }
  }

  return { status, error, result, preview, runBattle };
}
```

### 4. Uso no componente

```tsx
// src/pages/BattleArena.tsx
import { useBattle } from '../hooks/useBattle';
import { formatName, getHp } from '../api/client';

export function BattleArena() {
  const { status, error, result, preview, runBattle } = useBattle();
  const [name1, setName1] = useState('pikachu');
  const [name2, setName2] = useState('charmander');

  const isLoading = status === 'loading';

  return (
    <main>
      <input value={name1} onChange={(e) => setName1(e.target.value)} />
      <input value={name2} onChange={(e) => setName2(e.target.value)} />

      <div className="arena">
        <PokemonCard
          pokemon={preview[0]}
          highlight={result?.outcome === 'pokemon1'}
          dimmed={result?.outcome === 'pokemon2'}
        />
        <PokemonCard
          pokemon={preview[1]}
          highlight={result?.outcome === 'pokemon2'}
          dimmed={result?.outcome === 'pokemon1'}
        />
      </div>

      <button
        disabled={isLoading}
        onClick={() => runBattle(name1, name2)}
      >
        {isLoading ? 'Batalhando…' : 'Iniciar batalha'}
      </button>

      {error && <p className="error">{error}</p>}

      {result && (
        <p className="result" data-outcome={result.outcome}>
          {result.message}
        </p>
      )}
    </main>
  );
}

function PokemonCard({
  pokemon,
  highlight,
  dimmed,
}: {
  pokemon?: { name: string; stats: { base_stat: number }[] };
  highlight?: boolean;
  dimmed?: boolean;
}) {
  if (!pokemon) return <div className="card empty">?</div>;

  const hp = getHp(pokemon);

  return (
    <article
      className={[
        'card',
        highlight && 'card--winner',
        dimmed && 'card--loser',
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <h2>{formatName(pokemon.name)}</h2>
      <p>HP: {hp}</p>
      {/* Barra: width = (hp / 255) * 100 — 255 é HP máximo típico */}
      <div className="hp-bar" style={{ width: `${Math.min(100, (hp / 255) * 100)}%` }} />
    </article>
  );
}
```

---

## Dicas de UX na batalha

| Dica | Implementação |
|------|----------------|
| Normalizar nomes | `.trim().toLowerCase()` antes de chamar a API |
| Loading | Desabilitar botão + texto “Batalhando…”; opcional skeleton nos cards |
| Vitória | Classe `card--winner` (borda/anel destacado no card certo) |
| Empate | `outcome === 'tie'` → destacar os dois ou faixa “Empate” |
| Derrota visual | `card--loser` com opacidade ou grayscale leve |
| HP | Usar `stats[0].base_stat`; label `stats[0].stat.name` → `"hp"` |
| Erro 404 | Mostrar `errors.pokeapi`; não exibir `message` técnica do PHP |
| Animação | Após `success`, atrasar 300–500 ms antes de aplicar classes de vencedor |
| Validação | Bloquear batalha se os dois nomes forem iguais (opcional, UX) |

---

## Exemplos reais de resposta

**Vitória (pikachu 35 HP vs charmander 39 HP → charmander vence):**

```json
{
  "success": true,
  "message": "Pokémon charmander venceu.",
  "data": {
    "pokemon1": { "name": "pikachu", "stats": [{ "base_stat": 35, "stat": { "name": "hp" } }] },
    "pokemon2": { "name": "charmander", "stats": [{ "base_stat": 39, "stat": { "name": "hp" } }] }
  }
}
```

**Empate (mesmo HP):**

```json
{
  "success": true,
  "message": "Empate.",
  "data": {
    "message": "Empate entre os Pokémons.",
    "pokemon1": { "name": "pikachu", "stats": [...] },
    "pokemon2": { "name": "raichu", "stats": [...] }
  }
}
```

---

## Checklist front (batalha)

- [ ] Chama `GET /api/battle/{name1}/{name2}` ao confirmar duelo
- [ ] Trata `success: false` e exibe `errors.pokeapi`
- [ ] Usa `data.pokemon1` e `data.pokemon2` para manter os dois cards preenchidos
- [ ] Interpreta `message` ou HP para marcar vencedor / empate
- [ ] Nomes em minúsculas na URL
- [ ] Proxy ou `VITE_API_URL` configurado

---

## Referência no back-end

- Controller: `app/Http/Controllers/PokemonController.php` → método `battle`
- Testes: `tests/Feature/PokemonApiTest.php`

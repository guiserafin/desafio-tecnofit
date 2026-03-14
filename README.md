# Tecnofit Movement Ranking API

API REST em **PHP 8.3 puro** para retornar o ranking de recordes pessoais por movimento, conforme o Case técnico (PRD) `Case Técnico - PHP.pdf`.

## Objetivo da API

Disponibilizar o endpoint:

- `GET /api/movements/{identifier}/ranking`

Onde `identifier` pode ser:

- ID numérico do movimento (ex.: `1`)
- Nome do movimento (ex.: `Deadlift`)
- Busca case-insensitive por nome (ex.: `deadlift`)

Resposta (exemplo):

```json
{
  "movement": "Deadlift",
  "ranking": [
    {
      "position": 1,
      "user": "Jose",
      "personal_record": 190.0,
      "record_date": "2021-01-06T00:00:00+00:00"
    }
  ]
}
```

---

## Pré-requisitos

- Docker Engine 24+
- Docker Compose v2+

---

## Setup e execução

### 1) Clonar e preparar ambiente

```bash
git clone <repo-url>
cd tecnofit
cp .env.example .env
```

### 2) Subir containers

```bash
docker compose up -d
```

### 3) Instalar dependências PHP no container `app`

```bash
docker compose exec -T app composer install
```

### 4) Aplicar schema e seed no banco de desenvolvimento (`tecnofit`)

> O container MySQL cria os bancos `tecnofit` e `tecnofit_test`, mas a carga de tabelas/dados do ambiente de desenvolvimento é feita via migrations SQL.

```bash
docker compose exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/001_create_tables.sql
docker compose exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/002_populate_tables.sql
```

### 5) Verificar API

```bash
curl -i http://localhost:8080/api/movements/1/ranking
```

---

## Exemplos de uso (`curl`)

### Busca por ID

```bash
curl http://localhost:8080/api/movements/1/ranking
```

### Busca por nome

```bash
curl http://localhost:8080/api/movements/Deadlift/ranking
```

### Busca case-insensitive

```bash
curl http://localhost:8080/api/movements/deadlift/ranking
```

### Movimento com espaço no nome

```bash
curl "http://localhost:8080/api/movements/Back%20Squat/ranking"
```

### Movimento inexistente

```bash
curl -i http://localhost:8080/api/movements/999/ranking
```

---

## Execução dos testes

### Todos os testes

```bash
docker compose exec -T app composer test
```

### Apenas unitários

```bash
docker compose exec -T app composer test:unit
```

### Apenas integração

```bash
docker compose exec -T app composer test:integration
```

> Os testes de integração fazem bootstrap automático do banco `tecnofit_test` com as migrations `001_create_tables.sql` e `002_populate_tables.sql`.

---

## Arquitetura e decisões técnicas (fundamentação)

### 1) Arquitetura em camadas (sem framework MVC)

Estrutura aplicada:

- `src/Domain`: entidades, contratos e regras centrais
- `src/Application`: DTOs e casos de uso
- `src/Infrastructure`: acesso a banco, HTTP, configuração e logging
- `src/Presentation`: controller HTTP
- `public/index.php`: bootstrap/entrypoint

**Por que isso foi escolhido:** separa regra de negócio da infraestrutura e evita acoplamento com framework, facilitando testes e evolução.

### 2) `MovementRepositoryInterface` injetado no UseCase

`GetMovementRankingUseCase` depende da interface de repositório via construtor, não de implementação concreta.

**Ganho técnico:** inversion of control, isolamento da regra de negócio e testabilidade por mocks/stubs.

### 3) Estratégia SQL de ranking: `DENSE_RANK()` em vez de `RANK()`

A query usa:

```sql
DENSE_RANK() OVER (ORDER BY pr_max.personal_record DESC)
```

**Motivo:** o PRD exige ranking sem salto após empate (`1, 1, 2`).  
Com `RANK()`, o resultado seria `1, 1, 3`, o que viola o contrato funcional.

### 4) Regra do recorde pessoal e data associada

- O recorde é o **maior valor** (`MAX(pr.value)`), não o mais recente.
- A data retornada é da ocorrência desse recorde máximo.
- Em caso de múltiplas ocorrências com mesmo valor, prevalece a mais recente.

**Motivo:** aderência aos cenários U04/U05 do PRD.

### 5) Busca por identificador (ID vs nome)

- O identificador é normalizado por `MovementIdentifierNormalizer`.
- Se for numérico positivo, busca por ID.
- Caso contrário, busca por nome com `LOWER(name) = LOWER(:name)`.

**Motivo:** manter uma API flexível para cliente, com comportamento determinístico e validação centralizada.

### 6) Roteamento HTTP sem framework

`Router` próprio com:

- path templates (`/api/movements/{identifier}/ranking`)
- extração de parâmetros nomeados
- resposta padronizada para `404` e `405`

**Trade-off:** menos conveniência que um framework, porém máxima aderência ao requisito do desafio (sem MVC framework) e menor superfície de complexidade.

### 7) Tratamento de erro, segurança e logging

- `ErrorMapper` centraliza `Throwable -> status code/payload`.
- Em produção: mensagens genéricas para `500` (`Internal Server Error`).
- Stack trace fica apenas em log interno (`Logger::logServerError` para `5xx`).
- Todas as respostas incluem:
  - `Content-Type: application/json`
  - `X-Content-Type-Options: nosniff`
- Logging de request inclui método, URI, status e duração em ms.

**Motivo:** cumprir requisitos de segurança e observabilidade do PRD sem vazar detalhes técnicos ao cliente.

---

## Checklist de critérios de aceite (PRD)

| # | Critério | Evidência | Status |
|---|---|---|---|
| 1 | `docker compose up -d` inicia sem erros; API responde em `http://localhost:8080` | Ambiente iniciado e `curl -i /api/movements/1/ranking` retornando `HTTP/1.1 200 OK` | ✅ Atendido |
| 2 | `GET /api/movements/1/ranking` retorna HTTP 200 com ranking Deadlift correto | `curl -i http://localhost:8080/api/movements/1/ranking` com ranking `Jose(190.0)`, `Joao(180.0)`, `Paulo(170.0)` | ✅ Atendido |
| 3 | `GET /api/movements/Deadlift/ranking` retorna mesmo resultado da busca por ID | `curl -i http://localhost:8080/api/movements/Deadlift/ranking` com mesmo payload | ✅ Atendido |
| 4 | Back Squat retorna Joao e Jose empatados em 1°, Paulo em 2° | `curl -i http://localhost:8080/api/movements/2/ranking` retornando posições `1,1,2` | ✅ Atendido |
| 5 | Movimento inexistente retorna `404` com `{"error":"Movement not found"}` | `curl -i http://localhost:8080/api/movements/999/ranking` | ✅ Atendido |
| 6 | Nenhum framework MVC no `composer.json` | `composer.json` contém apenas runtime PHP/extensões + dev tools (`phpunit`, `php-cs-fixer`) | ✅ Atendido |
| 7 | Todas as queries usam PDO com prepared statements | `MySQLMovementRepository` usa `$this->connection->prepare(...)` em todas as queries | ✅ Atendido |
| 8 | `declare(strict_types=1)` em todos os arquivos PHP | Contagem: `24` arquivos PHP em `src/`, `public/`, `tests/` e `24` declarações `strict_types` | ✅ Atendido |
| 9 | Tipagem completa em todos os métodos | Assinaturas com tipos escalares/objetos e retornos explícitos; shape docs para arrays estruturados | ✅ Atendido |
| 10 | Mínimo 8 testes unitários passando | `composer test:unit` => `OK (17 tests, 38 assertions)` | ✅ Atendido |
| 11 | Mínimo 7 testes de integração passando | `composer test:integration` => `OK (7 tests, 18 assertions)` | ✅ Atendido |
| 12 | `MovementRepositoryInterface` injetado via construtor no UseCase | `GetMovementRankingUseCase::__construct(private MovementRepositoryInterface $movementRepository)` | ✅ Atendido |
| 13 | README com setup, exemplos e decisões técnicas | Este documento cobre setup, execução, `curl`, testes e fundamentos arquiteturais | ✅ Atendido |
| 14 | Stack trace nunca exposto no response | Teste com indisponibilidade de DB retornou `500` com payload genérico `{"error":"Internal Server Error"}` | ✅ Atendido |

---

## Encerramento do ambiente

```bash
docker compose down
```

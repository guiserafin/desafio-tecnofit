# Tecnofit Movement Ranking API

API REST em **PHP 8.3 puro** para retornar o ranking de recordes pessoais por movimento, conforme o Case técnico (PRD) `Case Técnico - PHP.pdf`.

| Campo | Valor |
|---|---|
| Tecnologia | PHP 8.3+ Puro (sem frameworks) |
| Banco de Dados | MySQL 8.0 |
| Infraestrutura | Docker + Docker Compose |
| Padrão Arquitetural | Clean Architecture + SOLID |
| Testes | PHPUnit 11 (Unit + Integration) |

## Objetivo da API


Construir uma API RESTful em PHP puro (8.3+) para consulta de ranking de movimentos de academia. A solução deve demonstrar domínio de PHP moderno, arquitetura limpa e capacidade de entrega de código pronto para produção, **sem uso de nenhum framework MVC** (Laravel, Symfony, Yi, etc.).

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

## 2. Arquitetura e Estrutura do Projeto

### 2.1 Estrutura de Diretórios

```
movement-ranking/
├── src/
│   ├── Domain/                        # Núcleo — sem dependências externas
│   │   ├── Entity/
│   │   │   ├── Movement.php
│   │   │   ├── User.php
│   │   │   └── PersonalRecord.php
│   │   ├── Repository/
│   │   │   └── MovementRepositoryInterface.php
│   │   └── Exception/
│   │       └── MovementNotFoundException.php
│   ├── Application/                   # Casos de uso e regras de negócio
│   │   ├── UseCase/
│   │   │   └── GetMovementRankingUseCase.php
│   │   └── DTO/
│   │       ├── MovementRankingRequestDTO.php
│   │       └── MovementRankingResponseDTO.php
│   ├── Infrastructure/                # Implementações concretas (DB, HTTP)
│   │   ├── Database/
│   │   │   ├── Connection.php
│   │   │   └── Repository/
│   │   │       └── MySQLMovementRepository.php
│   │   └── Http/
│   │       ├── Router.php
│   │       ├── Request.php
│   │       └── Response.php
│   └── Presentation/                  # Controllers
│       └── Controller/
│           └── MovementRankingController.php
├── public/
│   └── index.php                      # Entry point
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   └── Application/
│   └── Integration/
│       └── Http/
├── database/
│   └── migrations/
│       ├── 001_create_tables.sql
|       └── 002_populate_tables.sql
├── docker/
│   ├── php/Dockerfile
│   └── nginx/default.conf
├── docker-compose.yml
├── phpunit.xml
├── composer.json
└── README.md
```

### 2.2 Clean Architecture — Regra de Dependência

As dependências apontam **sempre para dentro**:

```
Presentation → Infrastructure → Application → Domain
```

- **Domain**: entidades e interfaces. Não depende de nada.
- **Application**: casos de uso. Depende apenas de Domain (via interfaces).
- **Infrastructure**: implementações concretas (MySQL, HTTP). Depende de Application e Domain.
- **Presentation**: controllers. Depende de Application.

### 2.3 Princípios SOLID Aplicados

| Princípio | Aplicação no Projeto |
|---|---|
| **S** — Single Responsibility | `Controller` só roteia; `UseCase` só orquestra; `Repository` só acessa dados |
| **O** — Open/Closed | Novo banco de dados = nova implementação da interface, sem alterar o UseCase |
| **L** — Liskov Substitution | `MySQLMovementRepository` substituível por qualquer impl. de `MovementRepositoryInterface` |
| **I** — Interface Segregation | `MovementRepositoryInterface` expõe apenas o método necessário para o UseCase |
| **D** — Dependency Inversion | `GetMovementRankingUseCase` recebe a interface via construtor (injeção de dependência) |

---

## 3. Requisitos Funcionais

### 3.1 Endpoint

| Campo | Valor |
|---|---|
| Método | `GET` |
| URL | `/api/movements/{identifier}/ranking` |
| Parâmetro | `{identifier}` pode ser o **ID inteiro** ou o **nome** do movimento (case-insensitive) |

### 3.2 Resposta de Sucesso — HTTP 200

```json
{
  "movement": "Deadlift",
  "ranking": [
    {
      "position": 1,
      "user": "Jose",
      "personal_record": 190.0,
      "record_date": "2021-01-06T00:00:00+00:00"
    },
    {
      "position": 2,
      "user": "Joao",
      "personal_record": 180.0,
      "record_date": "2021-01-02T00:00:00+00:00"
    },
    {
      "position": 3,
      "user": "Paulo",
      "personal_record": 170.0,
      "record_date": "2021-01-01T00:00:00+00:00"
    }
  ]
}
```

### 3.3 Regras de Negócio

- **Recorde Pessoal**: o maior valor (`MAX`) registrado pelo usuário naquele movimento.
- **Data do Recorde**: a data correspondente ao registro com o maior valor. Em caso de empate de valor, usar o registro mais recente.
- **Ordenação**: decrescente pelo valor do recorde pessoal.
- **Empate (DENSE_RANK)**: usuários com mesmo recorde compartilham a mesma posição. A posição seguinte **não é pulada** (ex.: 1°, 1°, 2° — e não 1°, 1°, 3°). Usar `DENSE_RANK()`, não `RANK()`.
- **Busca por nome**: case-insensitive. `"deadlift"`, `"DEADLIFT"` e `"Deadlift"` devem retornar o mesmo resultado.

### 3.4 Respostas de Erro

| Código | Cenário | Body |
|---|---|---|
| `400` | Identificador ausente ou inválido | `{"error": "Bad Request"}` |
| `404` | Movimento não encontrado | `{"error": "Movement not found"}` |
| `405` | Método HTTP diferente de GET | `{"error": "Method Not Allowed"}` |
| `500` | Erro interno não tratado | `{"error": "Internal Server Error"}` |

> **Regra de Segurança**: nunca expor stack trace, query SQL ou detalhes de implementação nas respostas de erro em produção. Logar internamente.

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


### 3) Verificar API

```bash
curl -i http://localhost:8080/api/movements/1/ranking
```

### OPCIONAL (Caso não funcione com os passos anteriores):


### 4) Instalar dependências PHP no container `app`

```bash
docker compose exec -T app composer install
```

### 5) Aplicar schema e seed no banco de desenvolvimento (`tecnofit`)

> O container MySQL cria os bancos `tecnofit` e `tecnofit_test`, mas a carga de tabelas/dados do ambiente de desenvolvimento é feita via migrations SQL.

```bash
docker compose exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/001_create_tables.sql
docker compose exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/002_populate_tables.sql
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

## Qualidade de Código

### Padrões Obrigatórios

- `declare(strict_types=1)` em **todos** os arquivos PHP
- Tipagem completa em todos os métodos: parâmetros, retorno e propriedades
- PSR-4 autoloading e PSR-12 formatação
- Usar recursos do PHP 8+: `match`, `readonly`, `enums`, `named arguments`, `union types`, `nullsafe operator`
- DTOs e Value Objects devem usar `readonly` properties para imutabilidade
- Exceptions customizadas e tipadas (ex.: `MovementNotFoundException extends \RuntimeException`)
- Nenhuma variável ou método sem uso no código final

### Tratamento Centralizado de Erros

O entry point (`public/index.php`) deve registrar um handler global que:

1. Captura todas as exceptions não tratadas
2. Mapeia tipos de exception para códigos HTTP corretos
3. Retorna JSON padronizado ao cliente
4. Loga o stack trace completo no servidor (nunca no response)

```php
// Exemplo de mapeamento
$httpCodeMap = [
    MovementNotFoundException::class => 404,
    InvalidArgumentException::class  => 400,
    \Throwable::class                 => 500,
];
```

### Logging

- Logar cada requisição: método, URI, status code, tempo de execução (ms)
- Logar exceptions de nível 500 com stack trace completo
- Nunca logar dados sensíveis (passwords, tokens, dados pessoais)

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

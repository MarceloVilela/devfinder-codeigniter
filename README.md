# DevFinder API — CodeIgniter 4

Replicação de [`devfinder-api`](https://github.com/MarceloVilela/devfinder-api) (Express +
MongoDB) para um monolito **PHP / CodeIgniter 4** (MVC + Query Builder sobre MySQL), com o
mesmo contrato público consumido por `devfinder-next`. Ver [`plan.md`](./plan.md) para o plano
de implementação spec-driven fase a fase.

## Quickstart (Docker Compose)

```bash
cp env .env   # e ajuste app.baseURL / database.default.* se necessário
export UID GID   # se seu usuário não for uid/gid 1000 (padrão em várias distros Linux)
docker compose up -d --build
docker compose exec app php spark migrate --all
curl http://localhost:8081/
```

Serviços: `nginx` (porta `8081` → `80`), `app` (PHP-FPM 8.3), `mysql` (`8.4`, porta `3306`).
Mesmo conjunto de containers usado no deploy real (ver
[`specs/infra-pending.md`](./specs/infra-pending.md)).

## Testes

```bash
docker compose exec app vendor/bin/phpunit
```

## Documentação do projeto

- [`CLAUDE.md`](./CLAUDE.md) — orientação para sessões de IA trabalhando neste repositório.
- [`plan.md`](./plan.md) — plano de implementação spec-driven, fase a fase.
- [`specs/`](./specs/) — contrato OpenAPI, modelo de dados, casos de aceite e registros de
  execução de cada fase.

## Requisitos do servidor (fora do Docker)

PHP 8.2+ com as extensões `intl`, `mbstring`, `mysqli`/`pdo_mysql`, `zip`, `gd` — já
provisionadas na imagem em [`docker/php/Dockerfile`](./docker/php/Dockerfile).

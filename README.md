# DevFinder API — CodeIgniter 4

**DevFinder** cataloga canais e vídeos brasileiros de tecnologia no YouTube. Devs se
registram via GitHub OAuth, podem curtir/descurtir outros devs e seguir/ignorar canais pra
personalizar seu feed de vídeos (trending, por canal, ou só dos canais que seguem). Uma
ingestão agendada mantém o catálogo de vídeos atualizado automaticamente, sem intervenção
manual.

Este repositório é a replicação do [`devfinder-api`](https://github.com/MarceloVilela/devfinder-api)
original (Express + MongoDB) para **PHP / CodeIgniter 4** — MVC + Query Builder
sobre MySQL. Mesmo domínio e mesmo contrato público
consumido por `devfinder-next`, reimplementado num segundo estilo de arquitetura para o
portfólio (o projeto irmão [`../serverless`](../serverless) faz a mesma migração em
Node/TypeScript sobre AWS Serverless — os dois vivem lado a lado como demonstrações diferentes
sobre o mesmo domínio). Ver [`plan.md`](./plan.md) para o plano de implementação spec-driven
fase a fase.

## Stack

- **Compute**: PHP-FPM 8.3 + Nginx, CodeIgniter 4 (MVC + Query Builder)
- **Dados**: MySQL 8.4 (Docker Compose local) / [TiDB Cloud Serverless](https://www.pingcap.com/tidb-cloud/)
  (deploy real, MySQL-compatível, camada sempre-gratuita)
- **Agendamento**: GitHub Actions (`schedule`), disparando `POST /video/refresh`
- **Auth**: GitHub OAuth + JWT (`firebase/php-jwt`), via `Filters` do CodeIgniter
  (`RequiredAuthFilter`/`OptionalAuthFilter`)
- **Deploy**: [Render](https://render.com) (plano free, imagem Docker única — PHP-FPM + Nginx
  + Supervisor, ver [`docker/render/Dockerfile`](./docker/render/Dockerfile))
- **Testes**: PHPUnit/CIUnit (`tests/feature/`), rodando no CI a cada PR

## Rodando localmente

```bash
cp .env.example .env   # já vem com os valores padrão do Docker Compose local preenchidos —
                        # só falta as credenciais do GitHub OAuth App (auth.githubClientId/
                        # githubClientSecret), ver specs/fase-4-endpoints-auth.md
export UID GID   # se seu usuário não for uid/gid 1000 (padrão em várias distros Linux)
docker compose up -d --build
docker compose exec app php spark migrate --all
curl http://localhost:8081/
```

Serviços: `nginx` (porta `8081` → `80`), `app` (PHP-FPM 8.3), `mysql` (`8.4`, porta `3306`).

```bash
docker compose exec app vendor/bin/phpunit
```

## Testando os `.http`

Todos os casos de aceite (um por rota, incluindo cenários de erro) estão em
[`specs/acceptance/`](./specs/acceptance/) como arquivos `.http`, prontos pra rodar contra
`local` **ou** o deploy real — ver [`specs/acceptance/README.md`](./specs/acceptance/README.md)
pra como disparar as requests (WebStorm/IntelliJ, VS Code + extensão REST Client, `curl` puro,
ou `npx httpyac send *.http --env <ambiente> --all`) e como gerar os tokens de autenticação em
cada ambiente.

## Endereço Render

- API real: `https://devfinder-codeigniter.onrender.com/v1`
- Banco: TiDB Cloud Serverless (MySQL-compatível)
- Ingestão **agendada** (`video:refresh`) via GitHub Actions, a cada 12h — também disparável
  manualmente (`POST /video/refresh` ou `workflow_dispatch`)
- Autenticação real via GitHub OAuth, com `client_secret`/`jwtSecret` guardados como variável
  de ambiente no Render (não versionados, `sync: false` no `render.yaml`)
- `devfinder-next` (frontend real) **não aponta** para esta API — decisão consciente de
  escopo, ver `plan.md`, Fase 7. O deploy real é demonstração funcional de portfólio, não
  substitui a produção do `devfinder-api` original.

## Documentação

- [`plan.md`](./plan.md) — plano de implementação spec-driven, fase a fase (metodologia
  **spec-driven**: cada fase parte de uma especificação escrita antes do código, um PR por
  fase).
- [`specs/README.md`](./specs/README.md) — status atual de cada fase, com o que foi
  verificado localmente e o que foi verificado contra o deploy real.
- [`CLAUDE.md`](./CLAUDE.md) — orientações usadas com o Claude Code durante o desenvolvimento
  deste projeto (transparência sobre o processo).

# Auditoria — Postgres no portfólio GitHub e status do ElephantSQL

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1 e dos demais `fase-7-deploy-*.md`). Item 7.5
> de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este documento registra uma
> pergunta lateral levantada durante a pesquisa de hospedagem — não avalia um novo candidato de
> host pro `php-codei` propriamente dito (ver "Por que isso não resolve o item 7.5" abaixo).

## Origem

Pergunta do usuário: se há algum projeto com banco Postgres no portfólio
(`github.com/MarceloVilela`), seguida de uma pista própria — "Postgres - ElephantSql -
railway" — apontando pra onde procurar.

## Verificação (2026-08-24)

`gh api users/MarceloVilela/repos` listou os repositórios públicos; a busca de código do
GitHub (`search/code`) devolveu `total_count: 0`/`incomplete_results: true` mesmo para termos
sabidamente presentes (ex.: `mongoose` no `devfinder-api`, que usa MongoDB — confirmado lendo o
`package.json` direto), então a indexação de busca não é confiável pra esse tipo de auditoria;
a verificação real foi feita lendo `package.json`/`.env.example` de cada repo candidato via
`gh api repos/.../contents/...`, não via busca.

### Projetos com Postgres confirmado

| Repositório | Evidência |
|---|---|
| **`gym-point-api`** | `.sequelizerc` na raiz; `package.json`: `pg@^7.0.0`, `sequelize@^5.21.0`, `sequelize-cli@^5.5.1` |
| **`gympoint`** (pasta `backend/`) | mesmas deps do `gym-point-api`; `.env.example`: `DB_USER=postgres`, `DB_NAME=postgresgympoint` |
| **`fastfeet`** (pasta `backend/`) | mesmas deps; `.env.example`: `DB_DIALECT=postgres`, `DB_NAME=postgresfastfeet` |

Todos do período do bootcamp GoStack/Rocketseat (commits de 2019-10 a 2020-06, pelas datas de
`updated_at` da API), padrão comum da época: Sequelize + driver `pg` + Postgres local via
Docker (`DB_HOST=localhost`, `DB_PASS=docker` nos `.env.example`). `ecoleta` (mesma família de
projetos, também com pasta `backend/`) não teve Postgres confirmado nesta auditoria —
`package.json`/`.env.example` de `backend/` não retornaram as mesmas evidências dos outros
três; não aprofundado, não é necessário pra responder a pergunta original (já há 3 exemplos
confirmados).

### ElephantSQL — status atual

Verificado via WebSearch (não memória — mesma disciplina de todo `infra-pending.md`):
**ElephantSQL foi descontinuado.** Vendas novas paradas em 2024-05-01, shutdown completo em
**2025-01-27** — o time se juntou ao CloudAMQP/LavinMQ (message queuing), não existe mais
Postgres gerenciado da ElephantSQL pra nenhuma conta, nova ou antiga. Se algum dos 3 projetos
acima usava ElephantSQL como banco em produção (padrão comum ~2019-2020 combinado com deploy no
Heroku), essa integração já não existe mais — mesmo padrão de "serviço grátis que saiu do ar"
já registrado nesta sessão para `db4free.net` (`fase-7-deploy-render.md`).

**Railway**: já avaliado especificamente no contexto do `php-codei` em
[`fase-7-deploy-railway.md`](./fase-7-deploy-railway.md) — suporta Postgres e MySQL nativamente,
mas o "grátis" é trial de US$5/30 dias seguido de US$1/mês sem acúmulo, não sempre-gratuito.
Não repetido aqui.

## Por que isso não resolve o item 7.5

O `php-codei` foi desenhado pra **MySQL/MariaDB** desde a Fase 1
(`specs/fase-1-data-model.md`), com `CHECK` constraints e `ON UPDATE CASCADE` já validados
contra MySQL real (`fase-2-scaffold-infra.md`). Os 3 projetos com Postgres encontrados aqui são
de outro domínio (GoStack/Rocketseat), não têm relação direta com o `devfinder`. Essa auditoria
confirma que já existe prova de experiência real com Postgres no portfólio (caso seja relevante
pra outro contexto), mas **não muda a decisão de hospedagem do `php-codei`**, que continua
precisando de MySQL sempre-gratuito — nenhuma opção nova levantada aqui resolve isso.

## Conclusão

- Portfólio **tem** Postgres real: `gym-point-api`, `gympoint`, `fastfeet` (Sequelize + `pg`).
- **ElephantSQL não existe mais** (encerrado 2025-01-27) — não é candidato a nada, nem pro
  `php-codei` (que é MySQL, não Postgres) nem pra revalidar algum desses 3 projetos antigos se
  algum dependesse dele em produção.
- Nenhuma opção nova de hospedagem sempre-gratuita pro item 7.5 surgiu desta auditoria. O
  caminho documentado em `infra-pending.md` (Oracle Cloud Always Free / GCP `e2-micro` Always
  Free) segue sendo o único sem obstáculo confirmado até agora.

Fontes: [End of Life Announcement – ElephantSQL](https://www.elephantsql.com/blog/end-of-life-announcement.html),
[End of Life – ElephantSQL](https://www.elephantsql.com/eol.html),
[Best ElephantSQL Alternatives in 2026 — PandaStack](https://pandastack.io/blog/best-elephantsql-alternatives-2026).

# Infra pendente — hospedagem real, Context7 e skill de CodeIgniter

> Cadernos de decisão (não fazem parte do plano de fases em si) sobre o que ainda depende de
> pesquisa ou execução humana antes/durante a Fase 0–2. Mesmo papel de
> `../serverless/specs/aws-pending.md` para este projeto.

## 1. Hospedagem real sempre-gratuita (fora do Docker local) — ainda em aberto

Padrão aplicado: mesma régua do projeto irmão (`../serverless/CLAUDE.md`, "Regra de custo AWS")
— **genuinamente sempre-gratuito**, não trial de 12 meses, não crédito promocional que expira,
não "free tier" que vira cobrança se esquecido.

**Decisão**: **não tomada ainda** — deliberadamente adiada para a execução da Fase 2 (fase de
deploy), não travada durante o planejamento. Uma pesquisa anterior (2026-08-23) tinha chegado
numa recomendação específica, mas ela não se aplica a este projeto (confundida com contexto de
outro projeto) e foi removida daqui em 2026-08-24 — não usar como referência.

**Ainda por fazer**: pesquisar de novo as opções de hospedagem PHP+MySQL genuinamente
sempre-gratuitas (não trial) quando a Fase 2 começar de fato, e registrar a decisão aqui com a
mesma régua acima. Documentar o provisionamento manual num runbook, mesmo padrão de runbook
humano já usado no projeto irmão `../serverless`.

## 2. Context7 MCP — cobertura CodeIgniter 4, e o que ele NÃO cobre

Context7 (`npx -y @upstash/context7-mcp`) é um servidor MCP que resolve nome de
biblioteca/framework → documentação atual, injetada no contexto do agente. Índice de milhares
de bibliotecas (números variam por fonte, na casa dos milhares).

- **Cobertura de CodeIgniter 4**: **confirmada em 2026-08-24** (Fase 0), via
  `https://context7.com/api/v1/search?query=codeigniter` (a busca por fetch simples na página
  de registro, tentada em 2026-08-23, não retornava nada — renderizada em JS; o endpoint de
  API público, sim). Achado: 3 entradas de CodeIgniter (UserGuide 3, CodeIgniter 4,
  "Version-4"), mais as extensões oficiais **Shield** (auth), **Tasks** (scheduler —
  candidato pro Command da Fase 6 em vez de cron puro), **Settings**, **Queue**, libs da
  comunidade, e uma entrada extra não prevista: **"Docker Environment"** ("Complete
  development setup with PHP 8.3, Nginx, MySQL, Redis") — candidata a consultar na Fase 2.
  O servidor MCP foi **configurado em 2026-08-24** (`claude mcp add --scope project context7
  -- npx -y @upstash/context7-mcp`, escrito em `.mcp.json`) e **aprovado em 2026-08-24** —
  resolvido numa sessão `claude` aberta com `php-codei` como raiz (a sessão que configurou
  tinha `../serverless` como raiz e não conseguia aprovar). Ferramenta real testada:
  `resolve-library-id("CodeIgniter 4")` retornou 5 resultados (`codeigniter4/codeigniter4`,
  `codeigniter4/userguide`, `websites/codeigniter_user_guide`, `codeigniter4/shield`,
  `codeigniter4/tasks`), confirmando a cobertura já verificada via endpoint público. Ver
  `fase-0-especificacao.md` para o registro completo.
- **Cobertura de Docker/infra** (pergunta feita em 2026-08-23, ao montar o Docker Compose da
  Fase 2): Context7 é voltado a **bibliotecas/frameworks de código** (React, Prisma, etc.),
  resolvidas por nome de pacote — não é a ferramenta certa para sintaxe de `Dockerfile`/
  `docker-compose.yml`, que não é uma "biblioteca" no sentido que o Context7 indexa. Não usar
  o Context7 como fonte para a Fase 2 (scaffold Docker); usar a documentação oficial das
  imagens PHP-FPM/Nginx/MySQL diretamente (conhecimento já disponível, sem necessidade de MCP
  para isso). O Context7 continua relevante **dentro** do container, para a sintaxe do
  CodeIgniter em si (Model, rotas, migrations, filtros).

## 3. Skill de agente para CodeIgniter

Existe uma skill publicada e instalável, encontrada em 2026-08-23 e **instalada em
2026-08-24** (Fase 0):

```
npx skills add yasserstudio/codeigniter-skills
```

Cobre CI3 (3.1.x) e CI4 (4.7.x) com auto-detecção: migrations, seeders, Shield (auth),
testes, Spark CLI, checklists de segurança/deploy. ~1600 linhas, MIT, projeto pequeno (poucas
estrelas/commits) mas real. Avaliação de risco do instalador na execução real: **Safe / 0
alerts / Low Risk** (Gen/Socket/Snyk). Instalada em `.agents/skills/codeigniter/`, symlinked
para `.claude/skills/codeigniter/` — disponível para sessões futuras do Claude Code neste
diretório sem precisar reinstalar.

Fonte: [github.com/yasserstudio/codeigniter-skills](https://github.com/yasserstudio/codeigniter-skills)

## 4. DeepWiki MCP — cobertura por repositório da stack, e fonte de verdade por fase

DeepWiki (`mcp.deepwiki.com`, Cognition/Devin) é um servidor MCP diferente do Context7: em vez
de snippet de API por biblioteca, ele indexa repositórios reais do GitHub e gera arquitetura
navegável (visão geral, módulos, dependências) mais três ferramentas —`ask_question`,
`read_wiki_contents`, `read_wiki_structure`. Verificado em 2026-08-23, repo a repo, contra
toda a stack deste plano (não só o framework):

| Componente | Repositório | Indexado? | O que dá pra fundamentar |
|---|---|---|---|
| Framework (Fases 1, 3, 4, 5, 6) | `codeigniter4/CodeIgniter4` | ✅ sim | routing, controllers, filters, models, query builder, conexões/transações de DB, service locator, config, **custom commands** |
| Scaffold (Fase 2) | `codeigniter4/appstarter` | ❌ não (placeholder "Loading...") | usar Composer/doc oficial direto |
| Auth lib (não usada — decisão já tomada) | `codeigniter4/shield` | ❌ não | irrelevante, já decidimos não usar Shield |
| JWT (Fase 4) | `firebase/php-jwt` | ✅ sim | algoritmos suportados (HS/RS/ES/EdDSA), hierarquia de exceptions (`ExpiredException` etc.), JWK, rotação de chave via `CachedKeySet` |
| Imagem PHP (Fase 2, Docker) | `docker-library/php` | ✅ sim | variante Debian vs Alpine, SAPI (FPM vs Apache vs CLI), `docker-php-ext-install`/`-configure` para extensões (ex. `pdo_mysql`) |
| Imagem MySQL (Fase 2, Docker) | `docker-library/mysql` | ✅ sim (indexado 2025-06-24) | canais de versão (8.0 vs **8.4 LTS**), `docker-entrypoint.sh` (init/env); **não cobre volumes/persistência em detalhe** |
| Imagem Nginx (Fase 2, Docker) | `nginxinc/docker-nginx` | ❌ não (placeholder) | usar doc oficial nginx.org direto |
| Testes (CIUnit, Fase 7) | dentro do próprio `CodeIgniter4` | ⚠️ raso/ausente | usar doc oficial de testing do CI4 |

**Achado que refina a nota do item 2 acima**: a ressalva de que "ferramenta de doc de
biblioteca não serve para Docker" vale para o **Context7** especificamente, não para o
DeepWiki como categoria — as imagens oficiais `docker-library/php` e `docker-library/mysql`
têm sistema real de templating/versionamento por trás (não são só um Dockerfile estático), e
por isso o DeepWiki indexa e cobre bem. A exceção dentro da própria família Docker é o
`nginxinc/docker-nginx`, não indexado.

**Fonte de verdade por fase** (consultar `ask_question` no repo relevante antes de escrever a
spec da fase, mesmo princípio de "evidência lida no código, não suposição" já usado em
`../../serverless/specs/fase-1-data-model.md`):

- **Fase 1** (data model) e **Fase 5** (relacionamentos): `codeigniter4/CodeIgniter4` — Query
  Builder/Models.
- **Fase 2** (scaffold + Docker): `docker-library/php` (Debian vs Alpine, extensões) e
  `docker-library/mysql` (8.0 vs 8.4 LTS); `appstarter` e `nginxinc/docker-nginx` ficam de
  fora — doc oficial direto para os dois.
- **Fase 3** (leitura pública): `codeigniter4/CodeIgniter4` — routing/controllers.
- **Fase 4** (auth): dois repositórios, não um — `codeigniter4/CodeIgniter4` (filters) **e**
  `firebase/php-jwt` (algoritmo/expiração/exceptions).
- **Fase 6** (ingestão em lote): `codeigniter4/CodeIgniter4` — custom commands.
- **Fase 7** (testes): sem apoio do DeepWiki — doc oficial CIUnit.

# CLAUDE.md

Este arquivo orienta o Claude Code ao trabalhar neste diretório.

## O que é isso

Plano de replicação de `../devfinder-api` (Express + MongoDB, hoje em Heroku) para uma stack
**PHP / CodeIgniter 4** — um monolito tradicional (MVC + Query Builder sobre MySQL/MariaDB),
não uma arquitetura serverless. Mesmo domínio e mesmo contrato público de `../devfinder-api`
(devs, channels, videos, likes/follows, GitHub OAuth), reimplementado num segundo estilo de
arquitetura para o portfólio — em vez de repetir Lambda/API Gateway com outra linguagem.

Este projeto é **independente** de `../serverless` (a migração AWS Serverless do mesmo
`devfinder-api`, feita em Node/TypeScript). Os dois vivem lado a lado como demonstrações de
arquiteturas diferentes sobre o mesmo domínio. **Não altere nada em `../serverless`.**

**Repositório remoto**: `git@github.com:MarceloVilela/devfinder-codeigniter.git` (privado, já
criado pelo usuário em 2026-08-24). Ainda **sem git local inicializado** neste diretório — só
os documentos de plano existem até agora. `git init` + primeiro commit + push ficam para
quando a execução da Fase 0 começar de fato (ver "Regra do projeto" abaixo), não antes.

**Leia nesta ordem antes de fazer qualquer alteração:**

1. [`portfolio.md`](./portfolio.md) — por que CodeIgniter 4 (e por que monolito tradicional,
   não serverless), o domínio replicado, e a relação com `../devfinder-api`/`../serverless`.
2. [`plan.md`](./plan.md) — o plano de implementação spec-driven, fase a fase, com critérios de
   aceite e o fluxo de PR por fase. Contém a seção **"Layout do workspace"** explicando que
   `devfinder-api`, `devfinder-next` e `serverless` são diretórios irmãos deste
   (`/home/marcelo/Desktop/coding/published/`), não filhos.
3. [`specs/README.md`](./specs/README.md) — status dos artefatos de especificação (ainda todos
   pendentes — nenhuma fase foi executada).

## Regra do projeto

Metodologia **spec-driven design**, igual ao projeto irmão `../serverless`: nenhuma fase de
`plan.md` avança sem a spec da fase anterior escrita e revisada primeiro. Diferença deliberada
em relação a `../serverless` (que implementou direto em `main`): aqui, **cada fase é um Pull
Request separado** — branch a partir de `main`, PR só é aberto quando o critério de aceite da
fase bate, e a fase seguinte só começa depois do PR mergeado. Ver `plan.md`, seção
"Metodologia", para o fluxo completo.

Não implemente código de scaffold ou controllers antes de `specs/fase-0-openapi.yaml` e
`specs/fase-1-data-model.md` existirem e estarem aprovados — esse é o próximo passo imediato
do plano.

**Pull Request só quando o usuário pedir explicitamente.** Mesmo que o critério de aceite da
fase já bata localmente (specs revisadas, testes passando, `docker compose up` funcionando
etc.), não crie branch/commit/push/PR por conta própria — implementar até o critério bater é
trabalho autônomo normal, mas abrir o PR é uma ação visível pro GitHub que fica pra decisão
humana, sempre. Avise que a fase está pronta e pergunte, não presuma.

**PHPUnit/CIUnit é escopo da Fase 7, não das fases de endpoint (3, 5, 6).** Casos de aceite
dessas fases são verificados rodando os `.http` de verdade (`npx httpyac send *.http --env
local --all`, não `curl` solto nem alegação em prosa) e guardando a saída real como evidência
versionada (ver `specs/acceptance/execucao-fase-3.log` como padrão a repetir). Suíte
automatizada rodando no CI a cada PR só entra na Fase 7 (`plan.md`, "Suíte de testes CIUnit
cobrindo os casos de aceite críticos").

## Ferramentas de contexto CodeIgniter

Para reduzir alucinação em convenções específicas do framework durante a implementação:

- **Context7 MCP** (`npx -y @upstash/context7-mcp`) — servidor MCP de documentação viva.
  Cobertura de CodeIgniter 4 **confirmada em 2026-08-24** via `context7.com/api/v1/search`
  (3 entradas de CI4 + Shield/Tasks/Settings/Queue oficiais) — ver
  `specs/fase-0-especificacao.md`. **Configurado em 2026-08-24** via `claude mcp add --scope
  project context7 -- npx -y @upstash/context7-mcp`, escrito em `.mcp.json` (raiz do projeto,
  versionado — é o objetivo do scope `project`: qualquer sessão futura aberta aqui já enxerga
  isso). **Aprovado em 2026-08-24** — resolvido numa sessão `claude` aberta com `php-codei`
  como raiz (a sessão que configurou isso tinha `../serverless` como raiz, não `php-codei`, e
  por isso não pôde aprovar nem usar a ferramenta imediatamente). `resolve-library-id` testado
  e funcionando — ver `specs/infra-pending.md`, item 2. **Custo verificado 2026-08-24**: plano
  Free = US$ 0, sem cartão, sem cobrança automática — 1.000 chamadas/mês + 60/hora sem API key
  (cortado de ~6.000/mês em jan/2026, sem aviso público — mesmo padrão do corte da Oracle
  Cloud Always Free). Detalhe completo em `specs/infra-pending.md`, item 2, último bullet.
- **`npx skills add yasserstudio/codeigniter-skills`** — skill de agente já publicada e
  instalável, cobrindo CI3 (3.1.x) e CI4 (4.7.x): migrations, seeders, Shield (auth), testes,
  Spark CLI, checklists de segurança/deploy. Instalar esta em vez de escrever uma skill
  customizada do zero — ver `specs/infra-pending.md` para a fonte da pesquisa.

## Regra de custo — hospedagem sempre-gratuita

Mesma disciplina de custo do projeto irmão `../serverless` (ver `../serverless/CLAUDE.md`,
"Regra de custo AWS"), adaptada para fora da AWS: qualquer deploy real deste projeto (não
Docker local) deve rodar em camada **genuinamente sempre-gratuita** — não trial de 12 meses,
não crédito promocional, não "free tier" que expira. **Alvo ainda não escolhido** — decisão
adiada deliberadamente para a fase de deploy (Fase 2), não travada aqui na fase de
planejamento. Ver [`specs/infra-pending.md`](./specs/infra-pending.md) para o estado atual
dessa decisão.

## Repositórios irmãos relevantes

- `../devfinder-api` — fonte da replicação (ler `CLAUDE.md` desse repo para o domínio
  completo: modelos `Dev`/`Channel`/`Video`, rotas, autenticação).
- `../devfinder-next` — frontend que consome a API; contrato público não pode quebrar.
- `../serverless` — migração do mesmo `devfinder-api` para AWS Serverless (Node/TypeScript).
  Projeto irmão, mesma metodologia spec-driven, arquitetura diferente. **Não alterar.**

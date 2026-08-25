# Avaliação — Railway.app como candidato ao item 7.5 (deploy real)

> Caderno de decisão (não é spec de fase executada — mesmo papel de
> [`infra-pending.md`](./infra-pending.md) item 1 e de
> [`fase-7-deploy-render.md`](./fase-7-deploy-render.md), do qual este documento é irmão).
> Item 7.5 de [`../plan.md`](../plan.md) segue **sem provedor escolhido**; este documento
> registra a avaliação de mais um candidato (Railway), não uma decisão.

## Origem

Opção 4 da nota local não versionada `specs/zdeploy__.md` (`**/*__.*`, gitignored — pesquisa
de terceiros trazida pelo usuário).

## Verificação (2026-08-24, via WebSearch — não memória)

| Aspecto | Achado |
|---|---|
| **Docker** | Suporta deploy de app Dockerizado direto do GitHub, sem gerenciar servidor — melhor encaixe técnico de todos os candidatos avaliados até agora (`docker-compose.yml` da Fase 2 mapeia bem). |
| **MySQL** | Suporta MySQL gerenciado nativamente (também Postgres, MongoDB, Redis) — não teria o problema de "sem MySQL grátis" encontrado no Render. |
| **Plano inicial** | Trial: US$5 de crédito único, válido por **30 dias** — some por tempo ou por uso, o que vier primeiro. |
| **Depois do trial** | Reverte para o "Free plan": **US$1 de crédito/mês**, que **não acumula** de um mês pro outro. |
| **Cartão de crédito** | Não exigido para começar o trial. |

## Por que importa para este projeto especificamente

Tecnicamente, Railway é o candidato mais parecido com "sobe o que já existe sem reescrever
nada" — Docker nativo (compose mapeia direto) e MySQL gerenciado de verdade, ao contrário do
Render (sem MySQL) e do InfinityFree/000webhost (sem Docker, sem CLI). O problema não é
técnico, é a **Regra de custo — hospedagem sempre-gratuita** do `../CLAUDE.md`: "não trial de
12 meses, não crédito promocional, não 'free tier' que expira".

US$5/30 dias é literalmente um crédito promocional que expira — desqualificado de cara. O que
sobra depois (US$1/mês, sem acúmulo) não é uma cota sempre-gratuita utilizável: um app sempre
ativo (PHP-FPM + Nginx + MySQL, os 3 serviços do compose rodando continuamente) consome
recursos 24/7, e US$1/mês de crédito não cobre isso por muitos dias — na prática, vira uma
conta que exige cartão e cobrança assim que o crédito mensal acaba, exatamente o padrão que a
regra do projeto pede pra evitar.

## Conclusão

**Descartado por regra de custo, não por limitação técnica.** Railway seria o deploy mais
simples de todos os avaliados até agora (Docker + MySQL nativos, sem reestruturar nada) — mas
o "grátis" dele é uma trial de $5/30 dias seguida de $1/mês sem acúmulo, o oposto de
"sempre-gratuito" como o `CLAUDE.md` define a régua. Só voltaria a ser candidato se o usuário
decidisse relaxar essa regra explicitamente (aceitar pagar pela hospedagem real) — decisão que
não foi tomada e não é assumida aqui.

Fontes: [Railway Free Tier in 2026: What You Get and When It Runs Out – Kuberns/Medium](https://medium.com/@kuberns/railway-free-tier-in-2026-what-you-get-and-when-it-runs-out-2101fdca0998),
[Free Trial – Railway Docs](https://docs.railway.com/pricing/free-trial),
[Railway Pricing 2026: Free Plan, Postgres & Alternatives – srvrlss.io](https://www.srvrlss.io/provider/railway/).

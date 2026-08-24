# Fase 5 — Endpoints autenticados de escrita e relacionamento

> Fase 5 de [`../plan.md`](../plan.md), seção "6. Fase 5". Depende da Fase 4 (PR #5,
> mergeado) — reaproveita `RequiredAuthFilter` e `DevModel::findOrCreate`.

## Escopo

`POST /devs`, `POST /channels`, `POST /video`, e os 4 pares
`POST`/`DELETE /likes/devs/{u}`, `/dislikes/devs/{u}`, `/likes/channels/{u}`,
`/dislikes/channels/{u}` + `GET /likes/devs`, `GET /dislikes/devs` — 13 endpoints, todos
`RequiredAuthFilter`. Confirmado lendo `devfinder-api/src/routes/index.ts` (não assumido):
`routes.use(authMiddleware)` na linha 127 cobre todos eles — o comentário `#swagger.security`
em cada rota é só documentação OpenAPI, quem de fato aplica o middleware é o `.use()` global.

Fora de escopo (`plan.md`, Fase 6): `POST /video/refresh`, `POST /channels/refresh` —
ingestão em lote, Command `spark` + cron.

## Achado antes de implementar — 2 bugs reais no `devfinder-api` original

Lendo `ChannelController.store`/`VideoController.store` com atenção (não só a forma, o
conteúdo):

1. **`Dev.create({name, bio, avatar})` sem `user`/`username`** — o schema Mongoose marca
   `user` como `required`; essa chamada quebraria a validação na prática. Não replicável:
   `devs.username` é `NOT NULL` no nosso schema. Corrigido usando `DevModel::findOrCreate`
   com `userGithub` como username — ganha idempotência de graça (o original cria um Dev novo
   a cada `POST /channels` pro mesmo `userGithub`, sem checar se já existe).
2. **`AddChannel` no OpenAPI não documentava `userGithub`/`avatar`** — herdado do swagger
   original incompleto, mas o controller sempre aceitou os dois campos. Corrigido no próprio
   `fase-0-openapi.yaml` (política do arquivo: "qualquer discrepância encontrada durante a
   implementação deve ser corrigida aqui primeiro").

## 3 bugs reais do framework/implementação, encontrados rodando de verdade

Nenhum dos três apareceria só lendo código — só rodando as requests contra MySQL real.
Detalhe completo + reprodução em
[`acceptance/execucao-fase-5.log`](./acceptance/execucao-fase-5.log):

1. **`*/g` dentro de um PHPDoc fechava o comentário cedo** — sintaxe real quebrada
   (`VideoModel::stripTrackingParam`), confirmado com `php -l`.
2. **`find(false)` do CI4 devolve todos os registros, não nenhum** — `DevModel::findOrCreate`
   com um username sem avatar real no GitHub: `insert()` falha validação, `false`;
   `$this->find(false)` ≠ `$this->find(null)` na cabeça, mas na prática os dois "sem filtro
   real" se comportam igual. Corrigido com guarda explícita (`RuntimeException` em vez de
   devolver a tabela inteira).
3. **`is_unique[...,id,{id}]` no `update()`** — o placeholder só resolve a partir do array de
   dados passado pro `update()`, não do argumento `$id`. Sem `'id' => $id` explícito nos
   dados, a checagem de unicidade sempre rejeita a própria linha como duplicata (bug
   silencioso: `update()` retorna `false`, categoria nunca muda, sem erro visível pro
   chamador do meu próprio código de teste até eu checar `$model->errors()` explicitamente).
   E incluir `'id'` nos dados sem uma regra de validação própria pra esse campo lança
   `LogicException` — guarda de segurança do próprio CI4, documentada mas fácil de não ver.
   Corrigido nos 3 Models que usam esse padrão de validação (`Channel`, `Dev`, `Video`).

## Decisões de design

- **Idempotência via chave composta**: `DevReactionModel`/`ChannelReactionModel::add()`
  checam existência antes do `INSERT` (like duas vezes não duplica nem estoura erro de
  unicidade) — exatamente o critério de aceite pedido em `plan.md`, Fase 5.
- **Auto-reação vira no-op silencioso**: a `CHECK (dev_id <> target_dev_id)` da Fase 1
  proíbe estruturalmente um Dev curtir a si mesmo — `add()` verifica antes de tentar o
  `INSERT`, nunca bate na constraint. Original não tinha proteção nenhuma pra esse caso
  (nunca testado, sem fluxo real que o gerasse).
- **`POST /devs` sempre 201** — paridade exata com o original: `findOrCreateDev` não
  distingue "achou" de "criou" na resposta.
- **`POST /channels`**: 201 na criação, 200 no update (sem status explícito no original,
  Express usa 200 por padrão) — paridade exata, não a suposição de "sempre 201" que um dos
  stubs herdados da Fase 0 tinha registrado incorretamente (corrigido junto).
- **`POST /video`**: 400 (canal não existe, payload de erro completo ecoado), 201 (criado,
  thumbnail com fallback pro padrão do YouTube), 409 (já existe, vídeo existente ecoado) —
  paridade exata com `VideoController.store`.
- **Fixture de teste**: adicionado um 3º canal só-fixture ("Canal Zeta", sem vídeos nem
  reações de baseline) em `AcceptanceChannelSeeder` — usar "Canal Beta" pros testes de
  follow/ignore corromperia o `ignore` de baseline do `dev01` que `feed-trending.http`
  (`?user=dev01`, Fase 3) já depende.

## Validação — execução real, não só leitura de código

`vendor/bin/phpunit`: `OK (5 tests, 7 assertions)`, exit 0 conferido explicitamente.

5 arquivos `.http` novos/corrigidos (`devs-store.http`, `channels-store.http`,
`video-store.http`, `likes-dislikes-devs.http`, `likes-dislikes-channels.http`) rodados de
verdade via `httpyac` contra banco limpo (`migrate --all` + `db:seed AcceptanceSeeder`) —
**25/25 requests com o status esperado**. Log completo em
[`acceptance/execucao-fase-5.log`](./acceptance/execucao-fase-5.log).

## Critério de aceite da Fase 5 (de `../plan.md`)

- [x] Casos de aceite cobrindo criar → listar → remover, pra cada par (like/dislike,
  follow/ignore) — confirmado (`likes-dislikes-devs.http`, `likes-dislikes-channels.http`).
- [x] `POST /devs`, `POST /channels`, `POST /video` implementados e validados.
- [x] Idempotência verificada — like duas vezes não duplica, `POST /devs` repetido não erra.
- [ ] Revisão humana.

## Próximo passo

Fase 6 (ingestão em lote) — Command `spark` + cron, reaproveitando a lógica de dedup de
`POST /video` (`VideoModel::findByExactUrl`) pro `video/refresh` em lote.

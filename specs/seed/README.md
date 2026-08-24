# Seed — por que os `.json` não estão aqui

`omni8.devs.json`, `omni8.channels.json`, `omni8.videos.json` são um dump real (Mongo
Extended JSON) de nomes, bios e canais reais de terceiros — gitignored pelo mesmo motivo do
projeto irmão `../../../serverless` (risco de marca registrada e LGPD em publicar isso num
repositório público, ver `../../../serverless/specs/seed/README.md`). `php spark seed:local`
espera esses 3 arquivos aqui dentro; sem eles, o comando falha cedo com uma mensagem
explicando isso — não com um erro genérico de arquivo não encontrado.

## Como rodar o seed sem o dump real

Formato esperado: Mongo Extended JSON (`{"$oid": "..."}`, `{"$date": "..."}`), mesma
convenção de um `mongoexport` — mesmo formato que `../../../serverless/specs/seed/README.md`
já documenta, reaproveitável aqui sem alteração. Referências cruzadas usam esses `$oid`:
`Video.channel_id` → `_id` de um channel; `Dev.follow`/`Dev.ignore` → `_id` de channels;
`Dev.likes`/`Dev.deslikes` → `_id` de outros devs. Use o exemplo sintético mínimo já
documentado lá para rodar `php spark seed:local` de ponta a ponta sem nenhum dado real.

## Diferença em relação ao seed do projeto irmão

`../../../serverless/api/scripts/seed-local.ts` grava num DynamoDB single-table (`pk`/`sk`,
slug como chave). Aqui (`app/Commands/SeedLocal.php`) o alvo é MySQL relacional
(`specs/fase-1-data-model.md`): sem slug — `channels.id`/`devs.id` são auto-increment nativos
— e a dedup de `channels` é só por `name` (o dump real tem uma única colisão, "CaquiCoders" 4x,
que também é a única colisão de `link`; ver `specs/fase-1-data-model.md`, seção "Evidência do
dump real").

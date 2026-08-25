#!/bin/sh
set -e

# Render injeta $PORT em runtime (varia, não é fixa) — o build local do docker-compose usa
# porta fixa (8081), então esse template só existe pro alvo Render.
: "${PORT:=10000}"
export PORT

# Só substitui ${PORT} explicitamente — envsubst sem lista substituiria também $uri,
# $document_root etc. do bloco nginx (variáveis do próprio nginx, não do shell).
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/app.conf

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

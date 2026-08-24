<?php

if (! function_exists('to_iso8601')) {
    /**
     * Formata um DATETIME do MySQL (ou null) como string ISO 8601 — mesmo formato usado
     * pelos campos `createdAt`/`updatedAt`/`date` do contrato público (fase-0-openapi.yaml).
     */
    function to_iso8601(?string $mysqlDatetime): ?string
    {
        if ($mysqlDatetime === null) {
            return null;
        }

        return date(DATE_ATOM, strtotime($mysqlDatetime));
    }
}

if (! function_exists('strip_channel_emoji')) {
    /**
     * Remove emoji de `category` — paridade com o regex do `ChannelController.store`
     * original: `/([-]|\uD83C[\uDF00-\uDFFF]|\uD83D[\uDC00-\uDDFF])/g`. JS opera
     * em pares substitutos UTF-16; traduzido pros codepoints Unicode reais que esses pares
     * representam (conferido a conta: \uD83C+[\uDF00-\uDFFF] = U+1F300–U+1F3FF,
     * \uD83D+[\uDC00-\uDDFF] = U+1F400–U+1F5FF — faixa contígua com a anterior), pra usar
     * com PCRE `/u` (que trabalha em codepoint, não em par substituto).
     */
    function strip_channel_emoji(string $category): string
    {
        return preg_replace('/[\x{E000}-\x{F8FF}\x{1F300}-\x{1F5FF}]/u', '', $category);
    }
}

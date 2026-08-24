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

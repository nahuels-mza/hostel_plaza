<style>
    /*
     * Tablas angostas en mobile: en vez de un <table> ancho con scroll
     * horizontal, cada <tr> se apila como una tarjeta y cada <td> muestra su
     * columna como etiqueta (vía data-label) antes del valor. Pura CSS — no
     * cambia el HTML/JS de cada fila, así que togglear .hidden en una fila
     * de detalle (ajustar/comprar/receta/historial) sigue funcionando igual.
     */
    @media (max-width: 640px) {
        table.responsive-table thead { display: none; }
        table.responsive-table,
        table.responsive-table tbody,
        table.responsive-table tr {
            display: block;
            width: 100%;
        }
        table.responsive-table tr.hidden { display: none; }
        table.responsive-table tr:not(.config-row, .recipe-row, .adjust-row, .purchase-row, .price-edit-row, .produce-row, .history-row) {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0.875rem;
            background: #fff;
        }
        table.responsive-table td {
            display: block;
            padding: 0.3rem 0 !important;
            border: none !important;
        }
        table.responsive-table td[data-label] {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.375rem 0.75rem;
            text-align: right;
            overflow-wrap: anywhere;
        }
        table.responsive-table td[data-label]::before {
            content: attr(data-label);
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            flex-shrink: 0;
        }
        /* Filas de detalle (colspan): siguen pegadas a su fila padre, sin tarjeta propia */
        table.responsive-table tr.config-row,
        table.responsive-table tr.recipe-row,
        table.responsive-table tr.adjust-row,
        table.responsive-table tr.purchase-row,
        table.responsive-table tr.price-edit-row,
        table.responsive-table tr.produce-row,
        table.responsive-table tr.history-row {
            background: #f8fafc;
            border-left: 3px solid #e2e8f0;
            padding: 0.625rem 0.875rem;
            margin: -0.5rem 0 0.75rem;
        }
    }
</style>

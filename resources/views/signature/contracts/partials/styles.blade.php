{{--
    Aparência da prévia do contrato (ContractRenderer::html em modo prévia)
    e do editor. Papel branco também no tema escuro: é a página como será
    impressa.
--}}
<style>
    .ct-paper { background: #fff; color: #1f1819; border-radius: 14px; padding: 28px 30px; font-size: 13.5px; line-height: 1.65; }
    .ct-paper p { margin: 0 0 9px; text-align: justify; }
    .ct-paper h3 { font-size: 13.5px; font-weight: 700; margin: 18px 0 8px; }
    .ct-paper ul { list-style: disc; margin: 0 0 9px 22px; }
    .ct-paper ol { list-style: decimal; margin: 0 0 9px 22px; }
    .ct-paper .ct-missing { background: #fde68a; color: #7c2d12; border-radius: 4px; padding: 0 3px; }
    .ct-paper .ct-clause { scroll-margin-top: 90px; border-radius: 10px; }
    .ct-paper .ct-clause.ct-flash { outline: 3px solid #f2c94c; outline-offset: 6px; }
    .ct-paper .ct-sigs { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 22px 28px; margin: 14px 0 6px; }
    .ct-paper .ct-sig { font-size: 12.5px; }
    .ct-paper .ct-sig span { color: #6d6062; }
    .ct-paper .ct-sig-line { border-top: 1px solid #1f1819; margin: 28px 0 4px; }
    .ct-diff ins { background: #d1fae5; color: #065f46; text-decoration: none; border-radius: 3px; padding: 0 2px; }
    .ct-diff del { background: #fee2e2; color: #991b1b; border-radius: 3px; padding: 0 2px; }
    .ct-rich { min-height: 64px; max-height: 420px; overflow-y: auto; padding: 10px 12px; outline: none; }
    .ct-rich:empty::before { content: attr(data-placeholder); color: var(--ink-3, #9a8e90); }
    .ct-rich ul { list-style: disc; margin-left: 22px; }
    .ct-rich ol { list-style: decimal; margin-left: 22px; }
    .ct-rich p { margin: 0 0 6px; }
</style>

{{--
    CSS do documento do contrato (`contract-document.blade.php`) — sem a tag
    <style>, para ser incluído dentro da de cada página. Um lugar só: a tela de
    impressão e a tela de validação da coordenação mostram o mesmo documento, e
    o coordenador valida exatamente o que o painel imprime.
--}}
        /* ---------- Documento ---------- */
        .doc{ width:100%; max-width:820px; background:var(--paper); color:var(--ink); font-family:var(--serif);
            box-shadow:0 20px 50px -24px rgba(0,0,0,.5); border-radius:6px; overflow:hidden; }
        .doc-header-img img{ display:block; width:100%; height:auto; }
        .doc-footer-img{ margin-top:26px; }
        .doc-footer-img img{ display:block; width:100%; height:auto; }
        .doc-title{ text-align:center; font-size:18px; font-weight:800; margin:26px 34px 10px; font-family:var(--sans); }
        .doc-body{ padding:8px 40px 6px; }
        .doc-body p{ margin:0 0 14px; font-size:15px; text-align:justify; line-height:1.65; }
        .doc-place{ margin-top:22px !important; }
        .doc-signatures{ display:flex; flex-direction:column; align-items:center; gap:34px; margin:44px 0 14px; }
        .doc-sign-block{ width:min(400px,90%); text-align:center; }
        .doc-sign-empty{ height:74px; }
        .doc-sign-img{ height:74px; display:flex; align-items:flex-end; justify-content:center; }
        .doc-sign-img img{ max-height:74px; max-width:100%; object-fit:contain; }
        .doc-sign-mark{ height:74px; display:flex; align-items:flex-end; justify-content:center; font-family:var(--sans); font-size:12px; color:#157a58; font-weight:700; padding-bottom:4px; }
        .doc-sign-line{ border-top:1.5px solid var(--ink); }
        .doc-sign-name{ font-size:15px; font-weight:800; margin-top:8px; font-family:var(--sans); }
        .doc-sign-role{ font-size:12.5px; color:var(--muted); font-family:var(--sans); margin-top:2px; }
        .doc-sign-note{ font-size:11px; color:var(--muted); font-family:var(--sans); margin-top:4px; }

        /* Anexo I — relatório de vendas da comissão, impresso dentro do termo. */
        .doc-annex{ margin-top:30px; border-top:1.5px solid var(--line); padding-top:16px; break-inside:auto; }
        .doc-annex-title{ font-family:var(--sans); font-size:14px; font-weight:800; margin-bottom:4px; }
        .doc-annex-meta{ font-size:12px !important; margin-bottom:10px !important; }
        table.annex{ width:100%; border-collapse:collapse; font-family:var(--sans); font-size:11px; }
        table.annex th{ text-align:left; border-bottom:1px solid var(--line); padding:5px 4px; font-size:10px;
            text-transform:uppercase; letter-spacing:.4px; color:var(--muted); }
        table.annex td{ padding:4px; border-bottom:1px solid rgba(0,0,0,.06); vertical-align:top; }
        table.annex .num{ text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        table.annex .annex-sec{ font-weight:800; padding-top:9px; background:rgba(0,0,0,.04);
            letter-spacing:.6px; font-size:10px; }
        @media print{ table.annex tr{ break-inside:avoid; } }
        /* O cabeçalho (thead) e o rodapé (tfoot) da tabela são repetidos pelo
           navegador em todas as páginas na impressão — sem precisar reservar
           espaço manualmente. */
        .doc-table{ width:100%; border-collapse:collapse; }
        .doc-table > thead > tr > td,
        .doc-table > tfoot > tr > td,
        .doc-table > tbody > tr > td{ padding:0; }

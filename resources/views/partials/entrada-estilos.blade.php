{{--
    Movimento das telas de fora do painel: a página inicial (/) e as telas de
    entrar e de criar conta (10/11/2026). CSS puro, e não classe do Tailwind,
    de propósito: funciona mesmo antes de um `npm run build`.

    .auth-photo-img  foto que assenta uma vez ao abrir (escala 1.06 → 1)
    .auth-photo-drift  as fotos sorteadas (App\View\ClubPhotos, 11/11/2026):
                     aparecem e seguem num movimento lento, de ida e volta —
                     afastam um pouco e deslizam na direção da foto
                     (--drift-x / --drift-y). A escala nunca desce de 1.06,
                     para o deslize não mostrar a borda.
    .auth-enter      os filhos sobem em sequência, uma vez ao abrir
    .auth-tagline    frase sobre a foto, sobe um pouco depois
    .auth-submit     botão principal: sobe um passo no hover, afunda no clique
    .auth-link       link de texto: o sublinhado se desenha no hover

    Com "reduzir movimento" no sistema, nada se move e nada fica escondido.
--}}
<style>
    @keyframes auth-photo-in { from { transform: scale(1.06); } to { transform: none; } }
    @keyframes auth-rise-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

    .auth-photo-img { animation: auth-photo-in 1.4s cubic-bezier(.2, .7, .2, 1) both; }

    @keyframes auth-photo-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes auth-photo-drift {
        from { transform: scale(1.12); }
        to { transform: scale(1.06) translate(var(--drift-x, 0), var(--drift-y, 0)); }
    }
    .auth-photo-img.auth-photo-drift {
        transform-origin: 50% 50%;
        will-change: transform;
        animation:
            auth-photo-fade .9s ease-out both,
            auth-photo-drift 24s ease-in-out infinite alternate;
    }
    .auth-enter > * { animation: auth-rise-in .6s cubic-bezier(.2, .7, .2, 1) both; }
    .auth-enter > *:nth-child(2) { animation-delay: .06s; }
    .auth-enter > *:nth-child(3) { animation-delay: .12s; }
    .auth-enter > *:nth-child(4) { animation-delay: .18s; }
    .auth-tagline { animation: auth-rise-in .8s .2s cubic-bezier(.2, .7, .2, 1) both; }

    .auth-submit { transition: transform .15s ease, background-color .15s ease, box-shadow .15s ease; }
    .auth-submit:hover { transform: translateY(-1px); box-shadow: 0 10px 22px -12px rgb(var(--grena) / .7); }
    .auth-submit:active { transform: translateY(0) scale(.98); box-shadow: none; }

    .auth-link {
        background-image: linear-gradient(currentColor, currentColor);
        background-position: 0 100%;
        background-repeat: no-repeat;
        background-size: 0% 1.5px;
        transition: background-size .25s ease;
        text-decoration: none;
    }
    .auth-link:hover, .auth-link:focus-visible { background-size: 100% 1.5px; }

    @media (prefers-reduced-motion: reduce) {
        .auth-photo-img, .auth-photo-img.auth-photo-drift, .auth-enter > *, .auth-tagline { animation: none; }
        .auth-photo-img.auth-photo-drift { transform: scale(1.06); }
        .auth-submit, .auth-submit:hover, .auth-submit:active { transform: none; transition: none; }
        .auth-link { transition: none; }
    }
</style>

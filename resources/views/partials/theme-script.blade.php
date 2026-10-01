{{--
    Tema claro/escuro. Fica no <head>, antes do CSS, para a página já nascer
    no tema certo e não piscar em branco.

    A escolha da pessoa ('light' ou 'dark') fica no localStorage; sem escolha
    ('system'), segue o sistema e acompanha quando ele muda. O acesso ao
    localStorage vai em try/catch porque em janela anônima ele lança.

    Uso: window.laraTheme.set('dark' | 'light' | 'system'),
         window.laraTheme.get() → a escolha salva.
--}}
<script>
    (function () {
        var KEY = 'laraTheme';
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function saved() {
            try {
                var value = localStorage.getItem(KEY);
                return value === 'light' || value === 'dark' ? value : 'system';
            } catch (e) {
                return 'system';
            }
        }

        function apply(choice) {
            var dark = choice === 'dark' || (choice === 'system' && media.matches);
            document.documentElement.classList.toggle('dark', dark);
        }

        window.laraTheme = {
            get: saved,
            set: function (choice) {
                try {
                    if (choice === 'system') {
                        localStorage.removeItem(KEY);
                    } else {
                        localStorage.setItem(KEY, choice);
                    }
                } catch (e) {}
                apply(choice);
                window.dispatchEvent(new CustomEvent('lara-theme-changed', { detail: choice }));
            },
        };

        media.addEventListener('change', function () {
            if (saved() === 'system') {
                apply('system');
            }
        });

        apply(saved());
    })();
</script>

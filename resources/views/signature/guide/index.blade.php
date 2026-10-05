{{--
    Guia do módulo de assinatura, dentro do painel.

    O guia é um documento com o CSS dele (signature/guide/content), feito
    para virar PDF também. Por isso entra num iframe: dentro do layout do
    painel, o CSS dos dois se misturaria — e a folha do guia é branca nos dois
    temas, como um documento.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Guia">
            Passo a passo do dia a dia: preparar o Word, cadastrar o modelo, emitir o documento e encontrar o assinado.
            <x-slot:actions>
                <a href="{{ route('signature-guide.pdf') }}" target="_blank"
                   class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                    Baixar em PDF
                </a>
            </x-slot:actions>
        </x-page-title>

        <div class="overflow-hidden rounded-card bg-white shadow-card">
            <iframe id="guiaAssinatura" src="{{ route('signature-guide.content') }}" title="Guia de assinatura de documentos"
                    class="block w-full" style="height: 80vh; border: 0;"></iframe>
        </div>


{{-- Script inline: o layout deste projeto não tem @stack (ver signature/templates/partials/form). --}}
<script>
(function () {
    var quadro = document.getElementById('guiaAssinatura');

    if (!quadro) {
        return;
    }

    // O quadro cresce até a altura do guia: uma página rolando dentro de
    // outra é ruim de ler. É a mesma origem, então dá para medir.
    function ajusta() {
        try {
            var doc = quadro.contentDocument;

            if (doc && doc.documentElement) {
                quadro.style.height = doc.documentElement.scrollHeight + 'px';
            }
        } catch (e) { /* fica com a altura padrão */ }
    }

    quadro.addEventListener('load', function () {
        ajusta();

        // Os links do sumário rolam a PÁGINA até o capítulo, já que o quadro
        // não tem rolagem própria.
        try {
            quadro.contentDocument.addEventListener('click', function (ev) {
                var link = ev.target.closest('a[href^="#"]');

                if (!link) {
                    return;
                }

                var alvo = quadro.contentDocument.getElementById(link.getAttribute('href').slice(1));

                if (alvo) {
                    ev.preventDefault();
                    window.scrollTo({
                        top: quadro.getBoundingClientRect().top + window.scrollY + alvo.offsetTop - 12,
                        behavior: 'smooth'
                    });
                }
            });
        } catch (e) { /* sem acesso ao conteúdo: os links ficam como estão */ }
    });

    window.addEventListener('resize', ajusta);
})();
</script>
    </x-page>
</x-app-layout>

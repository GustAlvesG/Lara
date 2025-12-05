
function toggleSearchAccordion() {
    const body = document.getElementById('search-accordion-body');
    const icon = document.getElementById('accordion-icon');
    const duration = 500; // Tempo de espera, deve ser igual ao duration-500 do CSS

    // Se o body está atualmente fechado (maxHeight é 0 ou vazio)
    if (body.style.maxHeight === '0px' || body.style.maxHeight === '') {
        // --- ABRIR ---
        
        // 1. Define a altura para o valor exato do conteúdo
        body.style.maxHeight = body.scrollHeight + 'px';
        
        // 2. Transiciona a opacidade para visível
        body.classList.remove('opacity-0');
        body.classList.add('opacity-100');
        
        // 3. Gira o ícone
        icon.classList.add('rotate-180');

    } else {
        // --- FECHAR ---

        // 1. Define a altura inicial da transição (necessário para que a transição comece do valor atual)
        body.style.maxHeight = body.scrollHeight + 'px'; 
        
        // 2. Transiciona a opacidade para invisível
        body.classList.remove('opacity-100');
        body.classList.add('opacity-0');

        // 3. Zera a altura após a opacidade começar
        setTimeout(() => {
            body.style.maxHeight = '0';
        }, 10); // Pequeno delay para garantir que a opacidade inicie

        // 4. Gira o ícone de volta
        icon.classList.remove('rotate-180');
    }
}

// Inicializa a altura máxima para 0 ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const body = document.getElementById('search-accordion-body');
    if (body) {
        body.style.maxHeight = '0';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Inicializa o listener de filtragem se o input estiver presente
    const input = document.getElementById('search-filter-text');
    if (input) {
        input.addEventListener('keyup', filterCards);
    }
});

/**
 * Função que filtra os cards de serviço em tempo real.
 * Requer que o contêiner de cards tenha o ID #elements-container.
 * ASSUME que o item do Grid (o elemento a ser escondido) é o pai direto do card âncora (o 'a.elements').
 */
function filterCards() {
    const input = document.getElementById('search-filter-text');
    if (!input) return;

    const filter = input.value.toUpperCase();
    const container = document.getElementById('elements-container');
    if (!container) return; 


    // Seleciona todos os elementos que representam o card content (a tag 'a' ou o wrapper do card)
    const cardAnchors = container.querySelectorAll('.elements'); 

    cardAnchors.forEach(cardAnchor => {
        // O elemento que precisamos esconder/mostrar é o pai direto do card, que é o item do grid.
        const gridItem = cardAnchor.parentElement; 

        // Captura todo o texto dentro do card (Título, Responsável, etc.) para tornar a busca abrangente.
        const fullCardText = cardAnchor.textContent || cardAnchor.innerText;
        const textToFilter = fullCardText.toUpperCase();

        // Lógica de Filtragem: Esconde o item do grid se o texto do filtro não estiver no card
        if (textToFilter.indexOf(filter) > -1) {
            // Mostra o item do grid (o display é restaurado e o grid reflows)
            gridItem.style.display = ""; 
        } else {
            // Esconde o item do grid (o grid reflows corretamente)
            gridItem.style.display = "none"; 
        }
    });
}
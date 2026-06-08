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
        const fullCardText = cardAnchor.textContent || cardAnchor.innerText;
        const textToFilter = fullCardText.toUpperCase();

        if (textToFilter.indexOf(filter) > -1) {
            cardAnchor.style.display = "";
        } else {
            cardAnchor.style.display = "none";
        }
    });
}
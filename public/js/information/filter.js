/**
 * Filtro em tempo real dos cards do InfoClube.
 *
 * Esconde o próprio item do grid (.elements). Antes cada card vinha embrulhado
 * num div extra que continuava ocupando a célula depois de esconder o card,
 * o que abria buracos na grade.
 */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('search-filter-text');

    if (input) {
        input.addEventListener('input', filterCards);
    }
});

function filterCards() {
    var input = document.getElementById('search-filter-text');
    var container = document.getElementById('elements-container');

    if (!input || !container) {
        return;
    }

    var filter = input.value.trim().toUpperCase();
    var cards = container.querySelectorAll('.elements');
    var visible = 0;

    cards.forEach(function (card) {
        var text = (card.textContent || '').toUpperCase();
        var matches = filter === '' || text.indexOf(filter) > -1;

        card.style.display = matches ? '' : 'none';

        if (matches) {
            visible++;
        }
    });

    var empty = document.getElementById('no-results');
    if (empty) {
        empty.classList.toggle('hidden', visible > 0);
    }
}

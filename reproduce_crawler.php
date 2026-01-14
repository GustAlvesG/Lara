<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

$html = <<<'HTML'
<table>
    <tr class="relatorioEspelhoPontoBodyRow">
        <td>01/01/2024</td>
        <td>08:00</td>
        <td>Apontamentos</td>
        <td>Extra 1</td>
        <td>Extra 2</td>
        <td>Extra 3</td>
        <td>Extra 4</td>
        <td>Extra 5</td>
        <td>Extra 6</td>
        <td>01:00</td> <!-- Index 9 -->
        <td>00:00</td> <!-- Index 10 -->
    </tr>
</table>
HTML;

$crawler = new Crawler($html);
$row = $crawler->filter('.relatorioEspelhoPontoBodyRow')->first();
$allTd = $row->filter('td');

echo "Count: " . $allTd->count() . "\n";
if ($allTd->count() > 9) {
    echo "Index 9 text: " . $allTd->eq(9)->text() . "\n";
} else {
    echo "Index 9 does not exist\n";
}
echo "Index 9 is Crawler? " . (is_a($allTd->eq(9), Crawler::class) ? 'Yes' : 'No') . "\n";

try {
    $parts = explode(':', $allTd->eq(9));
} catch (TypeError $e) {
    echo "Caught TypeError: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
}

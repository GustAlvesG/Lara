<div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <h1 class="data-car-title text-gray-800 dark:text-gray-200">
        Dados do Acesso
    </h1>
    <div class="row">
        <div class="col">
            <div class="data-car">
                <div class="data-car-label text-gray-800 dark:text-gray-200">
                    Placa
                </div>
                <div class="data-car-value text-gray-800 dark:text-gray-200">
                    {{ $car['plate'] }}
                </div>
            </div>
        </div>
        <div class="col">
            <div class="data-car">
                <div class="data-car-label text-gray-800 dark:text-gray-200">
                    Cor do carro
                </div>
                <div class="data-car-value text-gray-800 dark:text-gray-200">
			{{ isset($car['color']) ? strtoupper($car['color']) : 'Não encontrado' }}
                </div>
            </div>
        </div>
        <div class="col">
            <div class="data-car">
                <div class="data-car-label text-gray-800 dark:text-gray-200">
                    Quantidade de acessos
                </div>
                <div class="data-car-value text-gray-800 dark:text-gray-200">
                    {{ count($data) }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mb-8 p-4 border border-gray-100 dark:border-gray-700 rounded-lg shadow-sm bg-white dark:bg-gray-750">
                                @php
                                    $logDateTime = explode(" ", $log['entry_date']);
                                    $logDate = $logDateTime[1];
                                    $logTime = $logDateTime[0];
                                @endphp

                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">Acesso em: {{ $logDate }} às {{ $logTime }}</p>

                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

                                    <!-- Imagem (4/12 Colunas) -->
                                    <div class="md:col-span-4 flex justify-center items-start">

                                        <img src="{{ asset('storage/img_car/' . $log['file']) }}"
                                            onerror="this.onerror=null;this.src='https://placehold.co/200x150/f0f0f0/808080?text=Sem+Foto';"
                                            alt="Foto do carro"
                                            class="max-w-full h-auto rounded-lg shadow-md">
                                    </div>

                                    <!-- Tabela de Condutores Associados (8/12 Colunas) -->
                                    <div class="md:col-span-8 overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="py-2 px-2 text-xs font-medium text-left text-gray-600 dark:text-gray-300 uppercase">Mat.</th>
                                                    <th class="py-2 text-xs font-medium text-left text-gray-600 dark:text-gray-300 uppercase">Nome</th>
                                                    <th class="py-2 text-xs font-medium text-left text-gray-600 dark:text-gray-300 uppercase">Telefone</th>
                                                    <th class="py-2 text-xs font-medium text-left text-gray-600 dark:text-gray-300 uppercase">Horário</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach($log['access'] as $driver)
                                                    <tr class="hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition duration-100">
                                                        <td class="py-2 px-2 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ $driver->TitleCode }}</td>
                                                        <td class="py-2 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $driver->Name }}</td>
                                                        <td class="py-2 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $driver->Telephone }}</td>
                                                        <td class="py-2 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $driver->date }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>

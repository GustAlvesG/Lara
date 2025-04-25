<div class="my-4 border-t border-gray-200">
    <div class="row">
        <div class="col-4">
            <div class="flex items-center justify-center data-car-container">
                <div class="text-block">
                    <p>{{ $item['entry_date'] }}</p>
                </div>
                <img src="{{ asset('storage/img_car/' . $item['file']) }}" style="width: 50%, height:50%" alt="">
            </div>
        </div>
        <div class="col-8">
            <table class="min-w-full divide-y divide-gray-200 table-fixed dark:divide-gray-700" style="width: 100%">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <th scope="col" class="py-2  text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-400" style="width:10%">
                        Mat.
                    </th>
                    <th scope="col" class="py-2 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-400">
                        Nome
                    </th>
                    <th scope="col" class="py-2 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-400">
                        Telefone
		    </th>
		   <th scope="col" class="py-2 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-400">
                        Horário
                    </th>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @foreach ($item['access'] as $access)
                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-700">
                            <td class="py-2 px-2 text-sm font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ $access->TitleCode }}
                                
                            </td>
                            <td class="py-2 text-sm font-medium text-gray-500 whitespace-nowrap dark:text-white">
                                {{ $access->Name }} 
                                {{-- @if ($access->Titular == 1)
                                    <t style="color:red">(TITULAR)</t>
                                @endif --}}
                            </td>
                            <td class="py-2 text-sm font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ $access->Telephone }}
                            </td>
			    <td class="py-2 text-sm font-medium text-gray-900 whitespace-nowrap dark:text-white">
				 {{ $access->date }}
			   </td>
                        </tr>
                    @endforeach
                </tbody>           
            </table>
        </div>
    </div>
</div>



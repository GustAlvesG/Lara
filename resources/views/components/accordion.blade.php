<div class="bg-white p-6 shadow-md rounded-lg">
    
    <div class="flex justify-between items-center cursor-pointer"
        onclick="toggleSearchAccordion()">
        
        <div>
             <span class="text-xl font-bold text-gray-800 dark:text-gray-200">
                {{ $title }}
             </span>
        </div>
        
        <span class="text-indigo-600"> 
            <svg id="accordion-icon" 
                class="w-6 h-6 inline-block transition-transform duration-300" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24" 
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </span>
    </div>

    <div id="search-accordion-body" 
     class=" 
            max-h-0 opacity-0 
            overflow-hidden 
            transition-all duration-500 ease-in-out">

    {{ $body }}
    
</div>
</div>
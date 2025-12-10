<x-guest-layout>
    <x-slot name="slot">
        <!-- Card de Registro Centralizado e Elegante -->
    <!-- Card um pouco maior para acomodar mais campos -->
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden transform transition duration-500 hover:shadow-3xl">
        
        <!-- Cabeçalho do Card -->
        <div class="p-8 backdrop-blur-sm text-white text-center" style="background: #A00001;
background: linear-gradient(90deg,rgba(160, 0, 1, 1) 0%, rgba(126, 20, 23, 1) 100%);">
            
            <!-- ESPAÇO PARA LOGO -->
            <div class="mb-4 flex justify-center">
                <x-application-logo style="background: white;" class="h-20 w-20 rounded-full border-4 border-white shadow-lg" />
            </div>

            <h1 class="text-3xl font-extrabold mb-1">Crie sua Conta</h1>
            <p class="text-sm">Preencha os dados abaixo para se registrar.</p>
        </div>

        <!-- Formulário de Registro -->
        <div class="p-8">
            <form method="POST" action="/register">
                <!-- TOKEN CSRF (Mantenha para Laravel) -->
                @csrf

                <!-- Campo Nome -->
                <div class="mb-5">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                    <input type="text" id="name" name="name" required autocomplete="name" autofocus
                           placeholder="Seu nome"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>

                <!-- Campo E-mail -->
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="seu.email@exemplo.com"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>

                <!-- Campo Senha -->
                <div class="mb-5">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>
                
                <!-- Campo Confirmar Senha -->
                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>

                <!-- Botão de Registro (Design de Alto Contraste) -->
                <button type="submit" 
                        class="w-full px-4 py-3 text-white rounded-xl font-extrabold text-lg shadow-lg hover:bg-red-700 transition duration-150 transform hover:scale-[1.01] focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        style="background: #A00001;">
                    Registrar
                </button>


                @if ($errors->has('email') || $errors->has('password'))
                     <!-- ÁREA DE MENSAGEM DE ERRO (Mock) -->
                    <div id="login-error-message" 
                        class="my-4 p-3 text-sm text-red-800 bg-red-100 border border-red-300 rounded-lg text-center" 
                        role="alert">
                        @foreach ($errors->all() as $error)
                            {{ $error }}<br>
                        @endforeach
                    </div>
                @endif
                
                <!-- Link para Login -->
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">Já tem uma conta? 
                        <a href="/login" class="font-medium transition duration-150" style="color: #A00001;">
                            Faça Login
                        </a>
                    </p>
                </div>
            </form>
        </div>
        
    </div>
    </x-slot>
</x-guest-layout>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Projeto 2XKO</title>
    <!-- Carregamento do Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilo customizado para o fundo degradê sutil e a fonte Inter */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9; /* Fundo cinza claro */
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <!-- Card de Login Centralizado e Elegante -->
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden transform transition duration-500 hover:shadow-3xl">
        
        <!-- Cabeçalho do Card -->
        <div class="p-8 backdrop-blur-sm text-white text-center" style="background: #A00001;
background: linear-gradient(90deg,rgba(160, 0, 1, 1) 0%, rgba(126, 20, 23, 1) 100%);">
            
            <!-- ESPAÇO PARA LOGO ADICIONADO -->
            <div class="mb-4 flex justify-center">
                <img src="https://placehold.co/100x100/ffffff/A00001?text=LOGO" 
                     alt="Logo da Aplicação" 
                     class="h-20 w-20 rounded-full border-4 border-white shadow-lg">
            </div>

            <h1 class="text-3xl font-extrabold mb-1">Lara</h1>
            <p class="text-sm">Entre com suas credenciais para continuar.</p>
        </div>

        <!-- Formulário -->
        <div class="p-8">
            <form method="POST" action="/login">
                <!-- TOKEN CSRF (Mantenha para Laravel) -->
                @csrf

                <!-- Campo E-mail -->
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" id="email" name="email" required autocomplete="email" autofocus
                           placeholder="seu.email@exemplo.com"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>

                <!-- Campo Senha -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base">
                </div>
                
                {{-- <!-- Opções Adicionais (Lembrar-me e Esqueceu a Senha) -->
                <div class="flex items-center justify-between mb-8">
                    
                    <!-- Lembrar-me -->
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                            Lembrar-me
                        </label>
                    </div>

                    <!-- Esqueceu a Senha -->
                    <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition duration-150">
                        Esqueceu a Senha?
                    </a>
                </div> --}}

                <!-- Botão de Login (Design de Alto Contraste) -->
                <button type="submit" 
                        class="w-full px-4 py-3 text-white rounded-xl font-extrabold text-lg shadow-lg hover:bg-indigo-700 transition duration-150 transform hover:scale-[1.01] focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        style="background: #A00001;">
                    Entrar
                </button>

                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">Ainda não tem uma conta? 
                        <a href="/register" class="font-medium transition duration-150" style="color: #A00001;">
                            Registre-se
                        </a>
                    </p>
                </div>
            </form>
        </div>
        
    </div>
</body>
</html>
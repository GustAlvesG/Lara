<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>{{ config('app.name', 'Laravel') }}</title>
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
        {{ $slot }}
</body> 
</html>
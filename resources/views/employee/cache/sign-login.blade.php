<x-cache-sign-layout title="Assinar Cachê" heading="Identifique-se">
    <div class="bg-white rounded-2xl shadow-xl p-6">
        <p class="text-gray-600 mb-5">
            Informe a sua <strong>matrícula</strong> ou o seu <strong>CPF</strong> para ver os cachês que
            estão aguardando a sua assinatura.
        </p>

        <form method="POST" action="{{ route('employee-caches.sign.identify') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Matrícula ou CPF</label>
                <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus
                    inputmode="numeric" autocomplete="off"
                    class="w-full px-4 py-4 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
            </div>

            <button type="submit" class="w-full px-6 py-4 bg-[#A00001] text-white rounded-xl font-bold text-lg shadow-lg hover:bg-[#800000] transition">
                Continuar
            </button>
        </form>
    </div>
</x-cache-sign-layout>

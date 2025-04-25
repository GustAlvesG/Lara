<!-- Início do formulário. A ação do formulário é definida para a rota 'parking.show' -->
<form action="{{ route('parking.show') }}" method="POST">
  <!-- Campo CSRF para proteção contra ataques de falsificação de solicitação entre sites -->
  @csrf
  <!-- Início do container flexível para os campos do formulário -->
  <div class="flex gap-4 items-center">
    <!-- Início do campo de entrada para a placa do veículo -->
    <div class="w-1/4"> 
      <!-- Rótulo para o campo de entrada -->
      <x-input-label>
        Placa do veículo
      </x-input-label>
      <!-- Campo de entrada para a placa do veículo. O atributo 'required' indica que este campo é obrigatório -->
      <x-text-input label="Placa" name="plate" required/> 
    </div>
    <!-- Início do campo de entrada para a data -->
    <div class="w-1/4">
      <!-- Rótulo para o campo de entrada -->
      <x-input-label>
        Data
      </x-input-label>
      <!-- Campo de entrada para a data -->
      <x-datetime-input label="Data e Hora" name="datetime" /> 
    </div>
  </div>
  <!-- Espaçamento -->
  <br>
  <!-- Botão para submeter o formulário -->
  <x-primary-button>Buscar</x-primary-button>
<!-- Fim do formulário -->
</form>
{{--
    Tablet de assinatura › o fim da IIFE de quiosque/js/nucleo: retoma a
    sessão (ou começa pelo leitor / pelo Termo de Menores) e fecha o script.
--}}
@verbatim

  /* ---------------------------------------------------------------------
   | Início
   |---------------------------------------------------------------------*/

  // Uma sessão pode sobreviver a um recarregamento acidental da página: o
  // cookie continua valendo. Retoma do começo do documento, que é o único
  // ponto seguro para recomeçar.
  api('GET', CFG.rotas.sessao)
    .then(function (dados) { abreAtendimento(dados); })
    .catch(function () {
      if (CFG.menores) {
        menoresInicia();
        return;
      }

      iniciaLeitor();
    });

  // Recarregar por engano no meio do atendimento não pode deixar traço na
  // tela seguinte.
  window.addEventListener('pagehide', function () {
    desligaCamera();
  });
})();
@endverbatim

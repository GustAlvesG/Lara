<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stopwords para busca por palavra-chave
    |--------------------------------------------------------------------------
    |
    | Usadas por App\Http\Requests\SearchInformationRequest para reduzir uma
    | frase em linguagem natural (ex: "Qual o custo da aula de natação?") às
    | palavras-chave relevantes (ex: "custo", "aula", "natação").
    |
    | Cadastre sempre em minúsculas e SEM acento — a comparação normaliza o
    | texto de entrada com Illuminate\Support\Str::ascii() antes de checar
    | contra esta lista, então "à", "Á", "a" e "A" caem todos em "a".
    |
    */

    'stopwords' => [
        'a', 'ao', 'aos', 'aquela', 'aquelas', 'aquele', 'aqueles', 'aquilo',
        'as', 'ate', 'cade', 'com', 'como', 'da', 'das', 'de', 'dela', 'delas',
        'dele', 'deles', 'depois', 'do', 'dos', 'e', 'ela', 'elas', 'ele',
        'eles', 'em', 'entre', 'era', 'eram', 'eramos', 'essa', 'essas',
        'esse', 'esses', 'esta', 'estamos', 'estas', 'estava', 'estavam',
        'estavamos', 'este', 'esteja', 'estejam', 'estejamos', 'estes',
        'esteve', 'estive', 'estivemos', 'estiver', 'estivera', 'estiveram',
        'estiveramos', 'estiverem', 'estivermos', 'estivesse', 'estivessem',
        'estivessemos', 'estou', 'eu', 'foi', 'fomos', 'for', 'fora', 'foram',
        'foramos', 'forem', 'formos', 'fosse', 'fossem', 'fossemos', 'fui',
        'ha', 'haja', 'hajam', 'hajamos', 'hao', 'havemos', 'hei', 'houve',
        'houvemos', 'houver', 'houvera', 'houveram', 'houveramos', 'houverao',
        'houverei', 'houverem', 'houveremos', 'houveria', 'houveriam',
        'houveriamos', 'houvermos', 'houvesse', 'houvessem', 'houvessemos',
        'isso', 'isto', 'ja', 'lhe', 'lhes', 'mais', 'mas', 'me', 'mesmo',
        'meu', 'meus', 'minha', 'minhas', 'muito', 'na', 'nao', 'nas', 'nem',
        'no', 'nos', 'nossa', 'nossas', 'nosso', 'nossos', 'num', 'numa', 'o',
        'onde', 'os', 'ou', 'para', 'pela', 'pelas', 'pelo', 'pelos', 'por',
        'porque', 'qual', 'quais', 'quando', 'quanta', 'quantas', 'quanto',
        'quantos', 'que', 'quem', 'sao', 'se', 'seja', 'sejam', 'sejamos',
        'sem', 'ser', 'sera', 'serao', 'serei', 'seremos', 'seria', 'seriam',
        'seriamos', 'seu', 'seus', 'so', 'somos', 'sou', 'sua', 'suas',
        'tambem', 'te', 'tem', 'temos', 'tenha', 'tenham', 'tenhamos',
        'tenho', 'tera', 'terao', 'terei', 'teremos', 'teria', 'teriam',
        'teriamos', 'teu', 'teus', 'teve', 'tinha', 'tinham', 'tinhamos',
        'tive', 'tivemos', 'tiver', 'tivera', 'tiveram', 'tiveramos',
        'tiverem', 'tivermos', 'tivesse', 'tivessem', 'tivessemos', 'tu',
        'tua', 'tuas', 'tudo', 'um', 'uma', 'voce', 'voces', 'vos',
        'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'ao', 'à', 'aos', 'às',
        
        'do', 'da', 'dos', 'das', 'no', 'na', 'nos', 'nas', 'pelo', 'pela', 'pelos', 'pelas',
        
        # Pronomes
        'eu', 'tu', 'ele', 'ela', 'nós', 'vós', 'eles', 'elas', 'me', 'mim', 'comigo', 
        'te', 'ti', 'contigo', 'se', 'si', 'consigo', 'lhe', 'nos', 'vos', 'lhes', 
        'meu', 'minha', 'meus', 'minhas', 'teu', 'tua', 'teus', 'tuas', 'seu', 'sua', 
        'seus', 'suas', 'nosso', 'nossa', 'nossos', 'nossas', 'vosso', 'vossa', 'vossos', 'vossas',
        
        # Preposições
        'de', 'em', 'por', 'para', 'com', 'sem', 'sob', 'sobre', 'entre', 'até', 
        'desde', 'contra', 'perante', 'através', 'além', 'dentro', 'fora', 'perto', 
        'longe', 'durante',
        
        # Conjunções
        'e', 'mas', 'ou', 'porque', 'pois', 'que', 'se', 'como', 'quando', 'embora', 
        'porém', 'todavia', 'contudo', 'então', 'também', 'apesar', 'caso', 'além disso', 
        'portanto', 'logo', 'ainda que', 'a fim de', 'com o objetivo de',
        
        # Verbos auxiliares e comuns
        'ser', 'estar', 'ter', 'haver', 'ir', 'vir', 'fazer', 'poder', 'dever', 
        'querer', 'saber', 'está', 'estão', 'tem', 'tenho', 'é',
        
        # Demonstrativos
        'esse', 'essa', 'isso', 'este', 'esta', 'isto', 'aquele', 'aquela', 'aquilo', 
        'aqueles', 'aquelas', 'deste', 'desta', 'destes', 'destas', 'disso', 'daquilo', 
        'nisto', 'naquilo',
        
        # Localizadores
        'lá', 'aqui', 'ali', 'onde', 'aonde',
        
        # Interjeições e expressões comuns
        'ah', 'oh', 'ei', 'oi', 'olá', 'opa', 'eita', 'nossa', 'caramba', 'poxa', 
        'uau', 'xi', 'ih', 'ué', 'hein', 'que que é isso', 'quê',
        
        # Gírias e expressões coloquiais
        'cara', 'mano', 'mina', 'véi', 'velho', 'brother', 'meu', 'meu chapa', 
        'mano do céu', 'parça', 'parceiro', 'camarada', 'meu rei', 'minha rainha', 
        'bro', 'meu anjo', 'fera', 'chefe', 'paizão', 'mainha', 'veinho', 'velhinho', 
        'moleque', 'garoto', 'menino', 'menina', 'guri', 'guria', 'piá', 'piázinho', 
        'gajo', 'gaja', 'bacana', 'maneiro', 'massa', 'show', 'top', 'legal', 'beleza', 
        'joia', 'firmeza', 'daora', 'dahora', 'da hora', 'responsa', 'sinistro', 'brabo', 
        'brabíssimo', 'irado', 'supimpa', 'bala', 'zica', 'animal', 'monstro', 'mito', 'lenda',
        
        # Abreviações e siglas comuns na internet
        'pq', 'tb', 'tbm', 'vc', 'cê', 'ce', 'c', 'mt', 'mto', 'mta', 'td', 'tdo', 
        'tda', 'hj', 'amg', 'amigo', 'amiga', 'bjs', 'bjss', 'bjo', 'bjao', 'vlw', 
        'flw', 'fvr', 'por favor', 'abs', 'vdd', 'aff', 'pfv', 'pfvr', 'sla', 'slc', 
        'sdds', 'tmj', 'tamo junto', 'fds', 'findi', 'blz', 'bele', 'falou', 'falows', 
        'fmz', 'fmza', 'mds', 'oxe', 'oxi', 'vey', 'véi', 'vix', 'vish', 'vcs', 'tá', 
        'tô', 'tamos', 'tamo', 'cadê', 'd', 'pro', 'pra', 'q', 'qualé', 'tipo', 'né', 
        'qnd', 'aki', 'vamo', 'vambora', 'partiu', 'bora', 'falô', 'blza', 'sup', 'obg', 
        'pls', 'ñ', 'num', 'msm', 'sdd', 'pqn', 'pqns', 'agr', 'poxa', 'po', 'uai', 
        'eita', 'trem', 'bixim', 'vish', 'causo', 'sô', 'ôxe',
        
        # Regionalismos
        'bão', 'ocê', 'oxente', 'painho', 'égua', 'bah', 'tchê', 'aham', 'ahã', 
        'orra meu', 'home', 'homi', 'muié', 'muler', 'visse', 'tu', 'num é', 'nera',
        
        # Marcadores de discurso
        'tipo assim', 'aí', 'pois é', 'quer dizer', 'sabe', 'entende', 'sacou', 
        'tá ligado', 'ok', 'okay', 'tranquilo', 'suave', 'de boa', 'fechou', 
        'combinado', 'entendido',
        
        # Expressões de afirmação e negação
        's', 'n', 'yep', 'nop', 'ahan', 'nops', 'de jeito nenhum', 'com certeza', 
        'claro', 'óbvio', 'lógico', 'evidente', 'sem dúvida', 'quem sabe', 'vai ver', 
        'pode ser',
        
        # Expressões de quantidade e intensidade
        'bastante', 'pra caramba', 'pra cacete', 'pra dedéu', 'pácas', 'biga', 
        'uma porrada', 'um monte', 'um tantão', 'um bocado', 'um tiquinho', 
        'um cadinho', 'um tico', 'uma belezura',
        
        # Expressões de concordância
        'tá bom', 'tá bem', 'tá certo', 'pode crer', 'tô dentro', 'topo', 'vamos', 
        'bora lá', 'tá de boa',
        
        # Expressões de discordância
        'tá nada', 'que nada', 'nem a pau', 'nem ferrando', 'nem morto', 
        'nem que a vaca tussa', 'tá louco', 'tá doido', 'nem pensar',
        
        # Expressões de despedida
        'tchau', 'até logo', 'até mais', 'fui', 'to indo', 'té mais', 'inté', 
        'bye', 'xau', 'beijo', 'abraço', 'abç', 'fique com Deus', 'vai com Deus', 
        'se cuida', 'se cuide', 'fica bem', 'fique bem',
        
        # Expressões de saudação
        'e aí', 'fala', 'fala aí', 'fala tu', 'salve', 'quali', 'como vai', 
        'como tá', 'tudo bem', 'tudo bom', 'eae', 'coé', 'alô',
        
        # Expressões de agradecimento
        'valeu', 'obrigado', 'obrigada', 'brigado', 'brigadão', 'muito obrigado', 
        'grato', 'gratidão', 'thanks', 'thx', 'tanks', 'tenks',
        
        # Termos de internet e redes sociais
        'rs', 'kkkk', 'haha', 'hehe', 'lol', 'risos', 'kkk', 'hahaha', 'postar', 
        'post', 'stories', 'story', 'feed', 'timeline', 'curtir', 'like', 'reagir', 
        'compartilhar', 'share', 'seguir', 'follow', 'unfollow', 'bio', 'trending', 
        'viral', 'viralizar',
        
        # Expressões de dúvida
        'será', 'será mesmo', 'não sei não', 'tô na dúvida', 'to em dúvida', 
        'vai saber', 'sei lá', 'não faço ideia', 'nem imagino',
        
        # Conectores expandidos
        'tanto quanto', 'bem como', 'a menos que', 'a não ser que', 'conforme', 
        'à medida que', 'sempre que', 'logo que', 'assim que', 'uma vez que', 
        'na medida em que', 'de modo que', 'de forma que', 'caso contrário', 
        'fora isso', 'além do mais', 'por outro lado', 'por um lado', 
        'de uma forma ou de outra', 'apesar de tudo', 'ao contrário', 
        'em contrapartida', 'em compensação', 'por isso mesmo', 'desse modo', 
        'desta forma', 'assim sendo', 'por consequência', 'consequentemente', 
        'para tanto', 'aliás', 'inclusive', 'ademais', 'com isso', 'por assim dizer', 
        'em suma', 'afinal', 'a fim de que', 'de modo a',
        
        # Expressões expandidas
        'você', 'vocês', 'porquê', 'entaum', 'bls', 'dexa', 'tá ligado', 'tá sabendo', 
        'tá com', 'tá ok', 'cê tá', 'ocê', 'ocês', 'nois', 'nois é', 'é nois', 
        'na moral', 'escuta só', 'né não', 'nu', 'rlx', 'susto', 'tru', 'brodi', 
        'eu to', 'eu tô', 'nois tá', 'nóis tá', 'tu tá', 'tu vai', 'c vai', 'tu vamo', 
        'tá suave', 'tá de boaça'
    ],

];

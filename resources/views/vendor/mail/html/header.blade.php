@props(['url'])
{{--
    Cabecalho dos emails do Meu Negocio.

    Monograma + nome escrito, nao so a imagem: a maioria dos clientes de email
    bloqueia imagem por padrao, e um cabecalho que depende dela vira um espaco
    em branco justo no email que precisa provar de quem e. Com o nome em texto,
    a marca aparece de qualquer jeito e a imagem so soma.

    Usa `marca-mn.png` — o monograma "MN" gerado a partir do SVG da marca
    (`partials/logo-mark`). NAO use `logo-abbr.png` nem `logo-full.png`: apesar
    do nome, sao o logo do template Duralux (um "D" e a palavra DURALUX), e
    mandar a marca de um template de terceiro no email transacional do produto
    e o tipo de erro que so aparece na caixa de entrada do cliente. SVG tambem
    nao serve aqui — Gmail e Outlook removem.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
{{-- alt vazio de proposito: a imagem e decorativa, o nome ja vem em texto
     logo abaixo. Com alt preenchido, leitor de tela e cliente com imagem
     bloqueada anunciavam "Meu Negocio" duas vezes seguidas. --}}
<img src="{{ asset('assets/images/marca-mn.png') }}" width="48" height="48" alt=""
     style="display: block; margin: 0 auto 10px; border-radius: 13px;">
<span style="color: #2740b4; font-size: 19px; font-weight: bold;">{!! $slot !!}</span>
</a>
</td>
</tr>

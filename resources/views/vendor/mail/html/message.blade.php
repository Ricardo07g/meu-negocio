{{--
    Estrutura de todo email do sistema.

    Copia da versao do framework com duas mudancas: o rodape sai do ingles
    ("All rights reserved.") e ganha uma linha dizendo POR QUE a pessoa recebeu
    a mensagem — email transacional sem essa linha e o que filtro de spam e
    destinatario tratam como suspeito.
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Você recebeu este email porque tem uma conta no {{ config('app.name') }}.

© {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

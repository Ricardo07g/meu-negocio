{{--
    Email de confirmacao de endereco.

    Mesma ordem do email de recuperacao de senha: primeiro o botao (quem se
    cadastrou quer entrar), depois o aviso para quem nao reconhece o cadastro.
--}}
<x-mail::message>
# Confirme seu e-mail, {{ $nome }}

Falta só um passo para começar a usar o **Meu Negócio**: confirmar que este endereço é seu.

<x-mail::button :url="$urlVerificacao">
Confirmar e-mail
</x-mail::button>

**Este link vale por 60 minutos.** Se ele expirar, é só entrar no sistema e pedir outro na tela de confirmação.

<x-mail::subcopy>
**Não reconhece este cadastro?** Pode ignorar este email: sem a confirmação, a conta não é liberada.

Se o botão não funcionar, copie e cole este endereço no navegador:
[{{ $urlVerificacao }}]({{ $urlVerificacao }})
</x-mail::subcopy>
</x-mail::message>

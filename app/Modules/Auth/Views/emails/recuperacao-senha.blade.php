{{--
    Email de redefinicao de senha.

    Ordem proposital: primeiro o que fazer, depois o alerta de seguranca. Quem
    pediu a redefinicao quer o botao; quem NAO pediu precisa entender rapido que
    nao ha nada a fazer. Inverter isso faz o leitor legitimo caçar o botao no
    meio de um texto sobre invasao de conta.
--}}
<x-mail::message>
# Vamos criar sua nova senha

Recebemos um pedido para redefinir a senha da sua conta no **Meu Negócio** — a que você usa para acompanhar agenda, vendas e caixa do seu negócio.

Clique no botão abaixo para escolher uma nova senha:

<x-mail::button :url="$urlRedefinicao">
Criar nova senha
</x-mail::button>

**Este link vale por 60 minutos** e só pode ser usado uma vez. Depois disso, é só pedir outro na tela de login.

<x-mail::subcopy>
**Não foi você que pediu?** Pode ignorar este email com tranquilidade: sua senha continua a mesma e ninguém consegue entrar na sua conta com este link sem acesso a esta caixa de entrada.

Se o botão não funcionar, copie e cole este endereço no navegador:
[{{ $urlRedefinicao }}]({{ $urlRedefinicao }})
</x-mail::subcopy>
</x-mail::message>

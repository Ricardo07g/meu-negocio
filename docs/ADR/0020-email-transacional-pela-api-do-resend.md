# ADR-0020 — E-mail transacional pela API do Resend, não por SMTP

## Status

Aceito — agosto/2026. Fecha o fluxo que o ADR-0018 e o tema de marca deixaram pela metade: até aqui
o e-mail de recuperação de senha era **renderizado e descartado**.

## Contexto

O sistema manda **um** e-mail: o link de redefinição de senha (`Usuario::sendPasswordResetNotification`
→ `RecuperacaoSenhaMailable`). O template ganhou identidade própria (tema `meu-negocio`, monograma,
texto em PT-BR) e o fluxo ganhou testes. Faltava a entrega: em produção o mailer era `log`, então a
mensagem ia para o `stderr` do container e o usuário ficava esperando um e-mail que nunca chegou.
Quem esquecia a senha simplesmente perdia a conta.

O caminho óbvio seria SMTP — é o que o `.env` de desenvolvimento usa (Mailtrap sandbox) e o que o
próprio README recomendava para produção. **Esse caminho não existe no Railway.**

### O que se aprendeu no `build-os-studio`

O projeto irmão, hospedado no mesmo Railway, tentou SMTP primeiro e quebrou em produção. O Railway
libera SMTP apenas no plano Pro e, nos demais, **descarta a conexão em silêncio**: não recusa, não
devolve erro — pendura. O sintoma foi um `POST` travado até o timeout do servidor, sem nada no log
que apontasse o e-mail como culpado. O diagnóstico só apareceu porque alguém entrou no container.

Aqui o estrago seria pior de esconder: a fila é `sync` (ADR-0016 enxugou o deploy para um serviço só,
sem worker), então o envio acontece **dentro do `POST /esqueci-senha`**, num container que ainda pode
estar acordando do App Sleeping.

### Alternativas consideradas

1. **SMTP de algum provedor** — bloqueado pelo Railway, como acima. Descartado por impossibilidade,
   não por preferência.
2. **Subir um worker para tirar o envio do request** — não resolve o bloqueio de SMTP (o worker roda
   no mesmo Railway) e custa dinheiro e App Sleeping para um e-mail só.
3. **Amazon SES** — funcionaria pela API e o `aws-sdk-php` já vem no projeto (via flysystem-s3), mas
   exige sair do sandbox por ticket e o painel é desproporcional para uma mensagem.
4. **API HTTPS do Resend** — porta 443, atravessa o bloqueio, o Laravel já traz o transporte
   (`MailManager::createResendTransport`) e o `config/mail.php`/`config/services.php` do projeto já
   tinham as entradas do driver. É a mesma escolha do `build-os-studio`, o que faz um provedor servir
   os dois projetos.

## Decisão

**Enviar pela API HTTPS do Resend em produção; manter `log` em desenvolvimento e `array` nos testes.**

1. **Uma linha de dependência.** `resend/resend-php` no `composer.json` — o transporte é do próprio
   framework, nada de SDK no código da aplicação. `config/mail.php` e `config/services.php` não
   mudaram: o bloco `'resend'` e a chave `RESEND_API_KEY` já estavam lá.

2. **Configuração é variável de ambiente, não código.** Em produção: `MAIL_MAILER=resend`,
   `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`. Os segredos vivem só no Railway — o
   repositório é aberto. Consequência prática: **desligar é `MAIL_MAILER=log`**, sem deploy.

3. **O domínio remetente é verificado no Resend** (DKIM, SPF e MX de return-path publicados no DNS).
   Não se envia em nome de `*.up.railway.app`: não há como publicar os registros num subdomínio que
   não é nosso.

4. **Falha de envio não pode revelar quem tem cadastro.** `EsqueciSenhaController` passa a capturar a
   exceção do envio, registrar no log e devolver **a mesma mensagem genérica**. A razão está no
   assimétrico: para um endereço desconhecido o broker devolve `INVALID_USER` sem tentar enviar nada,
   então **toda** falha de transporte acontece para quem existe. Deixar a exceção subir para o
   `tratarErro` transformaria "provedor fora do ar" num oráculo de cadastro — o vazamento que a
   mensagem genérica existe para evitar, só que pelo avesso. Coberto por
   `RecuperacaoSenhaTest::test_falha_no_envio_nao_revela_que_o_email_existe`.

5. **A ordem de publicação importa.** As variáveis só podem ser trocadas **depois** do deploy que traz
   o pacote; setar `MAIL_MAILER=resend` antes derruba a recuperação de senha com
   `Class "Resend\Client" not found`.

## Consequências

### Positivas

- O fluxo de recuperação de senha passa a existir de verdade — antes, esquecer a senha era perder a
  conta.
- Envio em centenas de milissegundos, aceitável dentro do request mesmo com a fila em `sync`.
- Rollback instantâneo e sem deploy (`MAIL_MAILER=log`), no mesmo espírito do desligamento do
  Turnstile por variável vazia.
- Painel do provedor mostra entregue/bounce/spam — hoje não há observabilidade nenhuma de e-mail.
- Fecha uma assimetria de segurança que estava dormindo porque o driver `log` nunca falhava.

### Negativas

- **Uma dependência a mais** e um serviço externo no caminho de um fluxo de conta.
- O envio continua **dentro do request**: se o Resend estiver lento, quem pediu o link paga a espera.
  Subir um `queue:work` resolveria e fica registrado como opção, não como pendência.
- A falha de envio agora é **silenciosa para o usuário** — ele lê "enviaremos em instantes" mesmo
  quando não foi enviado. É o preço da não-enumeração; o erro real fica em `railway logs`.
- O plano gratuito do Resend limita a 100 e-mails/dia e 3.000/mês. Suficiente para demonstração,
  insuficiente para operação real.

### Neutras

- Desenvolvimento continua em `log` (ou Mailtrap sandbox por SMTP, que funciona fora do Railway) e o
  CI continua sem tocar em rede: `phpunit.xml` força `MAIL_MAILER=array`.
- O domínio remetente não é `meunegocio.*`. Enquanto a aplicação vive num subdomínio do Railway, o
  remetente vem de um domínio já verificado — trocar depois é mudar uma variável.
- `APP_URL` passa a ser **crítico** e não só cosmético: o cabeçalho do e-mail usa `config('app.url')`
  direto, sem passar pelo `trustProxies`. Errado ali, o link da marca aponta para `localhost` na
  caixa de entrada de quem recebeu.

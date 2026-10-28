# ADR-0022 — Verificação de e-mail no cadastro e 2FA por app autenticador

## Status

Aceito — outubro/2026. Entrega o item 5 do roadmap ("2FA + verificação de e-mail no registro") em
dois PRs: primeiro a verificação, depois o segundo fator.

## Contexto

Até aqui, qualquer endereço digitado no `/registrar` virava uma rede funcionando, sem prova de que
alguém do outro lado recebia e-mail ali. Isso tem dois custos concretos: a recuperação de senha
(ADR-0020) depende de um endereço que nunca foi provado, e um erro de digitação no cadastro tranca
o dono fora da própria conta na primeira vez que ele esquecer a senha.

O login tinha um fator só. O sistema guarda faturamento, caixa e a carteira de clientes de pequenos
negócios — vazar uma senha reaproveitada expõe tudo isso.

## Decisão

### Verificação de e-mail

- `Usuario` implementa `MustVerifyEmail`; a coluna `usuarios.email_verified_at` nasce com a
  migration, que **marca como verificados todos os usuários existentes** — a regra vale para
  cadastros novos, e trancar a base atual no deploy seria um apagão, não uma feature.
- **Bloqueio total até verificar.** O middleware `verified:verificacao.aviso` fica no grupo
  autenticado inteiro (`auth` → `verified` → `verificar.rede` → ...). Quem não confirmou só alcança a
  tela de confirmação, o reenvio, o link e o logout. O parâmetro do middleware existe para manter
  as rotas em português (`verificacao.*`) em vez do `verification.notice` padrão.
- **Todo usuário novo verifica**, inclusive o criado pelo Admin. O envio mora no
  `UsuarioService::criar`, não na `CriarUsuarioAction`, porque a Action também serve o registro e
  os seeders — que não devem disparar e-mail.
- **Trocar o e-mail derruba a verificação** (`Usuario::revalidarEmailSeAlterado`), no Meu Perfil e na
  edição pelo Admin: o endereço novo nunca foi provado.
- O link é `URL::temporarySignedRoute` com validade de 60 minutos e `sha1` do e-mail **atual** —
  link de um endereço anterior não confirma o novo. Exige estar logado (padrão do Laravel).
- **Falha de envio não derruba o cadastro.** `enviarVerificacaoSemFalhar` loga e segue; o usuário
  pede outro link na tela. Diferente do esqueci-senha (ADR-0020), aqui não há oráculo de cadastro a
  proteger — quem chega na tela já está logado —, então a mensagem pode ser direta.
- Envio síncrono via Mailable com o branding do projeto, no mesmo molde do reset de senha. Em
  produção a fila é `sync` (ADR-0016), então enfileirar não mudaria nada.

### 2FA (TOTP)

- **App autenticador (TOTP, RFC 6238)** com QR code e códigos de recuperação. Código por e-mail foi
  descartado: cada login viraria um envio pelo Resend, refém da entrega, e o segundo fator ficaria
  na mesma caixa que já recupera a senha — não seria um fator independente.
- **Opcional por usuário, exigível pela rede.** O Admin liga `redes.exigir_2fa`; a partir daí,
  quem não configurou é levado à aba Segurança do Meu Perfil antes de usar o sistema.
- `pragmarx/google2fa` + `bacon/bacon-qr-code` (QR em SVG inline, sem serviço externo), em vez do
  Laravel Fortify: o Fortify traz rotas, controllers e views próprios que brigariam com o padrão
  modular e com as telas Duralux; aqui só precisamos do algoritmo.
- Segredo e códigos de recuperação **criptografados no banco** (casts `encrypted`), fora do
  `toArray()` e do activitylog. Código TOTP usado uma vez não vale de novo (anti-replay).
- Disponível em **todos os planos**: segurança não fica atrás de pagamento.
- **Ligar o 2FA derruba as outras sessões.** A confirmação pede o código e a **senha**. A senha
  alimenta o `Auth::logoutOtherDevices`, e o middleware `auth.session`, no grupo autenticado, derruba
  toda sessão cujo hash de senha não bate mais. A sessão atual continua. Sessões abertas só com a
  senha (outro computador, aparelho perdido) não sobrevivem à ativação. Efeito colateral desejado:
  qualquer troca de senha também derruba os outros aparelhos.
- **2FA de Admin só o suporte redefine.** O Admin redefine pela tela o 2FA de quem não é Admin
  (celular perdido). O de outro Admin, não: junto com trocar e-mail e senha, seria tomar a conta de
  quem manda na rede. O suporte (operador do SaaS) usa `php artisan usuarios:redefinir-2fa {email}
  --motivo="..."`, com confirmação, e o motivo vai para a auditoria.
- **Limites, revisados no code review.** O desafio tem um **teto de 5 erros por pendência**, e
  estourar devolve ao login. Essa é a proteção contra força bruta distribuída: o throttle por IP
  sozinho se renovava a cada IP novo. Configurar, gerar códigos e desativar pedem a senha e têm
  balde próprio de throttle (prefixo). O `throttle:N,1` numérico do Laravel usa só usuário/IP como
  chave, então rotas diferentes somavam juntas, e um erro de digitação na configuração já dava 429.
  Verificar e consumir código acontece com a linha do usuário travada (`lockForUpdate`), para que
  duas requisições simultâneas não aceitem o mesmo código.

## Consequências

- **Positivas**: endereço provado antes de a conta existir de fato; recuperação de senha passa a
  ter um destino confiável; contas de Admin podem ter um segundo fator real.
- **Negativas**: um passo a mais no cadastro, e o Admin que cria um funcionário precisa avisá-lo
  para confirmar o e-mail. Se o provedor de e-mail cair, cadastros novos ficam parados na tela de
  confirmação até o envio voltar.
- **Neutras**: seeders, factory e `CriaTenant` passam a criar usuários já verificados; quem escreve
  teste de usuário não verificado usa `UsuarioFactory::new()->naoVerificado()` ou zera a coluna.

## Alternativas consideradas

1. **Acesso com aviso em vez de bloqueio** — deixaria contas com e-mail errado operando por tempo
   indeterminado, que é exatamente o problema.
2. **Prazo de carência** — mais complexo (comando agendado, estados intermediários) para um ganho
   pequeno, já que o link chega em segundos.
3. **Verificar só o registro público** — o funcionário criado pelo Admin também recupera senha pelo
   e-mail; deixá-lo de fora repetiria o problema dentro da rede.

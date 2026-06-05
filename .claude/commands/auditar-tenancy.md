---
description: Audita isolamento multi-tenant/multi-empresa do codigo (read-only) via subagente.
argument-hint: "[modulo ou caminho; vazio = diff atual da branch]"
---

Acione o subagente **tenancy-security-reviewer** (read-only) para auditar o isolamento de tenancy.

- Escopo: `$ARGUMENTS` se fornecido (ex.: `app/Modules/Pagamento`); caso contrario, o diff atual da
  branch (`git diff` / `git diff --stat`).
- Repasse o relatorio do agente integralmente: achados por severidade (Critico/Importante/Sugestao),
  com `arquivo:linha`, cenario de vazamento e correcao recomendada, alem do que foi verificado e esta OK.

Nao aplique correcoes automaticamente — primeiro apresente os achados ao usuario.

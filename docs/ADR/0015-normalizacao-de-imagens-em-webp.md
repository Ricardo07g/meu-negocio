# ADR-0015 — Normalização de imagens em WebP na gravação

## Status

Aceito — agosto/2026. **Complementa o ADR-0008** (que decidiu *onde* e *como* os arquivos são armazenados) decidindo *em que formato* as imagens entram no bucket. Não altera o schema da tabela `arquivos`, a convenção de path, o staging nem a autorização.

## Contexto

O sistema **declarava** suporte a `.webp` em todas as camadas de validação — `config/arquivos.php`, as coleções `avatar`/`galeria` dos quatro models (Produto, Cliente, Serviço, Usuário), os `SalvarXxxRequest` e o `REGRAS_IMAGEM` do `ProdutoArquivoController` — e o front (`campo-imagem.js`) inclusive **produzia** WebP: o cropper propagava o MIME do arquivo escolhido para o `canvas.toBlob()`.

Só que o **GD do container foi compilado sem WebP**. Os dois Dockerfiles (dev `php:8.3-fpm` e produção `php:8.3-cli`, usado pelo Railway) configuravam a extensão com `--with-freetype --with-jpeg` e nada mais, e nenhum deles instalava `libwebp-dev`. Verificação em tempo real no container: `gd_info()['WebP Support'] => false`, `imagewebp()` e `imagecreatefromwebp()` inexistentes, `imagick` não instalado.

Consequência prática: ao subir um `.webp`, o `ArquivoService::gravarArquivo()` chamava `decode()` e `encodeUsingFileExtension('webp')` para gerar a miniatura e estourava com `Error: Call to undefined function ...imagewebp()` — um **HTTP 500**, não uma mensagem de validação. Nenhum teste cobria o caso: toda a suíte de `tests/Feature/Arquivo/` usava apenas `UploadedFile::fake()->image('*.jpg')`.

Ao abrir o problema, dois defeitos vizinhos apareceram no mesmo método:

1. **O original era gravado intacto** (`putFileAs`), sem recompressão e **sem teto de dimensão**. Uma foto de 12 MP de celular entrava inteira no bucket e era servida no `show` via `imagem_url`.
2. **Qualquer falha na miniatura derrubava o upload inteiro**, porque a geração acontecia depois do `putFileAs` e sem tratamento — deixando ainda um original órfão no bucket.

Restrições que moldaram a decisão: o bucket R2 é pago por volume e compartilhado entre sistemas; o projeto já tem `intervention/image` ^4.1 com driver GD (entrou no ADR-0008 só para miniaturas); e existe acervo em produção gravado em jpg/png.

## Decisão

**Toda imagem convertível é reencodada em WebP no momento da gravação — original e miniatura — e o GD passa a ser compilado com suporte a WebP.**

1. **`--with-webp` nos dois Dockerfiles** (`docker/php/Dockerfile` e `Dockerfile`), com `libwebp-dev` no `apt-get`. AVIF ficou de fora: o ganho adicional não paga a lib extra hoje. Dev e produção precisam andar juntos — são builds independentes com o mesmo conjunto de extensões.

2. **Conversão centralizada em `ArquivoService::gravarArquivo()`**, o único ponto de I/O por onde passam `armazenar()` e `armazenarRascunho()`. Isso cobre galeria de Produto e avatares de Cliente/Serviço/Usuário de uma vez, sem tocar em Controller, Request, DTO ou Policy.

3. **Só `image/jpeg`, `image/png` e `image/webp` são convertidos** (`MIMES_CONVERSIVEIS`). **GIF fica de fora de propósito**: o GD achata a animação no primeiro quadro. PDF e demais tipos seguem o caminho "como enviado", inalterado.

4. **O original também é reduzido**, a `arquivos.imagem.largura_maxima` (1600px por padrão) — o que vai para o bucket é o que a tela serve. Qualidade e teto são configuráveis (`ARQUIVOS_WEBP_QUALIDADE`, `ARQUIVOS_LARGURA_MAXIMA`), e a conversão inteira pode ser desligada com `ARQUIVOS_CONVERTER_WEBP=false`.

5. **Metadados descrevem o objeto gravado, não o upload.** `tamanho` passa a ser o byte-count do conteúdo escrito e `hash` o SHA-256 desse mesmo conteúdo (antes era `hash_file` do arquivo original, que após a conversão não corresponderia a nada no bucket). `largura`/`altura` são capturados depois do `scaleDown`. `nome_original` continua sendo o nome que o usuário enviou — é rótulo de UI, não identidade do objeto.

6. **Nada é gravado antes de o `decode()` dar certo**: um upload corrompido vira `NegocioException` com mensagem amigável e não deixa órfão. No caminho não-convertido, a miniatura passa a ser **best-effort** — falhou, loga `warning` e segue com `caminho_thumb = null`, e o accessor `thumb_url` cai no próprio original (comportamento que o Model já tinha).

7. **Degradação em vez de explosão**: `deveConverterParaWebp()` exige `function_exists('imagewebp')`. Numa instalação sem o GD correto, o upload cai no caminho "como enviado" em vez de 500 — o bug original fica impossível de reproduzir mesmo com o Dockerfile errado.

8. **O front deixa de propagar o formato de entrada.** O cropper emite sempre WebP (com detecção de suporte e fallback para JPEG), e o `accept` da galeria (`produto-imagens.js`) passa de `image/*` — mais frouxo que o backend — para a lista real aceita.

**Migração: nenhuma.** O acervo antigo em jpg/png **não é reconvertido**. Cada registro carrega seu próprio `caminho`/`extensao`/`mime`, então os dois formatos convivem indefinidamente; arquivos novos nascem WebP.

## Consequências

### Positivas
- **`.webp` finalmente funciona** — o formato que o sistema já dizia aceitar, e que o próprio cropper gerava, deixa de dar 500.
- **Bucket e banda menores**: WebP a q82 fica tipicamente 25-35% abaixo do JPEG equivalente, e o teto de 1600px corta o caso patológico da foto de 12 MP. O R2 é pago por volume.
- **Um formato só para manter** nas imagens novas — sem matriz de casos por extensão.
- **Upload deixa de ser frágil**: miniatura virou best-effort e a falha de decode virou erro de negócio, com o bucket sempre consistente.
- **Um só ponto de mudança** cobriu os quatro models, porque o ADR-0008 já havia centralizado a I/O.

### Negativas
- **Acoplamento ao build da imagem.** A feature depende de um GD compilado com `--with-webp`; subir o código sem rebuildar a imagem apenas desliga a conversão (silenciosamente, pelo item 7). Em produção isso é resolvido pelo deploy por git do Railway, que sempre refaz o build — mas é uma dependência fora do código.
- **Reencode é lossy sobre lossy.** Um JPEG já comprimido pela câmera é decodificado e reencodado; a q82 a perda é imperceptível na prática, mas é real e irreversível — o original não é guardado.
- **CPU no request.** O decode + dois encodes acontecem síncronos no upload. Aceitável no volume atual (imagens pequenas, uploads esporádicos); se virar gargalo, o caminho é mover para a fila, não baixar a qualidade.
- **Acervo heterogêneo.** Por um bom tempo o bucket terá jpg/png antigos e webp novos. É invisível para quem usa (as URLs vêm do registro), mas qualquer script que assuma extensão única vai errar.

### Neutras
- O driver segue sendo **GD**, não Imagick — a decisão do ADR-0008 continua valendo, agora com uma flag de compilação a mais.
- `strip` de EXIF não foi ativado explicitamente; o GD não carrega metadados adiante de qualquer forma.
- Os limites de `mimes` por coleção não mudaram: continuam validando a **extensão informada pelo cliente** no `ArquivoService::validar()`. As Requests HTTP cobrem isso com `mimes:`, mas chamadas diretas ao service (jobs, seeders) seguem sem essa rede — anotado, fora do escopo deste ADR.

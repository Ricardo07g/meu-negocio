<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Arquivo\Services\ArquivoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Preserva a imagem enviada quando o formulario volta com erro de validacao.
 *
 * Arquivo nao sobrevive ao `withInput()`: UploadedFile nao e serializavel para a
 * sessao e o temporario do PHP morre no fim do request. Entao, quando a validacao
 * falha, o upload e estacionado na area de rascunho ({sistema}/tmp/{token}/) e o
 * caminho segue junto do old input. O componente <x-campo-imagem> reidrata o
 * preview e o controller promove o arquivo no proximo submit (ver a trait
 * SincronizaImagemUnica).
 *
 * Por que uma response customizada e nao um `merge()`: `Request::createFrom()`
 * COPIA o input bag para o FormRequest, entao mexer nele aqui nao chega ao
 * `Handler::invalid()`, que le o Request original. Ja `ValidationException::$response`,
 * quando presente, e devolvido direto pelo handler.
 */
trait PreservaImagemRascunho
{
    /**
     * Campos de imagem unica preservados por este request.
     *
     * @return list<string>
     */
    protected function camposImagemPreservados(): array
    {
        return ['foto'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            redirect($this->getRedirectUrl())
                ->withInput(array_merge(
                    // Mesma lista de `dontFlash` do handler padrao do Laravel.
                    Arr::except($this->input(), ['current_password', 'password', 'password_confirmation']),
                    $this->estacionarImagens($validator),
                ))
                ->withErrors($validator->errors(), $this->errorBag),
        );
    }

    /**
     * Estaciona os uploads validos e devolve os caminhos para o old input.
     *
     * @return array<string, string>
     */
    private function estacionarImagens(Validator $validator): array
    {
        $estacionados = [];

        foreach ($this->camposImagemPreservados() as $campo) {
            $arquivo = $this->file($campo);

            // Imagem grande demais ou de tipo errado nao vira rascunho: o erro e
            // dela, e reaproveita-la so repetiria a recusa no proximo submit.
            if (! $arquivo instanceof UploadedFile || $validator->errors()->has($campo)) {
                continue;
            }

            try {
                $servico = app(ArquivoService::class);
                $token = $this->tokenDeRascunho();
                $anterior = trim((string) $this->input("{$campo}_rascunho", ''));

                $estacionados["{$campo}_rascunho"] = $servico->armazenarRascunho($arquivo, $token)['caminho'];

                // Segunda tentativa com outra imagem: o rascunho antigo nao serve
                // mais e so viraria lixo ate o TTL.
                if ($anterior !== '') {
                    $servico->removerRascunho($token, $anterior);
                }
            } catch (\Throwable $e) {
                // Preservar a imagem e conveniencia. Se o staging falhar, o
                // usuario ainda ve os erros do form — so precisa reenviar a foto.
                Log::warning('Nao foi possivel estacionar a imagem do formulario.', [
                    'campo' => $campo,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $estacionados;
    }

    /**
     * Token da sessao, criado na primeira necessidade. Reaproveitado entre
     * tentativas para que todos os rascunhos do usuario vivam no mesmo prefixo.
     */
    private function tokenDeRascunho(): string
    {
        $token = (string) $this->session()->get(ArquivoService::SESSAO_TOKEN_UNICO, '');

        if ($token === '') {
            $token = (string) Str::uuid();
            $this->session()->put(ArquivoService::SESSAO_TOKEN_UNICO, $token);
        }

        return $token;
    }
}

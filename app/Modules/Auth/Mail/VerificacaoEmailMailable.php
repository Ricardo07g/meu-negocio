<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class VerificacaoEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $urlVerificacao,
        public string $nome,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu e-mail — Meu Negócio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'auth::emails.verificacao-email',
            with: [
                'urlVerificacao' => $this->urlVerificacao,
                'nome' => $this->nome,
            ],
        );
    }
}

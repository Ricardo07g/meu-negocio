<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class RecuperacaoSenhaMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Redefinição de senha — Meu Negócio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'auth::emails.recuperacao-senha',
            with: [
                'urlRedefinicao' => route('senha.redefinir.form', [
                    'token' => $this->token,
                    'email' => $this->email,
                ]),
            ],
        );
    }
}

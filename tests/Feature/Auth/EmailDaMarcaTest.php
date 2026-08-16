<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Mail\RecuperacaoSenhaMailable;
use Tests\TestCase;

/**
 * A identidade dos emails do sistema.
 *
 * O tema `meu-negocio` vale para TODO email — hoje só existe o de recuperação
 * de senha, mas quem vier depois (lembrete de agendamento, aviso de fatura, fim
 * do teste grátis) herda daqui. Por isso o teste mira no que é do tema, não no
 * texto desta mensagem específica.
 *
 * Cada asserção abaixo trava um defeito que existiu de verdade.
 */
class EmailDaMarcaTest extends TestCase
{
    private function html(): string
    {
        return (new RecuperacaoSenhaMailable('token-de-teste', 'cliente@exemplo.com'))->render();
    }

    /** O rodapé vinha do framework, em inglês, num produto todo em português. */
    public function test_rodape_esta_em_portugues(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Todos os direitos reservados', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }

    /**
     * `logo-abbr.png` e `logo-full.png` são o logo do template **Duralux** — um
     * "D" e a palavra DURALUX. Mandar a marca de um template de terceiro no
     * email transacional do produto é erro que só aparece na caixa do cliente.
     */
    public function test_usa_a_marca_do_produto_e_nao_a_do_template(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('marca-mn.png', $html);
        $this->assertStringNotContainsString('logo-abbr.png', $html);
        $this->assertStringNotContainsString('logo-full.png', $html);
    }

    /**
     * Regressão de CSS: uma regra em `.inner-body a` tem especificidade maior
     * que `.button` e pintava o texto do botão de azul sobre fundo azul —
     * ilegível, e invisível para qualquer teste que só olhasse o HTML.
     */
    public function test_botao_tem_texto_branco_sobre_o_azul_da_marca(): void
    {
        $html = $this->html();

        preg_match('/<a[^>]*class="button[^"]*"[^>]*style="([^"]*)"/', $html, $m);

        $this->assertNotEmpty($m, 'O botão precisa existir com estilo inline (o email inlina o CSS).');
        $this->assertStringContainsString('background-color: #3f5fe0', $m[1], 'Botão fora da cor da marca.');
        $this->assertStringContainsString('color: #fff', $m[1], 'Texto do botão precisa contrastar com o fundo.');
    }

    /** Imagem bloqueada é o padrão em muitos clientes: o nome tem de aparecer assim mesmo. */
    public function test_marca_aparece_em_texto_e_nao_so_na_imagem(): void
    {
        $this->assertStringContainsString('Meu Negócio', strip_tags($this->html()));
    }

    /** Sem essa linha, email transacional vira suspeito para filtro e para leitor. */
    public function test_rodape_explica_por_que_a_pessoa_recebeu(): void
    {
        $this->assertStringContainsString('porque tem uma conta', $this->html());
    }

    /** O botão pode não funcionar no cliente de email; o link cru precisa estar visível. */
    public function test_traz_o_link_em_texto_alem_do_botao(): void
    {
        $html = $this->html();
        $url = route('senha.redefinir.form', ['token' => 'token-de-teste', 'email' => 'cliente@exemplo.com']);

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(html_entity_decode($html), $url),
            'O endereço precisa aparecer no botão E em texto copiável.'
        );
    }
}

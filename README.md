# Criador de Ambientes AI – Alavanca360

Plugin WordPress para gerar fundos de luxo em imagens de produtos usando a API PhotoRoom. Este repositório contém um exemplo simples do plugin descrito no documento "Criador de Ambientes AI".

## Como usar

1. Copie a pasta `luxury-background-enhancer` para a pasta `wp-content/plugins` do seu site WordPress.
2. Ative o plugin no painel de administração.
3. Acesse `Configurações → Criador de Ambientes AI` para informar sua chave de API da PhotoRoom. Se precisar de uma chave, <a href="https://photoroom.com/api" target="_blank">cadastre-se aqui</a>.
4. Ao editar um produto no WooCommerce, utilize a caixa "Criador de Ambientes AI" para gerar novos fundos.
5. Use o botão **Fix Images** para redimensionar a imagem destacada do produto para 1024×423 caso ela esteja em tamanho diferente.

O plugin verificará se a imagem possui fundo branco, enviará a imagem para a API PhotoRoom e definirá a nova imagem como imagem destacada.

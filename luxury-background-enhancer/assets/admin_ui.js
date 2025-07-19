jQuery(document).ready(function($) {
  $('#luxbg-generate').on('click', function(e) {
    e.preventDefault();

    const product_id = $('#post_ID').val();
    const style = $('#luxbg_style').val();
    const status = $('#luxbg-status');

    status.text('Gerando imagem com fundo luxuoso...').css('color', '#666');

    $.ajax({
      url: luxbg_ajax.ajax_url,
      method: 'POST',
      data: {
        action: 'luxbg_generate_background',
        nonce: luxbg_ajax.nonce,
        product_id: product_id,
        style: style
      },
      success: function(response) {
        if (response.success) {
          status.html('<strong style="color:green">Imagem gerada com sucesso!</strong>');
        } else {
          status.html('<strong style="color:red">Erro: ' + response.data + '</strong>');
        }
      },
      error: function(xhr, statusText, errorThrown) {
        status.html('<strong style="color:red">Erro inesperado: ' + errorThrown + '</strong>');
      }
    });
  });
});

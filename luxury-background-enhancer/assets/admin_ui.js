jQuery(document).ready(function($){
  $('#luxbg-test-api').on('click', function(e){
    e.preventDefault();
    var result = $('#luxbg-test-result');
    result.text('Testando...');
    $.post(luxbg_admin_ui.ajax_url, {
      action: 'luxbg_test_api',
      _ajax_nonce: luxbg_admin_ui.test_nonce
    }).done(function(resp){
      if(resp && resp.success){
        result.text('✅ Conexão bem-sucedida');
      }else{
        result.text('❌ Erro: ' + (resp && resp.data ? resp.data : 'desconhecido'));
      }
    }).fail(function(){
      result.text('❌ Erro inesperado');
    });
  });
});

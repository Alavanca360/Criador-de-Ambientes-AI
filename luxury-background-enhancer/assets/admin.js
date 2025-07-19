(function($){
    $('#luxbg-generate-button').on('click', function(e){
        e.preventDefault();
        var data = {
            action: 'luxbg_generate_ajax',
            post_id: $(this).data('post'),
            prompt: $('#luxbg_prompt').val(),
            style: $('#luxbg_style').val(),
            _ajax_nonce: luxbg_ajax.nonce
        };
        $(this).attr('disabled', true);
        $.post(luxbg_ajax.url, data, function(response){
            if(response && response.success){
                window.location.reload();
            }else{
                alert(response.data || 'Erro na geração');
                $('#luxbg-generate-button').attr('disabled', false);
            }
        });
    });
})(jQuery);

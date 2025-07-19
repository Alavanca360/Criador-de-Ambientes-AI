<div class="wrap luxbg-settings-page">
    <h1>Criador de Ambientes AI</h1>
    <p>Insira sua chave de API da PhotoRoom. Se ainda não possui, <a href="https://photoroom.com/api" target="_blank">clique aqui para gerar a sua</a>.</p>
<div class="wrap">
    <h2>Criador de Ambientes AI</h2>
    <form method="post" action="options.php">
        <?php settings_fields( 'luxbg_settings' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="luxbg_api_key">PhotoRoom API Key</label></th>
                <td><input type="text" name="luxbg_api_key" id="luxbg_api_key" value="<?php echo esc_attr( get_option( 'luxbg_api_key' ) ); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php submit_button( 'Salvar', 'primary luxbg-green' ); ?>
        <?php submit_button(); ?>
    </form>
    <h2>Logs de Erro Recentes</h2>
    <?php if ( ! empty( $logs ) ) : ?>
    <table class="widefat">
        <thead>
            <tr>
                <th>Data</th>
                <th>Contexto</th>
                <th>Mensagem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( array_reverse( $logs ) as $log ) : ?>
            <tr>
                <td><?php echo esc_html( $log['time'] ); ?></td>
                <td><?php echo esc_html( $log['context'] ); ?></td>
                <td><?php echo esc_html( $log['message'] ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p>Nenhum erro registrado.</p>
    <?php endif; ?>
</div>
</div>

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
        <?php submit_button(); ?>
    </form>
</div>

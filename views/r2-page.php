<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded
// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var mixed[] $view
 */
?>

<h2>Cloudflare R2 Deployment Options</h2>

<h3>R2</h3>

<form
    name="wp2static-r2-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_r2_save_options" />

<table class="widefat striped">
    <tbody>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['bucket']->name; ?>"
                ><?php echo $view['options']['bucket']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['bucket']->name; ?>"
                    name="<?php echo $view['options']['bucket']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['bucket']->value !== '' ? $view['options']['r2Bucket']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['endpoint']->name; ?>"
                ><?php echo $view['options']['endpoint']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['endpoint']->name; ?>"
                    name="<?php echo $view['options']['endpoint']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['endpoint']->value !== '' ? $view['options']['endpoint']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['region']->name; ?>"
                ><?php echo $view['options']['region']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['region']->name; ?>"
                    name="<?php echo $view['options']['region']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['region']->value !== '' ? $view['options']['region']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['pathPrefix']->name; ?>"
                ><?php echo $view['options']['pathPrefix']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['pathPrefix']->name; ?>"
                    name="<?php echo $view['options']['pathPrefix']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['pathPrefix']->value !== '' ? $view['options']['pathPrefix']->value : ''; ?>"
                />
            </td>
        </tr>


        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['cfAccountId']->name; ?>"
                ><?php echo $view['options']['cfAccountId']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['cfAccountId']->name; ?>"
                    name="<?php echo $view['options']['cfAccountId']->name; ?>"
                    value="<?php echo $view['options']['cfAccountId']->value !== '' ? $view['options']['cfAccountId']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['cfApiKey']->name; ?>"
                ><?php echo $view['options']['cfApiKey']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['cfApiKey']->name; ?>"
                    name="<?php echo $view['options']['cfApiKey']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['cfApiKey']->value !== '' ?
                        \WP2Static\CoreOptions::encrypt_decrypt( 'decrypt', $view['options']['cfApiKey']->value ) :
                        ''; ?>"
                />
            </td>
        </tr>
    </tbody>
</table>


<br>

    <button class="button btn-primary">Save R2 Options</button>
</form>


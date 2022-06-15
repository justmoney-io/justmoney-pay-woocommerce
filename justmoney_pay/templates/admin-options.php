<img style="width:100%;max-width:1500px;" src="<?php echo plugin_dir_url(__DIR__)."/banner.jpg"; ?>"/>
<h3><?php _e( 'JustMoney Pay', 'woocommerce' ); ?></h3>
<p><?php _e( 'Crypto payment plugin by JustMoney. More info at <a href="https://pay.just.money">pay.just.money</a>.', 'woocommerce' ); ?></p>

<table class="form-table">
    <?php
    // Generate the HTML For the settings form.
    $this->generate_settings_html();
    ?>

    <tr valign="top">
        <th scope="row" class="titledesc">
            <label>Notes</label>
        </th>
        <td class="forminp">
           JustMoney Pay supports only USD as currency. Make sure your store currency is set to USD.
        </td>
    </tr>
</table><!--/.form-table-->

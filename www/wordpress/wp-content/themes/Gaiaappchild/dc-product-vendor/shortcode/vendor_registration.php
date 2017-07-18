<?php global $WCMp; ?>
<?php wc_print_notices(); ?>

<div class="section_1 container-fluid">
    <div class="container">
    <div class="row"><h2 class="title orange">REJOIGNEZ Mère nature</h2></div>
    <div class="row">
    <div class="col-md-6">
        <h3 class="title_inside">Offrez vos services à Mère Nature</h3>
        <ul class="check">
            <li>Vos plats livrés rapidement</li>
            <li>Votre activité boostée</li>
            <li>Un service professionnel</li>
        </ul>
    </div>
    <div class="col-md-6">
<div class="wcmp_regi_main">
    <form class="register" role="form" method="post" enctype="multipart/form-data">
        <h2 class="reg_header1">C'est parti</h2>

        <div class="wcmp_regi_form_box">
            <?php if(!is_user_logged_in()) : 
                $wcmp_vendor_general_settings_name = get_option('wcmp_vendor_general_settings_name');?>

            <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
                <div class="wcmp-regi-12">
                    <label for="reg_username"><?php _e('Username', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text"  name="username" id="reg_username" value="<?php if (!empty($_POST['username'])) echo esc_attr($_POST['username']); ?>" required="required" />
                </div>
            <?php endif; ?>
            <div class="wcmp-regi-12">
                <label for="reg_email"><?php _e('Email address', 'woocommerce'); ?> <span class="required">*</span></label>
                <input type="email" required="required"  name="email" id="reg_email" value="<?php if (!empty($_POST['email'])) echo esc_attr($_POST['email']); ?>" />
            </div>
            <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
                <div class="wcmp-regi-12">
                    <label for="reg_password"><?php _e('Password', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="password" required="required" name="password" id="reg_password" />
                </div>
            <?php endif; ?>
            <div style="<?php echo ( ( is_rtl() ) ? 'right' : 'left' ); ?>: -999em; position: absolute;"><label for="trap"><?php _e('Anti-spam', 'woocommerce'); ?></label><input type="text" name="email_2" id="trap" tabindex="-1" /></div>
            <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce');  ?>
            <?php endif; ?>
            <?php do_action('wcmp_vendor_register_form'); ?>
            <div class="clearboth"></div>
        </div>
        <?php //do_action('register_form'); ?> 
        <?php if(is_user_logged_in()){ echo '<input type="hidden" name="vendor_apply" />'; }  ?>
        <input type="hidden" value="true" name="pending_vendor" />
        <?php do_action( 'woocommerce_register_form' ); ?>
        <p class="woocomerce-FormRow form-row">
            <?php 
            $button_text = apply_filters('wcmp_vendor_registration_submit','Register');
            ?>
            <input type="submit" class="woocommerce-Button button" name="register" value="<?php esc_attr_e( $button_text, 'woocommerce' ); ?>" />
        </p>
        <?php do_action('woocommerce_register_form_end'); ?>
    </form>
</div>
</div>
    </div>
</div>
</div>
<div class="section_2">

<div class="container-fluid"><div class="row"><h2 class="title"> LE VEGAN SéDUIT NATURELELLEMENT LA FRANCE</h2></div></div>
    <div class="section_custom">
    <div class="container">
        <div class="row">
            <div class="col-md-4"><h4 class="pourcentage">43%</h4><p>des français souhaiteraient que les restaurants classique de type  restauration rapide proposent 1 ou 2 plats vegan à leur carte.</p></div>

            <div class="col-md-4"><h4 class="pourcentage">54%</h4><p>de français connaissent la tendance vegan et pensent qu'elle est acctuelle.</p></div>
            <div class="col-md-4"><h4 class="pourcentage">46%</h4><p>des français souhaiteraient que les restaurants classique de type restaurant à table proposent 1 ou 2  plats vegan à leur carte.</p></div>

        </div>
    </div>
    </div>
<div class="section_custom2">
    <div class="container">

    <div class="row">

        <div class="col-md-6 background_one"></div>

        <div class="col-md-6"><h3 class="little orange">Pourquoi Gaia ?</h3>

            <p>Un service professionel et exigenant, GAIA vous offre  l'opportunité de partager votre cuisine en toute sérennité. Proposez nous vos plats obtenez des commandes, nous nous occupons du reste ! </p>

        </div>


    </div>

    <div class="row">
        <div class="col-md-6"><h3 class="orange title_i"> << Gaia se consacre à  une nouvelle vision de la gastronomie >> </h3></div>

    <div class="col-md-6  background_dos"></div>
</div>
    </div>

</div>
</div>
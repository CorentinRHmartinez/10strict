<?php
/**
 * The template for displaying the header
 *
 * Displays all of the head element and everything up until the "site-content" div.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">


    <meta name="description" content="LE GOÛT DU NATUREL">
    <meta name="twitter:card" content="LE GOÛT DU NATUREL" />
    <meta name="twitter:site" content="@gaia_app" />
    <meta name="twitter:creator" content="@gaia_app" />
    <meta property="og:url" content="http://gaiaapp.fr/" />
    <meta property="og:type"               content="Application" />
    <meta property="og:title"              content="Gaia" />
    <meta property="og:description"        content="LE GOÛT DU NATUREL" />
    <meta property="og:image" content="http://gaiaapp.fr/img/logo-gaia.png" />
    <title>Gaia</title>
    <!--
    Favicons
    =============================================
    -->
    <link rel="apple-touch-icon" sizes="57x57" href="assets/favicons/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/favicons/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/favicons/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/favicons/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/favicons/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/favicons/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/favicons/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/favicons/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="assets/favicons/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicons/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16x16.png">

    <!--
    <!--Bootstrap CSS-->
    <link href="https://fonts.googleapis.com/css?family=Cantarell:400,700" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="wp-content/themes/Gaiaappchild/css/style.css">
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

        ga('create', 'UA-100903982-1', 'auto');
        ga('send', 'pageview');
    </script>
    <!--Hotjar Tracking Code for http://gaiaapp.fr/-->
    <script>
        (function(h,o,t,j,a,r){
            h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
            h._hjSettings={hjid:536663,hjsv:5};
            a=o.getElementsByTagName('head')[0];
            r=o.createElement('script');r.async=1;
            r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
            a.appendChild(r);
        })(window,document,'//static.hotjar.com/c/hotjar-','.js?sv=');
    </script>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">


	</div><!-- .sidebar -->



                <?php if ( is_front_page()) : ?>


<div id="content" class="site-content">
    <div class="container-fluid menu"><div class="row">
                <a href="http://gaiaapp.fr/" > <img id="logo" src="/img/logo-gaia.png" title="Logo Gaia"></a></div></div>
                <?php else : ?>
    <div id="content" class="site-content">
        <div class="container-fluid menuWow"><div class="row">
                <a href="http://gaiaapp.fr/" > <img id="logo" src="/img/logo-gaia.png" title="Logo Gaia"></a></div></div>
                <?php endif; ?>

                <div id="site-header-menu" class="site-header-menu">
						<?php if ( has_nav_menu( 'primary' ) ) : ?>
        <nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Primary Menu', 'twentysixteen' ); ?>">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'menu_class'     => 'primary-menu',
            ) );
            ?>
        </nav><!-- .main-navigation -->
<?php endif; ?>
    </div>

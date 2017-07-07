<?php
/**
 * The front page template file
 *
 * If the user has selected a static page for their homepage, this is what will
 * appear.
 * Learn more: https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.0
 */

get_header(); ?>
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

        ga('create', 'UA-100903982-1', 'auto');
        ga('send', 'pageview');
    </script>
    <!-- Hotjar Tracking Code for http://gaiaapp.fr/ -->
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

</head>
<body>
<div class="wow">
    <div class="container wow ">
        <a href="index.html" ><img id="logo" src="img/logo-gaia-b.png" title="logo gaiaapp" alt="gaia gaiaapp"></a>
        <div class="row legal align-items-center">
            <div class="col">
               <h2>Hebergement</h2>
                <ul>
                    <li>OVH - 40 Avenue de Flandre, 75019 Paris </li>
                    <li> Siret : 424 761 419 00045</li>
                </ul>
            </div>
            <div class="col">
                <h2>Crédits</h2>
                <ul>

                <li>images : <a href="https://unsplash.com/">https://unsplash.com</a></li>
                <li> Pictos : <a href="https://unsplash.com">https://thenounproject.com</a></li>

                </ul>
            </div>
            <div class="col">
                <h2>Editeur</h2>
                <ul>
                <li>Gaiaapp.fr </li>
                    <li><a href="mailto:hello@gaiaapp.fr">Contactez-nous !</a></li></ul>
            </div>
    </div>
    </div>
</body>
<?php get_footer();
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
    <div class="wrapper">
        <div class="container-fluid">




            <div class="row section1 align-items-center">



                <div class="col important_z text-center">
                    <h1 class="title"> LE GOÛT DU NATUREL <!--<span class="title2">AU SERVICE</span>  D'UNE AUTRE GASTRONOMIE--></h1>
                    <a  data-scroll  class="btn btn-theme cta-main" href="#découvrir" title="Service" >Découvrir<!--<i class="fa fa-angle-double-down" aria-hidden="true">--></i>  </a>
                </div>
            </div>
        </div></div>
            <div class="col-xs-12"><p class="text fff browntext">VOUS NE POURREZ PLUS VOUS EN PASSER !</p></div>


        <div id ="service" class="section4">

            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6 video col-sm-12">
                        <div class="iframe-responsive-wrapper">
                            <img class="iframe-ratio" src="data:image/gif;base64,R0lGODlhEAAJAIAAAP///wAAACH5BAEAAAAALAAAAAAQAAkAAAIKhI+py+0Po5yUFQA7">
                            <iframe src="https://player.vimeo.com/video/224475339"  style="width:100%;height:100%" frameBorder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                        </div>

                    </div>
                    <div class="col-md-6 col-sm-12">
                        <div class="col-xs-12"><p class="text" style="padding-top: 30px;">Votre boite  mail a besoin de fraîcheur ?</p>
                         <p class="text">Abonnez vous à la newsletter de Mère Nature !</p></div>
                        <div class="callout-btn-box">
                            <link href="//cdn-images.mailchimp.com/embedcode/horizontal-slim-10_7.css" rel="stylesheet" type="text/css">
                            <!-- <form id="subscription-form" role="form" method="post" action="php/subscribe.php">
                              <form id="subscription-form" role="form" method="post" action="//gaiaapp.us16.list-manage.com/subscribe/post?u=a63408779c9162b4af32a197e&amp;id=b4df4eab11">
                              <div class="input-group">
                                <input class="form-control" type="email" id="semail" name="b_a63408779c9162b4af32a197e_b4df4eab11" placeholder="Votre Email" data-validation-required-message="Si vous plait veuillez mettre vos email." required="required"/><span class="input-group-btn">
                                  <button class="btn btn-g btn-round" name="b_a63408779c9162b4af32a197e_b4df4eab11" id="subscription-form-submit" type="submit">Envoyer</button></span>
                              </div>
                            </form>-->
                            <!-- Begin MailChimp Signup Form -->
                            <link href="//cdn-images.mailchimp.com/embedcode/horizontal-slim-10_7.css" rel="stylesheet" type="text/css">
                            <style type="text/css">
                                #mc_embed_signup{clear:left; font:14px Helvetica,Arial,sans-serif; width:100%;}
                                #mc_embed_signup .button {
                                    font-size: 13px;

                                    letter-spacing: .03em;
                                    color: #FFF;
                                    background-color: #adadad;
                                    box-sizing: border-box;
                                    height: 42px;
                                    line-height: 32px;
                                    font-weight: 100;
                                    padding: 0 18px;
                                    border-radius: 50px;
                                    display: inline-block;
                                    margin: 0;
                                    width: 200px;
                                    transition: all 0.23s ease-in-out 0s;
                                }
                                #mc_embed_signup input.email {
                                    font-family: "Open Sans","Helvetica Neue",Arial,Helvetica,Verdana,sans-serif;
                                    font-size: 15px;
                                    border: rgba(255, 255, 255, 0);
                                    color: #ffffff;
                                    background-color: rgba(255, 255, 255, 0);
                                    box-sizing: border-box;
                                    height: 42px;
                                    border-size: 10px;
                                    border-bottom: 1px solid rgb(255, 255, 255);
                                    padding: 0px 0.4em;
                                    display: inline-block;

                                    margin: 0;
                                    width: 80%;
                                    vertical-align: top;
                                }
                                #mc_embed_signup .clear {
                                    display: inline-block;
                                    padding-top: 20px;
                                }

                                @media (max-width: 768px) {
                                    #mc_embed_signup input.email {width:100%; margin-bottom:5px;}
                                    #mc_embed_signup .clear {display: block; width: 100% }
                                    #mc_embed_signup .button {width: 100%; margin:0;margin-left: 0%; }
                                }

                                /* Add your own MailChimp form style overrides in your site stylesheet or in this style block.
                                We recommend moving this block and the preceding CSS link to the HEAD of your HTML file. */
                            </style>
                            <div id="mc_embed_signup">
                                <form action="//gaiaapp.us16.list-manage.com/subscribe/post?u=a63408779c9162b4af32a197e&amp;id=b4df4eab11" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
                                    <div id="mc_embed_signup_scroll">
                                        <label for="mce-EMAIL"></label>
                                        <input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="Votre adresse Email" required>
                                        <!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
                                        <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_a63408779c9162b4af32a197e_b4df4eab11" tabindex="-1" value=""> </div>
                                        <div class="clear"><input style="background-color: #c22546;" type="submit" value="JE M'INSCRIS !" name="subscribe" id="mc-embedded-subscribe" class="button"></div>
                                    </div>
                                </form>
                            </div>
                            <!--End mc_embed_signup-->
                            <div class="text-center" id="subscription-response"></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="découvrir" class="section2">
            <h2 class="browntext" style="text-align: center">C'EST AUSSI SIMPLE QUE ÇA !</h2>
            <div class="container-fluid">
                <div class="row">
                    <div  class="col-md-4  col-sm-12">
                        <div id="sec_1" class="action"><div class="img-prodtuit"><img src="wordpress/wp-content/themes/Gaiaappchild/img/bowl.png"></div><div class="same-height content-produit"><h2>DES PLATS VEGANS FAITS MAISON !</h2><p>Choisissez parmi les recettes de notre brigade de chefs professionnels ou amateurs et dégustez des plats gourmands et colorés.</p></div>
                        </div></div>
                    <div  class="col-md-4 col-sm-12">
                        <div id="sec_2" class="action">
                            <div class="img-prodtuit"><img src="wordpress/wp-content/themes/Gaiaappchild/img/click.png"    ></div><div class="same-height content-produit"><h2>COMMANDEZ EN 3 CLICS !</h2><p>Simplicité, rapidité, efficacité… Une expérience optimale pour commander votre déjeuner ou votre dîner.</p></div></div>
                    </div><div  class="col-md-4  col-sm-12">
                        <div id="sec_3" class="action">
                            <div class="img-prodtuit"><img src="wordpress/wp-content/themes/Gaiaappchild/img/coupe.png"></div><div class="same-height content-produit"><h2>FAITES VOUS LIVRER EN 30 MINUTES !</h2><p>Recevez votre commande dans la demi-heure et suivez son avancée en temps réel</p></div></div>

                    </div></div>
            </div>
        </div>
        <div class="section3">
            <h2 class="browntext" style="text-align: center; padding-bottom: 30px;">EN ATTENDANT DÉCOUVREZ NOTRE SÉLECTION DE RESTAURANTS</h2>
            <div class="container-fluid">
                <div class="row">
                    <div  class="col-md-4  col-sm-12">
                        <div id="img-1" class="action2">

                            <div class="commande">Café pinson</div>
                            <a class="btn btn-theme cta-main flex_end btn-wait" href="http://www.cafepinson.fr" onclick="window.open(this.href); return false;" title="Service" >Allons-y !<!--<i class="fa fa-angle-double-down" aria-hidden="true">--></i></a>
                        </div>
                    </div>
                    <div  class="col-md-4 col-sm-12">
                        <div id="img-2" class="action2">

                            <div class="commande">Caju vegan</div>
                            <a class="btn btn-theme cta-main btn-wait" href="http://www.cajuvegan.fr" onclick="window.open(this.href); return false;" title="Service" >Allons-y !<!--<i class="fa fa-angle-double-down" aria-hidden="true">--></i>  </a>
                        </div>
                    </div>

                    <div  class="col-md-4  col-sm-12">
                        <div id="img-3" class="action2">

                            <div class="commande">Wild&themoon</div>
                            <a class="btn btn-theme cta-main btn-wait" href="http://www.wildandthemoon.com" onclick="window.open(this.href); return false;" title="Service" >Allons-y !<!--<i class="fa fa-angle-double-down" aria-hidden="true">--></i>  </a></div>
                    </div>
                </div>

            </div> </div>
            <div class="col-xs-12"><p class="text browntext">les restaurants près de chez vous</p></div>
            <div class="section5"><div class="container-fluid"><div id="map"></div></div></div>



<?php get_footer();

<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the "site-content" div and all content after.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */
?>

	</div><!-- .site-content -->


    <footer><div class="container"><div class="row"><div class="col-md-6"><p>SUIVEZ NOUS !<a style="padding: 10px; class="track3" target="_blank" href="https://www.instagram.com/gaiaapp/?hl=fr"><span class="fa fa-instagram"></span></a><a style="padding: 10px;" class="track1" target="_blank" href="https://www.facebook.com/gaiaapp/"><span class="fa fa-facebook"></span></a><a style="padding: 10px;" class="track2" target="_blank" href="https://twitter.com/gaia_app  "><span class="fa fa-twitter"></span></a></p></div> <div class="col-md-6 text-right"><a href="http://gaiaapp.fr/mention-legale/" title="Mentions légales" >Mentions légales</a></div></div></div>
    </footer></div>



		</div><!-- .site-info -->
	</footer><!-- .site-footer -->

</div><!-- .site -->

<?php wp_footer(); ?>
<script
        src="https://code.jquery.com/jquery-3.2.1.min.js"
        integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
        crossorigin="anonymous"></script></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCOW1nIPYCEKzxxtqjUtOLwbf_Z58GpDmY&libraries=places"></script>
            <script>
    // This example requires the Places library. Include the libraries=places
    // parameter when you first load the API. For example:
    // <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places">

    maxHeight = 0;
    $('.same-height').each(function () {
        maxHeight = $(this).height() > maxHeight ? $(this).height() : maxHeight;
    });
    $('.same-height').height(maxHeight);




    var map;
    var infowindow;
    var markers = [];

    function initMap() {
        var centroid = {lat: 48.8647, lng: 2.3490};

        map = new google.maps.Map(document.getElementById('map'), {
            center: centroid,
            zoom: 15
        });
        infowindow = new google.maps.InfoWindow();

        findPlaces(centroid);

        map.addListener('center_changed', function() {
            console.log('PRPOUT');
        });
        map.addListener('dragend', function() {
            findPlaces(map.getCenter());
        });
        // Try HTML5 geolocation.
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                infowindow.setPosition(pos);
                infowindow.setContent('Location found.');
                map.setCenter(pos);
                findPlaces(pos);
            }, function() {
                handleLocationError(true, infowindow, map.getCenter());
            });
        } else {
            // Browser doesn't support Geolocation
            handleLocationError(false, infowindow, map.getCenter());
        }

    }

    function findPlaces(latlng){
        var service = new google.maps.places.PlacesService(map);
        service.nearbySearch({
            keyword : "vegan|vegetarien",
            location: latlng,
            radius: 1300,
            type: ['restaurant']
        }, callback);
    }

    function callback(results, status) {
        if (status === google.maps.places.PlacesServiceStatus.OK) {

            for (var i = 0; i < markers.length; i++ ) {
                markers[i].setMap(null);
            }
            markers.length = 0;

            for (var i = 0; i < results.length; i++) {

                createMarker(results[i]);
            }
        }
    }

    function createMarker(place) {
        var placeLoc = place.geometry.location;
        var marker = new google.maps.Marker({
            map: map,
            position: place.geometry.location
        });

        google.maps.event.addListener(marker, 'click', function() {
            console.log(place);
            infowindow.setContent('<div class="gmap"><div class="gmap1">' + place.name + '</div>' + '<div class="gmap2"> Note :' + place.rating + '</div>'  +'<img class="gmap3" src="'+ place.photos[0].getUrl({maxWidth:100, maxHeight:100}) +'"></div>');
            infowindow.open(map, this);
        });
        markers.push(marker);
    }




    function handleLocationError(browserHasGeolocation, infoWindow, pos) {
        infoWindow.setPosition(pos);
        infoWindow.setContent(browserHasGeolocation ?
            'Error: The Geolocation service failed.' :
            'Error: Your browser doesn\'t support geolocation.');
    }
    initMap();




            </script>

</body>
</html>

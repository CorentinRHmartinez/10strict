$( document ).ready(function () {




    // Initialize Firebase
    var config = {
        apiKey: "AIzaSyBerGL3JW4NWTaxmxfCRuJdqJadf6mVIdQ",
        authDomain: "gaia-97476.firebaseapp.com",
        databaseURL: "https://gaia-97476.firebaseio.com",
        projectId: "gaia-97476",
        storageBucket: "gaia-97476.appspot.com",
        messagingSenderId: "740956028301"
    };
    firebase.initializeApp(config);


//some datas
        //recup les datas pour la landing
        var premiereSectionCta = document.getElementsByClassName('track1');
        var nbctaclick = 0;
        for (var i = 0; i < premiereSectionCta.length; i++) {

            premiereSectionCta[i].addEventListener('click', function(e) {
                nbctaclick = nbctaclick + 1;

                console.log(nbctaclick);

                datasctaup = {
                    click: nbctaclick
                }

                var dbRefDatas = firebase.database().ref('datas');
                var dbRefDatasCTA = dbRefDatas.child('cta-fb');
                var dbRefDatasCTAkey = dbRefDatas.child('cta-fb').push().key;
                var dbRefDatasCTActa = dbRefDatasCTA.child(dbRefDatasCTAkey);

                dbRefDatasCTActa.update(datasctaup);


            })
        }




        /* var dbRefDatas = firebase.database().ref("datas/cta")
         dbRefDatas.on("child_added", function(snap){
         const blockCta = document.getElementById("dkr1")
         blockCta.innerText=snap.val().click;



         });
         */

        var dbRefCalls = firebase.database().ref().child('datas/cta-fb');
        dbRefCalls.on('value', function(snap) {
            var callsCOUNTER = 0;
            //console.log(snap.val())
            for (key in snap.val()) {
                //console.log(key);
                callsCOUNTER = callsCOUNTER + 1;

            }
            const blockCta = document.getElementById("dkr1")
           /*  blockCta.innerHTML = callsCOUNTER; */

        });




        var secondSectionCta = document.getElementsByClassName('track2');
        var nbctaclick = 0;
        for (var i = 0; i < secondSectionCta.length; i++) {

            secondSectionCta[i].addEventListener('click', function(e) {
                nbctaclick = nbctaclick + 1;

                console.log(nbctaclick);

                datasctaup = {
                    click: nbctaclick
                }

                var dbRefDatas = firebase.database().ref('datas');
                var dbRefDatasCTA = dbRefDatas.child('cta-twitter');
                var dbRefDatasCTAkey = dbRefDatas.child('cta-twitter').push().key;
                var dbRefDatasCTActa = dbRefDatasCTA.child(dbRefDatasCTAkey);

                dbRefDatasCTActa.update(datasctaup);


            })
        }


        var dbRefCalls = firebase.database().ref().child('datas/cta-twitter');
        dbRefCalls.on('value', function(snap) {
            var callsCOUNTER = 0;
            //console.log(snap.val())
            for (key in snap.val()) {
                //console.log(key);
                callsCOUNTER = callsCOUNTER + 1;

            }
            const blockCta2 = document.getElementById("dkr2")
           /* blockCta2.innerHTML = callsCOUNTER; */

        });


    var troisiemeSectionCta = document.getElementsByClassName('track3');
    var nbctaclick = 0;
    for (var i = 0; i < troisiemeSectionCta.length; i++) {

        troisiemeSectionCta[i].addEventListener('click', function(e) {
            nbctaclick = nbctaclick + 1;

            console.log(nbctaclick);

            datasctaup = {
                click: nbctaclick
            }

            var dbRefDatas = firebase.database().ref('datas');
            var dbRefDatasCTA = dbRefDatas.child('cta-insta');
            var dbRefDatasCTAkey = dbRefDatas.child('cta-insta').push().key;
            var dbRefDatasCTActa = dbRefDatasCTA.child(dbRefDatasCTAkey);

            dbRefDatasCTActa.update(datasctaup);


        })
    }



//Create references

        const dbRefObject = firebase.database().ref().child('emails');
// sync object changes

        dbRefObject.on('value', function(snap){
            console.log(snap.val())
            for(key in snap.val()){
                const  ulList = document.getElementById('list')
                const li = document.createElement('li');
                li.innerText = snap.val()[key].email;
                ulList.appendChild(li);

                console.log(snap.val()[key].email);

            }
        });






    /* var dbRefDatas = firebase.database().ref("datas/cta")
     dbRefDatas.on("child_added", function(snap){
     const blockCta = document.getElementById("dkr1")
     blockCta.innerText=snap.val().click;



     });
     */

    var dbRefCalls = firebase.database().ref().child('datas/cta-insta');
    dbRefCalls.on('value', function(snap) {
        var callsCOUNTER = 0;
        //console.log(snap.val())
        for (key in snap.val()) {
            //console.log(key);
            callsCOUNTER = callsCOUNTER + 1;

        }
        const blockCta = document.getElementById("dkr3")
        /*  blockCta.innerHTML = callsCOUNTER; */

    });



// sync on list change

        smoothScroll.init();

});
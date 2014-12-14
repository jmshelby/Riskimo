var geocoder = new google.maps.Geocoder();
var map;
var lastKnownPosition;
var markers = [];
var heartbeat;
var riskimoUrl = 'http://riskimo.mooo.com/webservice/';
var username = 'Test';

function setOutpost(position) {
    $.get(riskimoUrl + 'establish-base?latitude='
        +position.lat()
        +'&longitude='
        +position.lng()
        +'&username='+username,
    function(response) {
        console.log('Establish Base', response);
    });

    addOutpostsToMap();
}

function moveBattalion(position) {
    /*$.get(riskimoUrl + 'move-battalion?latitude='
        +position.lat()
        +'&longitude='
        +position.lng()
        +'&username='+username,
    function(response) {
        console.log('Move Battalion', response);
    });*/
}

function addOutpostsToMap() {
    $.get(riskimoUrl + 'bases?username=' + username,
    function(response) {
        console.log('Bases', response);
        var bases = response.response;
        for(var i in bases) {
            var base = bases[i];
            new google.maps.Marker({
                position: new google.maps.LatLng(base.location.latitude, base.location.longitude),
                title: 'Base',
                map: map,
                icon: 'http://img2.wikia.nocookie.net/__cb20140526212505/eyevea-archives/images/9/9a/AD_Outpost_Map_Icon.png'
            });
        }
    });
}

function geocodePosition(pos) {
    setOutpost(pos);

    /*geocoder.geocode({
        latLng: pos
    }, function(responses) {
        if (responses && responses.length > 0) {
            updateMarkerAddress(responses[0].formatted_address);
        } else {
            updateMarkerAddress('Cannot determine address at this location.');
        }
    });*/
}

function initialize(position) {
    var latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
    map = new google.maps.Map(document.getElementById('mapcanvas'), {
        zoom: 5,
        center: latLng,
        mapTypeId: google.maps.MapTypeId.SATELLITE
    });

    var marker = new google.maps.Marker({
        position: latLng,
        title: 'Your Current Location',
        map: map
    });

    var battalionMarker = new google.maps.Marker({
        position: latLng,
        title: 'Your Current Location',
        map: map,
        draggable: true,
        icon: 'https://cdn3.iconfinder.com/data/icons/buildings-places/512/Festival-128.png'
    });

    // Update current position info.
    /*updateMarkerPosition(latLng);*/
    geocodePosition(latLng);

    // Add dragging event listeners.
    /*google.maps.event.addListener(battalionMarker, 'dragstart', function() {
        updateMarkerAddress('Dragging...');
    });*/

    google.maps.event.addListener(battalionMarker, 'drag', function() {
        /*updateMarkerStatus('Dragging...');
        updateMarkerPosition(battalionMarker.getPosition());*/
    });

    google.maps.event.addListener(battalionMarker, 'dragend', function() {
        /*updateMarkerStatus('Drag ended');*/
        moveBattalion(battalionMarker.getPosition());
    });

    google.maps.event.addListener(map, 'rightclick', function(event) {
        marker.setPosition(event.latLng);
        geocodePosition(marker.getPosition());
    });

    /*heartbeat = setInterval(getMessages, 5000);*/
}

function initializeDefault() {
    var defaultPosition = {
        coords: {
            latitude: 39.739341754525086,
            longitude: -104.98478651046753
        }
    }

    initialize(defaultPosition);
}

function loadGeolocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(initialize, initializeDefault);
    } else {
        // no geolocation service
        initializeDefault();
    }
}

// Onload handler to fire off the app.
google.maps.event.addDomListener(window, 'load', loadGeolocation);
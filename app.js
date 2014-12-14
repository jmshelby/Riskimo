var geocoder = new google.maps.Geocoder();
var map;
var lastKnownPosition;
var markers = [];
var heartbeat;

function geocodePosition(pos) {
    lastKnownPosition = pos;
    getMessages(pos);

    geocoder.geocode({
        latLng: pos
    }, function(responses) {
        if (responses && responses.length > 0) {
            updateMarkerAddress(responses[0].formatted_address);
        } else {
            updateMarkerAddress('Cannot determine address at this location.');
        }
    });
}

$.get('url', function(response) {
    console.log(response);
});

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
        map: map,
        draggable: true
    });

/*
    // Update current position info.
    updateMarkerPosition(latLng);
    geocodePosition(latLng);

    // Add dragging event listeners.
    google.maps.event.addListener(marker, 'dragstart', function() {
        updateMarkerAddress('Dragging...');
    });

    google.maps.event.addListener(marker, 'drag', function() {
        updateMarkerStatus('Dragging...');
        updateMarkerPosition(marker.getPosition());
    });

    google.maps.event.addListener(marker, 'dragend', function() {
        updateMarkerStatus('Drag ended');
        geocodePosition(marker.getPosition());
    });

    google.maps.event.addListener(map, 'rightclick', function(event) {
        marker.setPosition(event.latLng);
        geocodePosition(marker.getPosition());
    });

    heartbeat = setInterval(getMessages, 5000);*/
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

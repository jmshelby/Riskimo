var geocoder = new google.maps.Geocoder();
var map;
var riskimoUrl = 'http://riskimo.mooo.com/webservice/';

function Player(username) {
    var self = this;

    self.currentMarker;
    self.battalionMarker;
    self.battalionTargetMarker;
    self.lastKnownPosition;
    self.heartbeat;
    self.username = username;
    self.isDragging = false;

    self.setOutpost = function setOutpost(position) {
        /* I am not sure if outposts are a necessary or valuable part of the game
           Let's try without them!

        $.get(riskimoUrl + 'establish-base?latitude='
            +position.lat()
            +'&longitude='
            +position.lng()
            +'&username='+self.username,
        function(response) {
            console.log('Establish Base', response);
        });

        self.addOutpostsToMap();*/
    };

    self.moveBattalion = function moveBattalion(position) {
        $.get(riskimoUrl + 'move-battalion?latitude='
            +position.lat()
            +'&longitude='
            +position.lng()
            +'&username='+self.username,
        function(response) {
            console.log('Move Battalion', response);
        });
    };

    self.updateBattalionPosition = function updateBattalionPosition() {
        $.get(riskimoUrl + 'battalion-position?username='+self.username,
        function(response) {
            console.log('Battalion Position', response);
            var position = response.response;
            self.battalionMarker.setPosition(new google.maps.LatLng(position.lat, position.long));

            if (!self.isDragging) {
                self.battalionTargetMarker.setPosition(new google.maps.LatLng(position.marker.location.latitude, position.marker.location.longitude));
            }

            self.updateBattalionPath();
        });
    };

    self.updateBattalionPath = function updateBattalionPath() {
        self.battalionPath.setPath([
            self.battalionMarker.getPosition(),
            self.battalionTargetMarker.getPosition()
        ]);
    }

    self.addOutpostsToMap = function addOutpostsToMap() {
        /* I am not sure if outposts are a necessary or valuable part of the game
           Let's try without them!

        $.get(riskimoUrl + 'bases?username=' + self.username,
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
        });*/
    };

    self.geocodePosition = function geocodePosition(pos) {
        /* I am not sure if outposts are a necessary or valuable part of the game
           Let's try without them!

        self.setOutpost(pos);*/

        /*geocoder.geocode({
            latLng: pos
        }, function(responses) {
            if (responses && responses.length > 0) {
                updateMarkerAddress(responses[0].formatted_address);
            } else {
                updateMarkerAddress('Cannot determine address at this location.');
            }
        });*/
    };

    self.updateZoom = function updateZoom(zoomScale) {
        var scale = map.getZoom() * map.getZoom();
        var markers = [self.currentMarker, self.battalionMarker];

        for (var i in markers) {
            markers[i].getIcon().scaledSize
                = markers[i].getIcon().size
                = new google.maps.Size(scale, scale);
            markers[i].getIcon().anchor = new google.maps.Point(scale/2, scale/2);
            markers[i].setZIndex(markers[i].setZIndex()); // force icon redraw
        }
    };

    self.initialize = function initialize(position) {
        var latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
        if (!map) {
            map = new google.maps.Map(document.getElementById('mapcanvas'), {
                zoom: 5,
                center: latLng,
                mapTypeId: google.maps.MapTypeId.SATELLITE
            });
        }

        self.currentMarker = new google.maps.Marker({
            position: latLng,
            title: 'Your Current Location',
            map: map,
            icon: {
                url: 'https://cdn3.iconfinder.com/data/icons/buildings-places/512/Festival-128.png'
            }
        });

        // TODO: start battalion marker at current battalion location
        self.battalionMarker = new google.maps.Marker({
            title: 'Battalion',
            map: map,
            zIndex: 10,
            icon: {
                url: 'http://icons.iconarchive.com/icons/3xhumed/mega-games-pack-23/128/Americas-Army-4-icon.png'
            }
        });

        self.battalionTargetMarker = new google.maps.Marker({
            title: 'Battalion Target',
            map: map,
            zIndex: -1,
            draggable: true,
            crossOnDrag: false,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                strokeColor: 'white',
                strokeWeight: 3,
                strokeOpacity: 0.6,
                fillColor: 'white',
                fillOpacity: 0.2,
                scale: 30
            }
        });

        self.battalionPath = new google.maps.Polyline({
            geodesic: true,
            strokeColor: 'white',
            strokeOpacity: 1.0,
            strokeWeight: 1,
            map: map
        });

        // Update current position info.
        /*updateMarkerPosition(latLng);*/
        self.geocodePosition(latLng);
        self.updateBattalionPosition();
        self.updateZoom(map.getZoom());

        // Info window for battalions
        self.infowindow = new google.maps.InfoWindow({
            content: '<a href="javascript:alert(\'TODO: provide interaction options: Attack, Invite to Team, Chat, etc\')">'+self.username+'</a>'
        });
        self.infowindow.open(map, self.battalionMarker);
        google.maps.event.addListener(self.battalionMarker, 'click', function() {
            self.infowindow.open(map, self.battalionMarker);
        });

        // Add dragging event listeners.
        google.maps.event.addListener(self.battalionTargetMarker, 'dragstart', function() {
            self.isDragging = true;
        });

        google.maps.event.addListener(self.battalionTargetMarker, 'drag', function() {
            self.updateBattalionPath();
        });

        google.maps.event.addListener(self.battalionTargetMarker, 'dragend', function() {
            self.isDragging = false;
            self.moveBattalion(self.battalionTargetMarker.getPosition());
        });

        google.maps.event.addListener(map, 'rightclick', function(event) {
            /*self.currentMarker.setPosition(event.latLng);
            self.geocodePosition(self.currentMarker.getPosition());*/
        });

        google.maps.event.addListener(map, 'zoom_changed', function() {
            self.updateZoom(map.getZoom());
        });

        /*heartbeat = setInterval(getMessages, 5000);*/
        setInterval(self.updateBattalionPosition, 1000);
    };

    self.initializeDefault = function initializeDefault() {
        var defaultPosition = {
            coords: {
                latitude: 39.739341754525086,
                longitude: -104.98478651046753
            }
        }

        self.initialize(defaultPosition);
    };

    self.loadGeolocation = function loadGeolocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(self.initialize, self.initializeDefault);
        } else {
            // no geolocation service
            self.initializeDefault();
        }
    };

    // Onload handler to fire off the app.
    google.maps.event.addDomListener(window, 'load', self.loadGeolocation);

}

new Player('Test');
new Player('jake');
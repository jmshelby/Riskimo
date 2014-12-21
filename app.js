var geocoder = new google.maps.Geocoder();
var map;

var riskimo = new function Riskimo() {}

riskimo.api = new function Api() {
    var self = this;
    var apiUrl = 'http://riskimo.mooo.com/webservice/';

    self.moveUnitGroup = function moveUnitGroup(position, callback) {
        $.get(apiUrl + 'move-group?username='+riskimo.player.username
            +'&latitude='
            +position.lat()
            +'&longitude='
            +position.lng(),
        function(response) {
            console.log('Unit Group Moved', response);
            if (callback) callback(response.response);
        });
    };

    self.addUnit = function addUnit(callback) {
        $.get(apiUrl + 'add-unit?username='+riskimo.player.username,
        function(response) {
            console.log('Unit Added', response);
            if (callback) callback(response.response);
        });
    };

    self.poll = function poll(callback) {
        $.get(apiUrl + 'group-position?username='+riskimo.player.username,
        function(response) {
            console.log('UnitGroup Position', response);
            if (callback) callback(response.response);
        });
    }
}

riskimo.icons = new function Icons() {
    var self = this;

    self.zoom = function zoom(marker) {
        var zoom = map.getZoom();
        var scale = zoom * zoom;

        marker.getIcon().scaledSize
            = marker.getIcon().size
            = new google.maps.Size(scale, scale);
        marker.getIcon().anchor = new google.maps.Point(scale/2, scale/2);
        marker.setZIndex(marker.setZIndex()); // force icon redraw
    }
}

riskimo.Player = function Player(username) {
    var self = this;
    var marker;

    self.username = username;

    self.setPosition = function setPosition(position) {
        if (!marker) {
            marker = new google.maps.Marker({
                title: 'Your Current Location',
                map: map,
                icon: {
                    url: 'https://cdn3.iconfinder.com/data/icons/buildings-places/512/Festival-128.png'
                }
            });

            riskimo.icons.zoom(marker);

            google.maps.event.addListener(map, 'zoom_changed', function() {
                riskimo.icons.zoom(marker);
            });
        }

        marker.setPosition(position);
    }

    console.log('Player Instantiated', '"'+username+'"', self.marker);
}

riskimo.Unit = function Unit() {
    // Nothing to do here unless individual units become part of the UI
}

riskimo.UnitGroup = function UnitGroup() {
    var self = this;

    var groupMarker = new google.maps.Marker({
        title: 'Group',
        map: map,
        zIndex: 10,
        icon: {
            url: 'http://icons.iconarchive.com/icons/3xhumed/mega-games-pack-23/128/Americas-Army-4-icon.png'
        }
    });

    var targetMarker = new google.maps.Marker({
        title: 'Group Target',
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

    var targetPath = new google.maps.Polyline({
        geodesic: true,
        strokeColor: 'white',
        strokeOpacity: 1.0,
        strokeWeight: 1,
        map: map
    });

    function updatePath() {
        targetPath.setPath([
            groupMarker.getPosition(),
            targetMarker.getPosition()
        ]);
    }

    self.update = function update(data) {
        groupMarker.setPosition(new google.maps.LatLng(data.lat, data.long));

        if (!self.isDragging) {
            targetMarker.setPosition(new google.maps.LatLng(data.marker.location.latitude, data.marker.location.longitude));
        }

        updatePath();
    }

    riskimo.icons.zoom(groupMarker);

    google.maps.event.addListener(map, 'zoom_changed', function() {
        riskimo.icons.zoom(groupMarker);
    });

    // Info window for groups
    self.infowindow = new google.maps.InfoWindow({
        content: '<a href="javascript:alert(\'TODO: provide interaction options: Attack, Invite to Team, Chat, etc\')">'+riskimo.player.username+'</a>'
    });
    self.infowindow.open(map, groupMarker);

    google.maps.event.addListener(groupMarker, 'click', function() {
        self.infowindow.open(map, groupMarker);
    });

    // Add dragging event listeners.
    google.maps.event.addListener(groupMarker, 'dragstart', function() {
        self.isDragging = true;
    });

    google.maps.event.addListener(targetMarker, 'drag', function() {
        updatePath();
    });

    google.maps.event.addListener(targetMarker, 'dragend', function() {
        self.isDragging = false;
        riskimo.api.moveUnitGroup(targetMarker.getPosition());
    });
}

riskimo.Fort = function Fort() {

}

function Game(username) {
    var self = this;

    riskimo.player = new riskimo.Player(username);

    self.group;
    self.heartbeat;
    self.username = username;
    self.isDragging = false;

    var poll = function poll() {
        riskimo.api.poll(function(data) {
            self.group.update(data);
        });
    };

    self.initialize = function initialize(position) {
        console.log('Initializing UI');
        var latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
        if (!map) {
            map = new google.maps.Map(document.getElementById('mapcanvas'), {
                zoom: 5,
                center: latLng,
                mapTypeId: google.maps.MapTypeId.SATELLITE
            });
        }

        riskimo.player.setPosition(latLng);
        self.group = new riskimo.UnitGroup();

        // Update current game info
        poll();
        heartbeat = setInterval(poll, 1000);
    };

    self.initializeDefault = function initializeDefault() {
        console.log('No geolocation available.  Using Denver, USA as default location.')
        var defaultPosition = {
            coords: {
                latitude: 39.739341754525086,
                longitude: -104.98478651046753
            }
        }

        self.initialize(defaultPosition);
    };

    self.loadGeolocation = function loadGeolocation() {
        console.log('Retrieving Geolocation');
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(self.initialize, self.initializeDefault);
        } else {
            // no geolocation service
            self.initializeDefault();
        }
    };

    console.log('Signing In', '"'+self.username+'"');
    self.loadGeolocation();
}


var geocoder = new google.maps.Geocoder();
var map;

var riskimo = new function Riskimo() {}

riskimo.api = new function Api() {
    var self = this;
    var apiUrl = 'http://riskimo.mooo.com/webservice/';

    self.moveUnitGroup = function moveUnitGroup(username, position, callback) {
        $.get(apiUrl + 'move-group?username='+username
            +'&latitude='
            +position.lat()
            +'&longitude='
            +position.lng(),
        function(response) {
            console.log('Unit Group Moved', response);
            if (callback) callback(response.response);
        });
    };

    self.addUnit = function addUnit(username, callback) {
        $.get(apiUrl + 'add-unit?username='+username,
        function(response) {
            console.log('Unit Added', response);
            if (callback) callback(response.response);
        });
    };

    self.poll = function poll(callback) {
        $.get(apiUrl + 'board?username='+riskimo.player.username,
        function(response) {
            console.log('Playing Board', response);
            if (callback) callback(response.response);
        });
    }

    self.attack = function attack(username) {
        for (var i in game.groups) {
            if (game.groups[i].player.username == username) {
                var defender = game.groups[i];
                console.log('Attacking', defender.player.username);
                self.moveUnitGroup(riskimo.player.username, defender.getTarget().getPosition());
            }
        }
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

riskimo.controlPanel = new function ControlPanel() {
    var self = this;
    var previousPosition;

    function getPoliticalName(responses) {
        console.log(responses);
        if (responses) {
            for (var i in responses) {
                for (var j in responses[i].types) {
                    if (responses[i].types[j] == 'political') {
                        return responses[i].formatted_address;
                    }
                }
            }
        }
        return 'Unknown';
    }

    self.update = function update(group) {
        var controlPanel = $(document).find('#control_panel');
        controlPanel.fadeIn();
        controlPanel.find('#current_units').text(group.unit_count);

        var latLng = new google.maps.LatLng(group.current_position.latitude, group.current_position.longitude);
        if (!latLng.equals(previousPosition)) {
            previousPosition = latLng;

            geocoder.geocode({
                latLng: new google.maps.LatLng(group.current_position.latitude, group.current_position.longitude)
            }, function(responses) {
                controlPanel.find('#location').text(getPoliticalName(responses))
            });
        }
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

    self.addUnit = function addUnit() {
        riskimo.api.addUnit(self.username);
    }

    console.log('Player Instantiated', '"'+username+'"', self.marker);
}

riskimo.Unit = function Unit() {
    // Nothing to do here unless individual units become part of the UI
}

riskimo.UnitGroup = function UnitGroup(data) {
    var self = this;
    var isDragging;
    var infowindow;

    self.player = new riskimo.Player(data.user.username);

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
        },
        visible: riskimo.player.username == self.player.username
    });

    var targetPath = new google.maps.Polyline({
        geodesic: true,
        strokeColor: 'white',
        strokeOpacity: 1.0,
        strokeWeight: 1,
        map: map,
        visible: riskimo.player.username == self.player.username
    });

    function updatePath() {
        targetPath.setPath([
            groupMarker.getPosition(),
            targetMarker.getPosition()
        ]);
    }

    self.update = function update(data) {
        groupMarker.setPosition(new google.maps.LatLng(data.current_position.latitude, data.current_position.longitude));

        if (!isDragging) {
            var target = data.current_position.marker;
            targetMarker.setPosition(new google.maps.LatLng(target.location.latitude, target.location.longitude));
        }

        updatePath();

        if (riskimo.player.username == self.player.username) {
            riskimo.controlPanel.update(data);
        }
    }

    self.getTarget = function getTarget() {
        return targetMarker;
    }

    self.update(data);

    riskimo.icons.zoom(groupMarker);

    google.maps.event.addListener(map, 'zoom_changed', function() {
        riskimo.icons.zoom(groupMarker);
    });

    // Info window for groups
    var infowindow = new google.maps.InfoWindow({
        content: '<a href="javascript:riskimo.api.attack(\''+self.player.username+'\');">Attack '+self.player.username+'</a>'
    });

    google.maps.event.addListener(groupMarker, 'click', function() {
        infowindow.open(map, groupMarker);
        infowindow.setContent(infowindow.getContent());
    });

    // Add dragging event listeners.
    google.maps.event.addListener(targetMarker, 'dragstart', function() {
        isDragging = true;
    });

    google.maps.event.addListener(targetMarker, 'drag', function() {
        updatePath();
    });

    google.maps.event.addListener(targetMarker, 'dragend', function() {
        isDragging = false;
        riskimo.api.moveUnitGroup(self.player.username, targetMarker.getPosition());
    });

}

riskimo.Fort = function Fort() {

}

function Game(username) {
    var self = this;

    riskimo.player = new riskimo.Player(username);

    self.groups = {};
    self.heartbeat;
    self.username = username;

    var poll = function poll() {
        riskimo.api.poll(function(data) {
            for (var i in data.groups) {
                var group = data.groups[i];

                if (!self.groups[group._id]) {
                    self.groups[group._id] = new riskimo.UnitGroup(group);
                } else {
                    self.groups[group._id].update(group);
                }
            }
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

            new DayNightOverlay({
                map: map,
                fillColor: 'rgba(0,0,0,0.3)'
            });
        }

        riskimo.player.setPosition(latLng);

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


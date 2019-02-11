<!DOCTYPE html>
<html>
<meta name="viewport" content="width=device-width, initial-scale=1">
<head>
    <title>Tracker by Kaiser</title>
    <meta charset="utf-8">
    <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous"> -->
    <style type="text/css">
        html {
          font-family: -apple-system,BlinkMacSystemFont,"Segoe UI","Roboto","Oxygen","Ubuntu","Cantarell","Fira Sans","Droid Sans","Helvetica Neue",sans-serif;
          font-weight: 300;
          font-size: 1em;
          line-height: 1.5;
          letter-spacing: 0.5px;
          background-color: #f9f9f9;
          color: #212121;
          overflow-y: scroll;
          min-height: 100%;
          box-sizing: border-box;
        }

        html, body {
          margin: 0;
          padding: 0;
        }

        h1, h2, h3, h4 {
          font-weight: 300;
          margin: 0;
        }

        #map {
          height: 90vh;
        }

        .header {
          display: flex;
          flex-direction: row;
          flex-wrap: nowrap;
          flex-shrink: 0;
          box-sizing: border-box;
          align-self: stretch;
          align-items: center;
          width: 100%;
          height: 40px;
          padding: 24px 16px;
          color: #fff;
          background: #512da8;
          box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.2), 0 4px 5px 0 rgba(0, 0, 0, 0.14), 0 1px 10px 0 rgba(0, 0, 0, 0.12);
        }

        .name-box {
          padding: 4px 20px;
          background: #673ab7;
          color: #fff;
        }

        .name-box input[type="text"] {
          padding: 8px 12px;
          font-size: 14px;
          border: 2px solid #ddd;
          outline: none;
        }

        .name-box input[type="text"]:focus {
          border-color: #ff4081;
        }

        .name-box button {
          color: #fff;
          padding: 10px;
          background: #ff4081;
          border-color: #ff4081;
          outline: none;
        }

        .hidden {
          display: none;
        }

        #delivery-heroes-list {
          padding: 4px 0;
        }

        .small {
          padding: 2px 6px !important;
        }
    </style>
</head>
<body>

    <div class="container-fluid p-0">
        <div id="name-box" class="name-box">
            <h3>Enter your username</h3>
            <input id="name" type="text" placeholder="e.g. Kaiser">
            <button id="saveNameButton">Save</button>
        </div>

        <div id="delivery-hero-box" class="name-box" style="display: none;">
            <h3 id="welcome-message"></h3>
            <h4 id="delivery-heroes-list"></h4>
            <input id="deliveryHeroName" type="text" placeholder="e.g. Shelly">
            <button id="addDeliveryHeroButton">Add</button>
        </div>

        <div id="map"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script> -->
    <!-- <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script> -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{env('GOOGLEMAPS_KEY')}}"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/socket.io/2.2.0/socket.io.js"></script>

    <script type="text/javascript">

        const socket = io.connect(window.location.hostname + ':3001', {
            transports: ['websocket'], 
            upgrade: false
        });

        $(function() {

            var username;

            // handy variables
            var locationWatcher;
            var myLastKnownLocation;
            var sendLocationInterval;
            var deliveryHeroesLocationMap = {};
            var deliveryHeroesMarkerMap = {};

            // mode - user's or delivery guy's
            var mode = 'user';

            // load the map
            map = new google.maps.Map(document.getElementById('map'), {
                center: {lat: -34.397, lng: 150.644},
                zoom: 14
            });

            // get the location via Geolocation API
            if ('geolocation' in navigator) {
                var currentLocation = navigator.geolocation.getCurrentPosition(function (position) {
                    // save my last location
                    var location = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };

                    myLastKnownLocation = location;
                    map.setCenter(location);
                });
            }

            $('#saveNameButton').click( function () {

                var input = $('#name-box input').val();

                if (input && input.trim()) {

                    username = input;

                    // hide the name box
                    $('#name-box').hide();

                    // set the name
                    var message = 'Hi! <strong>' + username +
                    (mode === 'user' ? '</strong>, type in your Delivery Hero\'s name to track your food.' : '</strong>, type in the customer name to locate the address');
                    $('#welcome-message').html(message);

                    // show the delivery hero's div now
                    $('#delivery-hero-box').show();

                    // create a private channel with the username
                    createMyLocationChannel(username);

                }

                return;

            });

            $('#addDeliveryHeroButton').click( function() {
                var deliveryHeroName = $('#deliveryHeroName').val();

                // if already present return
                if (deliveryHeroesLocationMap[deliveryHeroName]) return;

                if (deliveryHeroName) {
                    var deliveryHeroChannelName = 'private-' + deliveryHeroName;
                    var deliveryHeroChannel = subscribe(deliveryHeroChannelName);
                    deliveryHeroChannel.on('client-location', function (nextLocation) {
                        // first save the location
                        // bail if location is same
                        var prevLocation = deliveryHeroesLocationMap[deliveryHeroName] || {};
                        deliveryHeroesLocationMap[deliveryHeroName] = nextLocation;
                        showDeliveryHeroOnMap(deliveryHeroName, false, true, prevLocation);
                    });
                }

                // add the name to the list
                var deliveryHeroTrackButton = $('<button/>');
                    deliveryHeroTrackButton.addClass('small');
                    deliveryHeroTrackButton.html(deliveryHeroName);
                    deliveryHeroTrackButton.click(function() {
                        showDeliveryHeroOnMap(deliveryHeroName, true, false, {});
                    });
                $('#delivery-heroes-list').append(deliveryHeroTrackButton);
            });

            function createMyLocationChannel (name) {

                var myLocationChannel = subscribe('private-' + name, function() {
                    // safe to now trigger events
                    // use the watchPosition API to watch the changing location
                    // and trigger events with new coordinates
                    locationWatcher = navigator.geolocation.watchPosition( function(position) {
                        var location = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        triggerLocationChangeEvents(myLocationChannel, location);
                    });

                    // also start a setInterval to keep sending the loction every 5 secs
                    sendLocationInterval = setInterval( function () {
                        // not using `triggerLocationChangeEvents` to keep the pipes different
                        myLocationChannel.emit('client-location', myLastKnownLocation)
                    }, 5000);

                });

            }

            function showDeliveryHeroOnMap (deliveryHeroName, center, addMarker, prevLocation) {

                if (!deliveryHeroesLocationMap[deliveryHeroName]) return;

                // first center the map
                if (center) map.setCenter(deliveryHeroesLocationMap[deliveryHeroName]);
                var nextLocation = deliveryHeroesLocationMap[deliveryHeroName];

                // add a marker
                if ((prevLocation.lat === nextLocation.lat) && (prevLocation.lng === nextLocation.lng)) {
                    return;
                }

                if (addMarker) {
                    var marker = deliveryHeroesMarkerMap[deliveryHeroName];
                    marker = marker || new google.maps.Marker({
                        map: map,
                        label: deliveryHeroName,
                        animation: google.maps.Animation.BOUNCE,
                    });
                    marker.setPosition(deliveryHeroesLocationMap[deliveryHeroName]);
                    deliveryHeroesMarkerMap[deliveryHeroName] = marker;
                }
            }

            function triggerLocationChangeEvents (channel, location) {
                // update myLastLocation
                myLastKnownLocation = location;
                channel.emit('client-location', location);
            }

            function subscribe (channel, callback) {

                socket.emit('subscribe', channel);

                if (callback) {
                    socket.on(this.channel + '|subscribe', callback);
                }

                return {
                    channel: channel,
                    emit: function(event, data) {
                        if (data) {
                            socket.emit('channel', {channel: this.channel, event: event, data: data});
                        }
                    },
                    on: function(event, callback) {
                        socket.on(this.channel + '|' + event, callback);
                    },
                    unsubscribe: function(callback) {
                        socket.emit('unsubscribe', this.channel);
                        if (callback) {
                            socket.on(this.channel + '|unsubscribe', callback);
                        }
                    }
                };
            }

        });
    </script>
</body>
</html>

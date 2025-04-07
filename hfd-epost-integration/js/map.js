var IsraelPostMap = {
    map: null,
    activeInfo: null,
    infoWindows: {},
    markers: {},
    current_spot: null,
    init: function () {
        var _this = this;
        $j( "body" ).on( 'click', '.selectspot', function (e) {
            e.preventDefault();
            var spotId = $j(this).data('shopid');
            _this.selectSpot();
        });

        $j(window).trigger('resize');
		  
        this.drawMap();
    },

    drawMap: async function(){
		const { AutocompleteService } = await google.maps.importLibrary("places");
		
		this.map = new google.maps.Map(document.getElementById('israelpost-map'), {
            center: {lat: 32.063940, lng: 34.837801},
            zoom: 12,
			mapId: 'hfd19integration'
        });
		
		window.gmap = this.map;
		
		var searchContainer = document.getElementById('israelpost-autocompelete');
        var legend = (document.getElementById('legend'));
		var input = (document.getElementById('pac-input'));
		
		//searchContainer.appendChild(placeAutocomplete);
        this.map.controls[google.maps.ControlPosition.TOP_LEFT].push(legend);
        this.map.controls[google.maps.ControlPosition.TOP_LEFT].push(searchContainer);
		
		var autocomplete = new google.maps.places.Autocomplete(input, {
			componentRestrictions: {country: 'il'},
			fields: ["address_components", "geometry", "icon", "name"],
			bounds: this.map.getBounds()
		});
		
		autocomplete.addListener("place_changed", () => {
			const place = autocomplete.getPlace();
			if( !place.geometry ){
                window.alert( "Autocomplete's returned place contains no geometry" );
                return;
            }
			if( place.geometry.viewport ){
				this.map.fitBounds( place.geometry.viewport );
			}else{
				this.map.setCenter( place.geometry.location );
				this.map.setZoom( 17 );  // Why 17? Because it looks good.
			}
		});
    },
	buildContent: function( spot ){
		var icon = IsraelPostCommon.getConfig('redDotPath');
		if (spot.type == 'חנות') {
			var icon = IsraelPostCommon.getConfig('grnDotPath');
		}
					
		const content = document.createElement("div");
		content.classList.add("infoBox");
		content.innerHTML = '<div class="details">' +
                '<h3>' + spot.name + '</h3>' +
                '<p>' + spot.street + ' ' + spot.house +
                '<br />' + spot.city +
                '<br />' + spot.remarks +
                '</p><ul class="hours">' + this.generateHours(spot) + '</ul>' +
                '<a href="#" data-shopid="' + spot.n_code + '" class="selectspot">' + Translator.translate('Select') + ' ' + spot.type + ' &raquo;</a></div>';
				
		return content;
	},
    pinMarkers: async function (spots) {
		const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
		const { Map, InfoWindow } = await google.maps.importLibrary("maps");
		
		var spotList = [];
        spots = spots || {};
        var _this = this,
            infoboxOptions,
            spot;
		
		var info_window = null;
		
        //loop trough shops
        for (i in spots) {
            spot = spots[i];
            
            var icon = IsraelPostCommon.getConfig('redDotPath');
            if (spot.type == 'חנות') {
                var icon = IsraelPostCommon.getConfig('grnDotPath');
            }
			
			spotList.push( spot );
			
			const hfdImg = document.createElement("img");
			hfdImg.src = icon;
			
            //google maps marker
            const marker = new AdvancedMarkerElement({
                position: new google.maps.LatLng(spot.latitude, spot.longitude),
                map: this.map,
                content: hfdImg,
                zIndex: null,
            });
						
			marker.spot = spot;
			marker.addListener("click", ({domEvent, latLng}) => {
				if( info_window ){
					info_window.close();
				}
				// Create an info window to share between markers.
				info_window = new InfoWindow({
					content: this.buildContent( marker.spot ),
				});
				this.current_spot = marker.spot;
				//infoWindow.setContent( marker.content );
				info_window.open({
					anchor: marker,
				});
			});
        }
		
        google.maps.event.addListenerOnce(_this.map, 'idle', function () {
            google.maps.event.trigger(_this.map, 'resize');
        });
    },

    clickSpot: function (spotid) {
        //move map to center of this marker
       // this.map.panTo(this.markers[spotid].getPosition());
        //open the infobubble
        if (this.activeInfo != null) {
            this.infoWindows[this.activeInfo].close();
        }
        this.infoWindows[spotid].open(this.map, this.markers[spotid]);
        //active marker is this one
        this.activeInfo = spotid;
    },

    generateHours: function (json) {
        var hoursoutput = '';
        return hoursoutput;
    },

    selectSpot: function () {
		var spot = this.current_spot;
        var html = this.spotTemplate = '<strong>' + Translator.translate('Branch name') + ':</strong> '+ spot.name +' <br/>'
            + '<strong>' + Translator.translate('Branch address') + ':</strong> '+ spot.street +' '+ spot.house +', '+ spot.city +' <br/>'
            + '<strong>' + Translator.translate('Operating hours') + ':</strong> '+ spot.remarks
        IsraelPost.saveSpotInfo(spot)
        IsraelPost.renderSpotInfo(html);
        IsraelPost.renderSpotId(spot.n_code);
        IsraelPost.closeModal();
    },

    closeInfobox: function () {
        if (this.activeInfo != null && this.infoWindows[this.activeInfo])
            this.infoWindows[this.activeInfo].close();

        this.activeInfo = null;
    },

    resize: function () {
        google.maps.event.trigger(this.map, "resize");
    }
};
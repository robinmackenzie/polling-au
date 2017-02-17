var MapManager = (function() {
  
  var map;
  var cedGeoJson;
  var pollingPlaces;
  var markerGroup;
  // ui elements
  var toggleCedGeoJson;
  var infoTable;
  var infoTableBody;
  var votesTable;
  var votesTableBody;
  
  // public methods
  // init
  var init = function() {
    bindUIActions();
    createMap();
  };
  
  var getMap = function() {
    return map;
  }
  
  // private methods
  var bindUIActions = function() {
    toggleCedGeoJson = $('#checkDisplayCED');
    toggleCedGeoJson.on('change', showHideCedGeoJson);
    infoTable = $('#placeTable');
    infoTableBody = $('#placeTableBody');
    votesTable = $('#voteResultsTable');
    votesTableBody = $('#voteResultsTableBody');
  };

  // event handler for togging cedGeoJson layer
  var showHideCedGeoJson = function() {
    if (toggleCedGeoJson.is(":checked")) {
      map.addLayer(cedGeoJson);
    } else {
      map.removeLayer(cedGeoJson);
    }
  };
  
  // create map and features
  var createMap = function() {
    // instantiate leaflet map
    map = L.map('map', {
      // surry hills = [-33.8899, 151.2151], zoom = 13
      // au national = [-27.371767300523032, 133.94531250000003], zoom = 4
      center: [-27.371767300523032, 133.94531250000003],
      minZoom: 2,
      zoom: 4
    });

    // add tiles to map
    L.tileLayer('http://{s}.tiles.wmflabs.org/bw-mapnik/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // add geo search plugin to map
    new L.Control.GeoSearch({
      provider: new L.GeoSearch.Provider.OpenStreetMap()
    }).addTo(map);

    // add geo location button to map
    lc = L.control.locate().addTo(map); 

    // add CED geojson layer
    $.getJSON("./geodata/ABS_CED_2016.geo.json", 
      function(json) {
        cedGeoJson = L.geoJson(json, {
          style: function (feature) {
            return  {
              color: '#0000ff',
              fillColor: '#0000aa',
              weight: 1,
              opacity: 0.7,
              fillOpacity: 0.05
            }; 
          },
          onEachFeature: function (feature, layer) {
              layer.bindPopup('CED: ' + feature.properties.Elect_div);
          }
        }).addTo(map);
      }
    );

    // add polling place markers in a marker group to map
    markerGroup = L.markerClusterGroup();
    $.ajax({
      url: './api/v1/pollingplaces',
      type: 'get',
      success: function(json) {
        pollingPlaces = json.data;
        for (var i=0; i<json.data.length; i++) {
          var place = json.data[i];
          if (place.lat == null) {
            console.log(place);
          } else {
            var placeId = place.polling_place_id;
            var placeName = place.polling_place_name;
            var markerTitle = JSON.stringify({id: placeId, name: placeName});
            var marker = L.marker([place.lat, place.long], {title: markerTitle});
            marker.bindPopup('Polling place: ' + placeName);
            marker.on('click', function(e) {
              var markerData = JSON.parse(this.options.title);
              var id = markerData.id;
              renderPollingPlaceData(id);
            });
            markerGroup.addLayer(marker);
          }
        }
      }
    });
    map.addLayer(markerGroup); 

  };   
  
  // get data on marker click
  var renderPollingPlaceData = function(id) {
    infoTableBody.empty();
    votesTableBody.empty();
    var pp = pollingPlaces.filter(function(k) {return k.polling_place_id == id;});
    renderPollingPlaceInfoTable(pp[0]);
    $.ajax({
      url: './api/v1/votes2016/' + id,
      type: 'get',
      success: function(json) {
        renderVotingDataTable(json.data);
      }
    }); 
  };
  
  // table builder functions
  var renderPollingPlaceInfoTable = function(info) {

    var html = '<table class="table table-condensed table-hover">';
    html += '<tbody>';
    html += '<tr>';
    html += '<th>Id</th>';
    html += '<td>' + info.polling_place_id + '</td>';
    html += '</tr>';
    html += '<tr>';
    html += '<th>Name</th>';
    html += '<td>' + info.polling_place_name + '</td>';
    html += '</tr>';
    html += '<tr>';
    html += '<th>Division</th>';
    html += '<td>' + info.division_name + '</td>';
    html += '</tr>';
    html += '<tr>';
    html += '<th>Address</th>';
    html += '<td>' + info.address1 + '</td>';
    html += '</tr>';
    html += '</tbody>';
    html += '</table>';
    
    infoTable.html(html);
    
  };
  
  var renderVotingDataTable = function(info) {
    
    var html, rgb;
    // clear existing rows
    votesTableBody.empty();
    
    // add rows from data
    $.each(info, function(index, item) {
      html = '<tr>';
      html += '<td>' + item.candidate_name + '</td>';
      html += '<td>' + item.party_name + '</td>';
      html += '<td>' + item.party_group_code + '</td>';
      if (item.party_group_rgb == null) {
        rgb = '#ffffff';
      } else {
        rgb = item.party_group_rgb;
      }
      html += '<td><span class="glyphicon glyphicon-stop" style="color:' + rgb + '"></span></td>';        
      if (item.elected == 1) {
        html += '<td><span class="glyphicon glyphicon-ok"></span></td>';        
      } else {
        html += '<td></td>';
      }
      html += '<td>' + item.votes + '</td>';
      html += '</tr>';
      votesTableBody.append(html);     
    });
  };
  
  // return public methods from module
  return {
    init: init,
    map: getMap
  };

})();


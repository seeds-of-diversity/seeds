<html>
  <head>
    <title>JQVMap - World Map</title>
    <link href="../../vendor/10bestdesign/jqvmap/dist/jqvmap.css" media="screen" rel="stylesheet" type="text/css">

    <script type="text/javascript" src="http://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script type="text/javascript" src="../../vendor/10bestdesign/jqvmap/dist/jquery.vmap.js"></script>
    <script type="text/javascript" src="../../vendor/10bestdesign/jqvmap/dist/maps/jquery.vmap.world.js" charset="utf-8"></script>
    <script src="../../vendor/10bestdesign/jqvmap/dist/maps/jquery.vmap.canada.js"></script>

    <script type="text/javascript">
    jQuery(document).ready(function() {
      jQuery('#vmap').vectorMap({
          map: 'canada_en',
          backgroundColor: null,
          color: '#c23616',
          hoverColor: '#999999',
          enableZoom: false,
          showTooltip: true,
          onRegionClick: function(element, code, region)
          {
              jQuery('#selectedRegion').html(region);
//              var message = 'You clicked "'
//                  + region
//                  + '" which has the code: '
//                  + code.toUpperCase();
//
//              alert(message);
          }
      });
    });
    </script>
  </head>
  <body>
     <div id="vmap" style="width: 600px; height: 400px;"></div>
     <h3 id="selectedRegion"></h3>
  </body>
</html>






<!-- Example Map Above  -->

<!--
        <script src="../../wcore/os/jquery/jquery-3-3-1.min.js"></script>
        <script src="../../vendor/10bestdesign/jqvmap/dist/jquery.vmap.js"></script>
        <script src="../../vendor/10bestdesign/jqvmap/dist/maps/jquery.vmap.canada.js"></script>

        <script>
        jQuery('#vmap').vectorMap({
            map: 'canada_en',
            backgroundColor: null,
            color: '#c23616',
            hoverColor: '#999999',
            enableZoom: false,
            showTooltip: false
        });
        </script>

        <div id="vmap" style="width: 600px; height: 400px;"></div>

-->



<button id='mybutton' onclick='doButton()'>Press Here</button>
<div id='buttonTarget'>Before</div>
<style>
#map-container {
  overflow: hidden;
  /* 930:720 aspect ratio */
  padding-top: 77.42%;
  position: relative;
}

#map-container iframe {
   border: 0;
   height: 100%;
   left: 0;
   position: absolute;
   top: 0;
   width: 100%;
}
</style>


<div id="map-container">
  <iframe src="https://seeds.ca/app/ev/iframe.php?mode=map" title="Seedy Saturdays and Seedy Sundays Across Canada" style=""></iframe>
</div>
<style>
#calendar-container {
  overflow: hidden;
  /* 930:720 aspect ratio */
  padding-top: 77.42%;
  position: relative;
}

#calendar-container iframe {
   border: 0;
   height: 100%;
   left: 0;
   position: absolute;
   top: 0;
   width: 100%;
}
</style>
<div id="calendar-container">
  <iframe src="https://seeds.ca/app/ev/iframe.php?mode=calendar" title="Seedy Saturdays and Seedy Sundays Across Canada" style=""></iframe>
</div>
<div id="map-container">
  <iframe src="https://seeds.ca/app/ev/iframe.php?mode=map" title="Seedy Saturdays and Seedy Sundays Across Canada" style=""></iframe>
</div>
<style>
#calendar-container {
  overflow: hidden;
  /* 930:720 aspect ratio */
  padding-top: 77.42%;
  position: relative;
}

#calendar-container iframe {
   border: 0;
   height: 100%;
   left: 0;
   position: absolute;
   top: 0;
   width: 100%;
}
</style>
<div id="calendar-container">
  <iframe src="https://seeds.ca/app/ev/iframe.php?mode=calendar" title="Seedy Saturdays and Seedy Sundays Across Canada" style=""></iframe>
</div>

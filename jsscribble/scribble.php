<!DOCTYPE html>
<?php
  session_start(); // repeatedly calling this should be OK i.e. we should keep the same session.
  $boardName = "Test board";
  if (isset($_POST["boardname"])) {
    $boardName = $_POST["boardname"];
  } elseif (isset($_GET["boardname"])) {
    $boardName = $_GET["boardname"];
  }
      
  if ($_GET)
?>

<html>
  <head>
      <meta http-equiv="Content-type" content="text/html; charset=utf-8">
      <title>Scribble</title>
      <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
      <link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">
      <!-- <link rel="stylesheet" href="reset.css"> -->
      <link rel="stylesheet" href="scribble.css?v=2">
      <link rel='shortcut icon' href='favicon.ico' type='image/x-icon'/ >
<script>
    $(scribble);

function scribble()	{
    "use strict";
    
    var debug = true;
    // this is the same as document.getElementById('canvas');
    var canvas = $('#canvas')[0];
    // different browsers support different contexts. All support 2d
    var context = canvas.getContext('2d');
    var eventLoopDelay = 1000/60; // delay in millisecs
    var initialMouseX = null;
    var initialMouseY = null;
    var initialDraggingItem = null;
    var penColor = "black";
    var penWidth = 1; 
    var penDown = false;
    var drawPrevX = null;
    var drawPrevY = null;
    var segment = null;
    var otherClientSegmentLatestDate = "1900-01-01T00:00:00";
    
    var message = "";
    var canvasBoundingRect = canvas.getBoundingClientRect();
    
    function log(msg) {
      if (debug) {
        console.log(msg);
      }
    }
    
    // See: http://stackoverflow.com/questions/20857593/canvas-mouse-event-position-different-than-cursor
    function getMouseX(evt) {
      return evt.clientX - canvasBoundingRect.left;
    }
    
    function getMouseY(evt) {
      return evt.clientY - canvasBoundingRect.top;
    }
    
    function initScribble() {
      
      getAllSegments();
      var nowISO = (new Date()).toISOString(); 
      otherClientSegmentLatestDate = nowISO;
      var reloadOtherClientInterval = setInterval(getSegmentsFromOtherClients, 1000);
      
      pickBluePencil();
    }

    function getAllSegments() {
        $.ajax({
          type: "GET",
          url: "GetData.php",
          dataType: "json",
          data: {BoardID : $('#boardID').val()},
          success : getSegmentsSuccess,
          error : getSegmentsError
        });
    }
    
    function clearAllSegments() {
      // Clear the database records for this board.
      $.ajax({
        type: "GET",
        url: "ClearData.php",
        dataType: "json",
        data: {BoardID : $('#boardID').val()},
        success : clearSegmentsSuccess,
        error : clearSegmentsError
      });
    }
    
    function clearSegmentsSuccess() {
      sendClearMessage();
    }
      
    function sendClearMessage() {
        // Send message so that all other clients can be cleared as well.
        segment = Segment.createClearSegment();
        sendSegment(segment);
    }
    
    function clearSegmentsError() {
              alert('ajax clearAllSegments() call failed, status: ' + status);
    }
    
    function getSegmentsSuccess(data, status) {
      var segmentsStrArr = data.segments; // array of strings containing JSON for the segment.
      if (segmentsStrArr.length == 0) {
        return;
      }
      
      var segmentsArr = [];
      for (var i = 0; i < segmentsStrArr.length - 1; i++) {
        var segment = JSON.parse(segmentsStrArr[i]);
        segmentsArr.push(segment);
      }
      
      // The creation date of the final segment is tacked onto the data. 
      otherClientSegmentLatestDate = segmentsStrArr[segmentsStrArr.length - 1];
      
      for (var i = 0; i < segmentsArr.length; i++) {
        var segment = segmentsArr[i];
        var penColor = segment.penColor;
        var penWidth = segment.penWidth;  
        var prevCoord = null;
        
        var action = segmentsArr[i].action;
        if (action == "clear") {
          clearCanvas();
        }
        else if (action == "draw") {
          var coordsArr = segment.coords;
          for (var j = 0; j < coordsArr.length; j++) {
            if (j == 0) {
              prevCoord = coordsArr[j];
              continue;
            }
            var coord = coordsArr[j];                    
            drawLine(prevCoord[0], prevCoord[1], coord[0], coord[1], penColor, penWidth);
            prevCoord = [coord[0], coord[1]];
          }
        }        
      }
    }
      
    function getSegmentsError(jqXHR, status, error) {
      alert('ajax getSegmentsFromOtherClients() call failed, status: ' + status);
    }
    
    function getSegmentsFromOtherClients() {
      $.ajax({
      type: "GET",
      url: "GetData.php",
      dataType: "json",
      data: {BoardID : $('#boardID').val(), ClientID : $('#clientID').val(), BeginDate : otherClientSegmentLatestDate},
      success : getSegmentsSuccess,
      error : getSegmentsError
      });
    }
      
    $('#canvas').mousedown(function(evt) {
      log('mousedown');
      
      // Prevent cursor changing to an I-beam text selection cursor in Chrome.
      // http://stackoverflow.com/questions/2659999/html5-canvas-hand-cursor-problems
      evt.preventDefault();
      evt.stopPropagation();
      
      penDown = true;
      segment = Segment.createDrawSegment(penColor, penWidth);
      
      drawPrevX = getMouseX(evt);
      drawPrevY = getMouseY(evt);
      
      segment.addCoord([drawPrevX, drawPrevY]);
    });
    
    $('#canvas').mousemove(function(evt) {
      log('mousemove');
      
      if (penDown) {
        draw(evt);
      }
    });

    $('#canvas').mouseup(function(evt) {
      log('mouseup');
      
      if (penDown) {
        sendSegment(segment);
      } 

      penDown = false;
    });

    $('#tb-eraser').click(function (evt) {
      penColor = "white";
      penWidth = "50";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("eraser");
    });

    $('#tb-black-pencil').click(function (evt) {
      penColor = "black";
      penWidth = "1";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("black-pencil");
    });
    
    $('#tb-blue-pencil').click(function (evt) {
      penColor = "blue";
      penWidth = "1";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("blue-pencil");
    });
    
    function pickBluePencil() {
      penColor = "blue";
      penWidth = "1";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("blue-pencil");
    }
    
  $('#tb-red-pencil').click(function (evt) {
    penColor = "red";
    penWidth = "1";
    clearToolbarHighlights();
    $(this).addClass("selected");
    $('#canvas').removeClass().addClass("red-pencil");
  });

    $('#tb-green-pencil').click(function (evt) {
      penColor = "green";
      penWidth = "1";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("green-pencil");
    });
    
    $('#tb-black-paintbrush').click(function (evt) {
      penColor = "black";
      penWidth = "3";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("black-paintbrush");
    });

    $('#tb-blue-paintbrush').click(function (evt) {
      penColor = "blue";
      penWidth = "3";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("blue-paintbrush");
    });

    $('#tb-red-paintbrush').click(function (evt) {
      penColor = "red";
      penWidth = "3";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("red-paintbrush");
    });

    $('#tb-green-paintbrush').click(function (evt) {
      penColor = "green";
      penWidth = "3";
      clearToolbarHighlights();
      $(this).addClass("selected");
      $('#canvas').removeClass().addClass("green-paintbrush");
    });

    $('#tb-mouse-pointer').click(function (evt) {
      penDown = false;
    });
    
    $('#tb-clear').click(function (evt) {
      var confirmed = true; // confirm("Clear the entire board?");
      if (confirmed) {
        clearCanvas();
        clearAllSegments();
      }
    });
    
    $('#btnSetBoardName').click(function (evt) {
      submitFormWithBoardName();         
      // clearCanvas();
      // getAllSegments();
    });
      
    $('#boardID').keypress(function(e) {
        if (e.which == 13) {
          submitFormWithBoardName();         
          return false; <?php // prevent double submit ?>  
        }
    });
    
    function submitFormWithBoardName() {
      var mainForm = $('#mainForm');
      mainForm.attr("action", "scribble.php?boardname=" + $('#boardID').val());
      mainForm.submit();
    }
    
    function sendSegment(segment) {
        var boardID = $('#boardID').val();
        var clientID = $('#clientID').val(); // replace with <?php session_id() ?> when done debugging.

        sendSegmentData(boardID, clientID, segment);
    }
    
    function clearCanvas() {
      context.clearRect(0, 0, canvas.width, canvas.height);
    }
    
    function sendSegmentData(boardID, clientID, segment) {
      $.ajax({
        type: "POST",
        url: "StoreData.php",
        data: {BoardID : boardID, ClientID : clientID, SegmentData : JSON.stringify(segment)},
        success : sendSegmentSuccess,
        error : sendSegmentError
      });
    }
    
    function sendSegmentSuccess() {
      console.log("sendSegmentData success.");
    }
    
    function sendSegmentError(xhr, ajaxOptions, thrownError) {
      alert("sendSegmentData error: " + thrownError);
    }
    
    function clearToolbarHighlights() {
      $('#toolbar td').removeClass("selected");
    }
    
    // See http://stackoverflow.com/questions/2368784/draw-on-html5-canvas-using-a-mouse
    function draw(evt) {
      var x = getMouseX(evt);
      var y = getMouseY(evt);
      if (x == drawPrevX && y == drawPrevY) {
        return; // Only draw if the position has changed.
      }
      
      drawLine(x, y, drawPrevX, drawPrevY, penColor, penWidth);
      
      segment.addCoord([x,y]);
      
      drawPrevX = x;
      drawPrevY = y;
    }
    
    function drawLine(x1, y1, x2, y2, penColor, penWidth) {
      context.beginPath();
      context.moveTo(x1, y1);
      context.lineTo(x2, y2);
      context.strokeStyle = penColor;
      context.lineWidth = penWidth;
      context.stroke();
      context.closePath();
    }
    
    // See http://stackoverflow.com/questions/5767325/remove-a-particular-element-from-an-array-in-javascript.
    // Note: IE 8 and below don't support indexOf, see the above link for a polyfill if needed.
    function removeItem(item) {
      var index = items.indexOf(item);
      if (index > -1) {
        items.splice(index, 1);
      }
    }
    
    function removeSelectedItem() {
      if (!selectedItem) {
        return;
      }
      
      removeItem(selectedItem);
    }
    
    function Segment() {
    }

    Segment.createDrawSegment = function(penColor, penWidth) {
      var s = new Segment();
      s.action = "draw";
      s.penColor = penColor;
      s.penWidth = penWidth;
      s.coords = [];
      
      return s; 
    }
    
    Segment.createClearSegment = function() {
      var s = new Segment();
      s.action = "clear";
      
      return s;
    }
    
    Segment.prototype.addCoord = function(c) {
      this.coords.push(c);
    }
    
    var imageLoader = document.getElementById('imageLoader');
    imageLoader.addEventListener('change', handleImage, false);
    
    // Apparently triggering one click from another only works if the initial click is initiated by the user. See response by Fazi: http://stackoverflow.com/questions/8595389/programmatically-trigger-select-file-dialog-box
    $('#btnCustomFileUpload').click(function(evt) {
      $('#imageLoader').click();
    })
    
    // See the response by DerekR: http://stackoverflow.com/questions/10906734/how-to-upload-image-into-html5-canvas 
    function handleImage(e){
        var reader = new FileReader();
        reader.onload = function(event){
            var img = new Image();
            img.onload = function(){
                //canvas.width = img.width;
                //canvas.height = img.height;
                context.drawImage(img,0,0);
            }
            img.src = event.target.result;
        }
        reader.readAsDataURL(e.target.files[0]);     
    }    
    
    initScribble();
  }
    </script>
  </head>

  <body>   
    <form id="mainForm" method="post" action="scribble.php">
      <div id="topToolbar">
        <strong>Board Name:</strong>  
        <input type="text" id="boardID" name="boardname" value="<?php echo $boardName;?>">  
        <button type="button" id="btnSetBoardName">Go</button> <?php // type="button" makes the button not submit the form ?>
        
        <input id="btnCustomFileUpload" type="button" value="Upload an image">
        <input type="hidden" id="clientID" name="ClientID" value="<?php echo session_id(); ?>"></input>
      </div>
    </form>
    
    <input type="file" id="imageLoader" name="imageLoader" class="hidden">


    <canvas id="canvas" width="1200px" height="800px">
      Sorry, your browser does not support the HTML5 Canvas feature :-(
    </canvas>  
      
    <div id="toolbar"> 
      <table>
        <tr>
          <td id="tb-clear" class="hover-highlight" >
            <i class="fa fa-times fa-2x"></i><br>
            Clear
          </td>
        </tr>
        <tr>
          <td id="tb-eraser" class="hover-highlight" >
            <i class="fa fa-eraser fa-2x"></i><br>
            Eraser
          </td>
        </tr>
        <tr>
          <td id="tb-black-pencil" class="hover-highlight" >
            <i id="black-pencil" class="fa fa-pencil fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-blue-pencil" class="hover-highlight blue" >
            <i id="blue-pencil" class="fa fa-pencil fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-red-pencil" class="hover-highlight red" >
            <i id="red-pencil" class="fa fa-pencil fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-green-pencil" class="hover-highlight green" >
            <i id="green-pencil" class="fa fa-pencil fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-black-paintbrush" class="hover-highlight" >
            <i id="black-paintbrush" class="fa fa-paint-brush fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-blue-paintbrush" class="hover-highlight blue" >
            <i id="blue-paintbrush" class="fa fa-paint-brush fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-red-paintbrush" class="hover-highlight red" >
            <i id="red-paintbrush" class="fa fa-paint-brush fa-2x"></i><br>
          </td>
        </tr>
        <tr>
          <td id="tb-green-paintbrush" class="hover-highlight green" >
            <i id="green-paintbrush" class="fa fa-paint-brush fa-2x"></i><br>
          </td>
        </tr>
      </table>
    </div>
  </body>
</html>

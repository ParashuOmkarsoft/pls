<?php
function groupon_csv_import(){ ?>
<h3>Groupon Import</h3>
<ul>
  <li>
    <p>This page is for importing your events from a comma seperated file (CSV) directly into the the events database.  The limitation of this upload is that it does not support the extra questions, only the core event configuration. </p>
    <ul>
      <li>Please use Y where you want to say Yes and N where you want No.</li>
      <li>Dates should be formatted YYYY-MM-DD (2009-07-04).</li>
      <li>I have included a template file <a href="<?php echo EVENT_ESPRESSO_PLUGINFULLURL ?>events.csv">here</a> that I recommend you download and use.  It is very easy to work with it in excel, just remember to save it as a csv and not excel sheet.</li>
      <li>The file name should be events.csv in order for it to work. I will fix this issue later, I just wanted to get this working first.</li>
    </ul>
    <p>One final note, you will see that the header row, fist column has a 0 while other rows have a 1.  This tells the upload to ignore rows that have the 0 identifier and only use rows with the 1.</p>
    <p>This is the first pass at the uploader, but for those of you who have alot of events, particularly events that are similar in setup, this will be a time saver.</p>
    <?php
uploader();
load_events_to_db();
 ?>
 </li>
</ul>
<?php }
/*
uploader([int num_uploads [, arr file_types [, int file_size [, str upload_dir ]]]]); 

num_uploads = Number of uploads to handle at once. 

file_types = An array of all the file types you wish to use. The default is txt only. 

file_size = The maximum file size of EACH file. A non-number will results in using the default 1mb filesize. 

upload_dir = The directory to upload to, make sure this ends with a / 
*/ 
function uploader($num_of_uploads=1, $file_types_array=array("csv"), $max_file_size=1048576, $upload_dir="../wp-content/uploads/"){
  if(!is_numeric($max_file_size)){
    $max_file_size = 1048576; 
  }
  if(!isset($_POST["submitted"])){
    $form = "<form action='".$PHP_SELF."' method='post' enctype='multipart/form-data'><p>Upload files:</p><input type='hidden' name='submitted' value='TRUE' id='".time()."'><input name='action' type='hidden' value='csv_import' /><input type='hidden' name='MAX_FILE_SIZE' value='".$max_file_size."'>"; 
    for($x=0;$x<$num_of_uploads;$x++){ 
      $form .= "<p><font color='red'>*</font><input type='file' name='file[]'>"; 
    }
    $form .= "<input class='button-primary' type='submit' value='Upload File & Add Vouchers(s)'></p><p><font color='red'>*</font>Maximum file name length (minus extension) is 15 characters. Anything over that will be cut to only 15 characters. Valid file type(s): "; 
    for($x=0;$x<count($file_types_array);$x++){ 
      if($x<count($file_types_array)-1){ 
        $form .= $file_types_array[$x].", "; 
      }else{
        $form .= $file_types_array[$x].".</p>";
   }
    }
    $form .= "</form>";
    echo($form);
  }else{
    foreach($_FILES["file"]["error"] as $key => $value){
      if($_FILES["file"]["name"][$key]!=""){
        if($value==UPLOAD_ERR_OK){
          $origfilename = $_FILES["file"]["name"][$key];
          $filename = explode(".", $_FILES["file"]["name"][$key]);
          $filenameext = $filename[count($filename)-1];
          unset($filename[count($filename)-1]);
          $filename = implode(".", $filename);
          $filename = substr($filename, 0, 15).".".$filenameext;
          $file_ext_allow = FALSE;
          for($x=0;$x<count($file_types_array);$x++){
            if($filenameext==$file_types_array[$x]){
              $file_ext_allow = TRUE;
            }
          }
          if($file_ext_allow){ 
            if($_FILES["file"]["size"][$key]<$max_file_size){ 
              if(move_uploaded_file($_FILES["file"]["tmp_name"][$key], $upload_dir.$filename)){
                echo("<p>File uploaded successfully. - <a href='".$upload_dir.$filename."' target='_blank'>".$filename."</a></p>");
              }else{
                echo($origfilename." was not successfully uploaded<br />");
              } 
            }else{ 
              echo($origfilename." was too big, not uploaded<br />");
            }
          }else{
            echo($origfilename." had an invalid file extension, not uploaded<br />");
          }
        }else{
          echo($origfilename." was not successfully uploaded<br />");
        }
      }
    }
  }
}

function load_events_to_db(){
	global $wpdb;
	//$events_detail_tbl = get_option ( 'events_detail_tbl' );
	$curdate = date ( "Y-m-j" );
	$month = date ('M');
	$day = date('j');
	$year = date('Y');

	$fieldseparator = ",";
	$lineseparator = "\n";
	$csvfile = "../wp-content/uploads/groupons.csv";

 function getCSVValues($string, $separator=";"){
	 global $wpdb;
	 //$wpdb->show_errors();
        $elements = explode($separator, $string);
        
        for ($i = 0; $i < count($elements); $i++) 
        {
            $nquotes = substr_count($elements[$i], '"');
            
            if ($nquotes %2 == 1)
            {
                for ($j = $i+1; $j < count($elements); $j++)
                {
                    if (substr_count($elements[$j], '"') > 0) 
                    {
                        // Put the quoted string's pieces back together again
                        array_splice($elements, $i, $j-$i+1,
                        implode($separator, array_slice($elements, $i, $j-$i+1)));
                        break;
                    }
                }
            }
            
            if ($nquotes > 0) 
            {
                // Remove first and last quotes, then merge pairs of quotes
                $qstr =& $elements[$i];
                $qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
                $qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
                $qstr = str_replace('""', '"', $qstr);
            }
        }
        
        return $elements;
    }
    
	if(!file_exists($csvfile)) {
		echo "File not found. Make sure you specified the correct path.\n";
		exit;
	}
	
	$file = fopen($csvfile,"r");
	
	if(!$file) {
		echo "Error opening data file.\n";
		exit;
	}
	
	$size = filesize($csvfile);
	
	if(!$size) {
		echo "File is empty.\n";
		exit;
	}
   
    $file = file_get_contents($csvfile);
    $dataStrings = explode("\r", $file);
    
    $i = 0;
    foreach ( $dataStrings as $data ){
	++$i; 

    for ( $j = 0; $j < $i; ++$j )
        $strings = getCSVValues( $dataStrings[$j] );
        
    print_r( $strings );
    if (array_key_exists('2', $strings)) {
    //echo "The  element is in the array";
		//$skip = $strings[0];
		
		//if ($skip >= "1"){
			//Add event data
			$g_sql = "INSERT INTO " . EVENTS_GROUPON_CODES_TABLE . " (groupon_code, groupon_holder)";
			$g_sql .= " VALUES ('$strings[1]', '$strings[2]')";
			echo $g_sql;
			$wpdb->query ( $wpdb->prepare( $g_sql ) );
			$last_g_id = $wpdb->insert_id;
			print $wpdb->print_error();
		//}
	}
}   


	unlink($csvfile);
	if(!file_exists($csvfile)) {
		echo "<br>
Upload file has been deleted.<br>";
	
	}
	$tot_records = $i - "2";
	echo "Added a total of $tot_records events to the database.<br>";
}
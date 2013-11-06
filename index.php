<?php

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

$message = "";
$message_class = "";

// Constant Variables
define('TII_ENCRYPT', 0);
define('TII_DIAGNOSTIC', 1);
define('UPLOAD_DIRECTORY', dirname(__FILE__).'/uploaded_files/');

// Turnitin Login details
define('TII_SRC', 0);
define('TII_AID', 0);
define('TII_KEY', '');
define('TII_APIURL', 'https://sandbox.turnitin.com/api.asp');

$tii_variables_not_to_post = array("submit", "api_function", "attached_filename", "paper_ids");

class TIIClass {

	function getFuncSpecVars($api_function) {
		// Split the posted api function variable to get function id and command
		$function_vars = explode("_", $api_function);
		$function_vars['fid'] = (int)$function_vars['0'];
		$function_vars['fcmd'] = (int)$function_vars['1'];
		return $function_vars;
	}

	function getIdSync($fid, $fcmd) {
		$idsync = 0;
		if (($fid == 1) || (($fid == 2 || $fid == 4 || $fid == 5 || $fid == 6) && ($fcmd == 1 || $fcmd == 2))) {
			$idsync = 1;
		}
		return $idsync;
	}

	function getGMT() {
		$gmt = gmdate("YmdHi");
		$gmt = substr($gmt, 0, strlen($gmt)-1);
		return $gmt;
	}

	function postToQueryString ($posted_vars) {
		// Put the posted variables into a query string
		global $tii_variables_not_to_post;
		$query_string = "?";
		foreach ($posted_vars as $k => $v) {
			if (!in_array($k, $tii_variables_not_to_post)) {
				$query_string .= $k."=".$v."&";
			}
		}
		$query_string = substr($query_string, 0, strlen($query_string)-1);
		return $query_string;
	}

	function generateMd5($posted_vars) {
		global $tii_variables_not_to_post;
		$not_in_md5_string = array_merge($tii_variables_not_to_post, array("src", "new_teacher_email", "session-id", "idsync", "pdata", "starttime", "score", "max_points", "attached_file", "allPapers", "export_data", "create_session"));
		ksort($posted_vars);
		$md5_string = "";
		foreach ($posted_vars as $k => $v) {
			if (!in_array($k, $not_in_md5_string)) {
				//echo $k." - ".$v."<br/>";
				$md5_string .= $v;
			}
		}
		$md5_string .= TII_KEY;
		return md5($md5_string);
	}

	function getMessageFromXML($xml_string, $fid, $fcmd) {
		echo "XML string: ".$xml_string;
		$xml = simplexml_load_string($xml_string);
		$message = $xml->rmessage;
		if ($fid == 6 && $fcmd == 2) {
			$message = "Originality score is ".$xml->originalityscore."%";
		}
		if ($fid == 10) {
			$i = 0;
			$table_row = "";
			foreach ($xml->object as $submission) {
				$i++;
				$table_row .= "<tr><td>".$submission->objectID."</td><td>".$submission->userid."</td><td>".$submission->title."</td><td>".$submission->firstname." ".$submission->lastname."</td></tr>";
			}
			$message = "<p>There have been ".$i." submissions for this assignment.</p>";
			$message .= "<table border='1' cellpadding='0' cellspacing='0'>";
			$message .= "<tr><th>Submission Id</th><th>User Id</th><th>Title</th><th>Name</th></tr>";
			$message .= $table_row;
			$message .= "</table>";
		}
		if ($fid == 13 && $fcmd == 2) {
			$message = "Paper has been graded";
			if ($xml->markup_exists == 0) {
				$message = "Paper has not been graded";
			}
		}
		if ($fid == 14) {
			$message = "Average turnaround time for 15 minutes is ".$xml->averagetime15." seconds<br/>";
			$message .= "Average turnaround time for 10 minutes is ".$xml->averagetime10." seconds<br/>";
			$message .= "Average turnaround time for 5 minutes is ".$xml->averagetime5." seconds<br/>";
			$message .= "Average turnaround time for 1 minutes is ".$xml->averagetime1." seconds";
		}
		if ($fid == 15 && $fcmd == 2) {
			$message = "Grade for paper is ".$xml->score;
		}
		if ($fid == 19 && $fcmd == 4) {
			if ($xml->enrollment->is_instructor == 1) {
				$message = "User is the instructor of this class";
			} else if ($xml->enrollment->is_student == 1) {
				$message = "User is a student in this class";
			} else {
				$message = "User role is undefined";
			}
		}
		if ($fid == 19 && $fcmd == 5) {
			$instructor_table_rows = "";
			$student_table_rows = "";
			foreach ($xml->instructors->instructor as $instructor) {
				$instructor_table_rows .= "<tr><td>".$instructor->userid."</td><td>".$instructor->email."</td><td>".$instructor->firstname." ".$instructor->lastname."</td></tr>";
			}
			foreach ($xml->students->student as $student) {
				$student_table_rows .= "<tr><td>".$student->userid."</td><td>".$student->email."</td><td>".$student->firstname." ".$student->lastname."</td></tr>";
			}
			$message = "<p>Class Details (ID: ".$xml->classid.")</p>";
			$message .= "<table border='1' cellpadding='0' cellspacing='0'>";
			$message .= "<tr><th colspan='3'>Instructor(s)</th></tr>";
			$message .= "<tr><th>User Id</th><th>Email</th><th>Name</th></tr>";
			$message .= $instructor_table_rows;
			$message .= "<tr><th colspan='3'>Student(s)</th></tr>";
			$message .= "<tr><th>User Id</th><th>Email</th><th>Name</th></tr>";
			$message .= $student_table_rows;
			$message .= "</table>";
		}
		if ($fid == 20 && $fcmd == 2) {
			$message = "You have ".$xml->newMessages." new message(s) and ".$xml->totalMessages." total message(s)";
		}
		return $message;
	}

	function uploadFile($file) {
		if (!empty($file['name'])) {
			// check for image submitted
    		if ($file['error'] > 0) {
				// check for error re file
            	echo "Error: " . $file["error"] ;
       		} else {
				// move temp file to our server
				move_uploaded_file($file['tmp_name'], UPLOAD_DIRECTORY.$file['name']);
				echo 'Uploaded File.';
        	}
    	} else {
	        echo 'File not uploaded.';
			// exit script
    	}
    	return $file['name'];
	}

	function savePaperIdsAsCSV($paper_ids) {
		$filename = "file_".date("dmy_His").".csv";
		$file = fopen(UPLOAD_DIRECTORY.$filename, 'w');
		$paper_ids_array = explode(",", $paper_ids);
		//fputcsv($file, $paper_ids_array);
		foreach ($paper_ids_array as $paper_id){
			fwrite($file, $paper_id."\r\n");
		}
		//fwrite($file, $paper_ids);
		//fwrite($file, "\r\n");
		fclose($file);
		return $filename;
	}

	function doRequest($posted_vars, $method = "get") {
		$query_string = $this->postToQueryString($posted_vars);
		echo "Query String: ".$query_string."<br/>";
		if (($posted_vars['fid'] == 6 && $posted_vars['fcmd'] == "1") || $posted_vars['fid'] == 7 || ($posted_vars['fid'] == 13 && ($posted_vars['fcmd'] == "1" || $posted_vars['fcmd'] == "3"))) {
			?>
			<script type="text/javascript">
			<!--
				window.open('<?php echo TII_APIURL.$query_string; ?>');
 			//-->
 			</script>
 			<?php
		} else if ($posted_vars['fcmd'] == "1") {
			header("Location: ".TII_APIURL.$query_string);
			exit;
		} else {
			if ($method == "get") {
				if (TII_DIAGNOSTIC == 1) {
					$message = file_get_contents(TII_APIURL.$query_string);
				} else {
					$xml = simplexml_load_file(TII_APIURL.$query_string);
					$message = $this->getMessageFromXML($xml, $posted_vars['fid'], $posted_vars['fcmd']);
				}
			} else {

				//print_r($posted_vars);
				//echo "<br/>";

				// open connection
				$ch = curl_init();

				// set the url, number of POST vars, POST data
				curl_setopt($ch, CURLOPT_URL, TII_APIURL);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    			curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $posted_vars);

				// execute post
				$result = curl_exec($ch);

				//print_r(curl_getinfo($ch));

				// close connection
				curl_close($ch);

				if (TII_DIAGNOSTIC == 1) {
					$message = $result;
				} else {
					$message = $this->getMessageFromXML($result, $posted_vars['fid'], $posted_vars['fcmd']);
				}
			}
		}

		return $message;
	}

	function getMessageClass($message) {
		$message_class = "";
		if (strstr($message, "successful") || strstr($message, "Successful")) {
			$message_class = "success";
		} else {
			$message_class = "error";
		}
		return $message_class;
	}

	function removeBlankValues($posted_vars) {
        foreach($posted_vars as $k => $v) {
            if (trim($v) != "") {
                $new_posted_vars[$k] = $v;
            }
        }
        return $new_posted_vars;
    }
}

if (isset($_POST["submit"])) {
	$tii = new TIIClass();
	$_POST=$tii->removeBlankValues($_POST);

	// Get function variables
	$function_vars = $tii->getFuncSpecVars($_POST['api_function']);
	$_POST['fid'] = $function_vars['fid'];
	$_POST['fcmd'] = $function_vars['fcmd'];

	// Define idsync variable
	$idsync = $tii->getIdSync($_POST['fid'], $_POST['fcmd']);
	$_POST['idsync'] = $idsync;

	// Save File
	if ($_POST['fid'] == 5 && $_POST['fcmd'] == 2) {
    	$_POST['ptype'] = 2;
    	$_POST['pdata'] = '@'.UPLOAD_DIRECTORY.$tii->uploadFile($_FILES["paper"]);
    }
    // Save paper ids in csv file
    if ($_POST['fid'] == 21) {
    	$_POST['attached_file'] = '@'.UPLOAD_DIRECTORY.$tii->savePaperIdsAsCSV($_POST["paper_ids"]).';type=text/csv';
    }

	// Configure other POST variables
	$_POST['gmtime'] = $tii->getGMT();
	$_POST['aid'] = TII_AID;
	$_POST['diagnostic'] = TII_DIAGNOSTIC;
	$_POST['src'] = TII_SRC;
	$_POST['allPapers'] = 1;
	$_POST['export_data'] = 1;
	$_POST['encrypt'] = TII_ENCRYPT;
	$_POST['md5'] = $tii->generateMd5($_POST);

	$method = "post";

	//print_r($_POST);
	//echo "<br/>";

	$message = $tii->doRequest($_POST, $method);
	$message_class = $tii->getMessageClass($message);
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<title>Turnitin Legacy API Tester</title>
	<link href="css/styles.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/functions.js"></script>
</head>

<body>
	<form id="form1" name="form1" method="post" action="" enctype="multipart/form-data">
		<?php
		if ($message != "") {
		?>
			<div class="message <?php echo $message_class;?>">
				<?php echo $message; ?>
			</div>
		<?php
		}
		?>

  		<div class="formrow">
  			<label for="api_function">Function:</label>
  			<select name="api_function" id="api_function" onchange="showBoxes();">
  				<option value=""></option>
  				<option value="1_1">Create user &amp; log them in (fid=1, fcmd=1)</option>
  				<option value="1_2">Create user (fid=1, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="2_1">Create class &amp; assign user as instructor &amp; log them in (fid=2, fcmd=1)</option>
  				<option value="2_2">Create class &amp; assign user as instructor (fid=2, fcmd=2)</option>
  				<option value="2_3">Update class end date (fid=2, fcmd=3)</option>
  				<option value="2_4">Switch class instructor (fid=2, fcmd=4)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="3_1">Join class &amp; log user in (fid=3, fcmd=1)</option>
  				<option value="3_2">Join class (fid=3, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="4_1">Create assignment &amp; log instructor in (fid=4, fcmd=1)</option>
  				<option value="4_2">Create assignment (fid=4, fcmd=2)</option>
  				<option value="4_3">Modify assignment (fid=4, fcmd=3)</option>
  				<option value="4_4">Delete assignment (fid=4, fcmd=4)</option>
  				<option value="4_5">Modify assignment &amp; log instructor in (fid=4, fcmd=5)</option>
  				<option value="4_7">Get assignment details (fid=4, fcmd=7)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="5_1">Submit paper &amp; go to submission screen (fid=5, fcmd=1)</option>
  				<option value="5_2">Submit paper (fid=5, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="6_1">Get originality report (fid=6, fcmd=1)</option>
  				<option value="6_2">Get originality report score (fid=6, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="7_1">View submission (fid=7, fcmd=1)</option>
  				<option value="7_2">Download submission (fid=7, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="8_2">Delete submission (fid=8, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="9_2">Change user password (fid=9, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="10_1">List submissions &amp; go to inbox (fid=10, fcmd=2)</option>
  				<option value="10_2">List submissions (fid=10, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="11_2">Check assignment submission (fid=11, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="12_1">Go to administrator statistics screen (fid=12, fcmd=1)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="13_1">Open Grademark paper (fid=13, fcmd=1)</option>
  				<option value="13_2">Has paper been marked (fid=13, fcmd=2)</option>
  				<option value="13_3">Open Grademark report page (fid=13, fcmd=3)</option>
  				<?/*<option value="13_4">Open Grademark paper (fid=13, fcmd=4)</option>*/?>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="14_2">Get report turn around time (fid=14, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="15_2">Get score for paper (fid=15, fcmd=2)</option>
  				<option value="15_3">Set score for paper (fid=15, fcmd=3)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="17_2">Login Session (fid=17, fcmd=2)</option>
  				<option value="18_2">End Session (fid=18, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="19_2">Drop user from class (fid=19, fcmd=2)</option>
  				<option value="19_3">Switch user role (fid=19, fcmd=3)</option>
  				<option value="19_4">Get user role in class (fid=19, fcmd=4)</option>
  				<option value="19_5">Get user enrollments (fid=19, fcmd=5)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="20_1">Load Announcements/Messages page (fid=20, fcmd=1)</option>
  				<option value="20_2">Show Announcements/Messages page (fid=20, fcmd=2)</option>
  				<option value="" disabled="disabled">------------------------------</option>
  				<option value="21_1">Download papers (fid=21, fcmd=1)</option>
  				<option value="21_2">Download papers (fid=21, fcmd=2)</option>
  			</select>
  		</div>

  		<?php /*<div class="formrow">
  			<label for="method">Method:</label>
  			<select name="method" id="method"">
  				<option value="get">GET</option>
  				<option value="post">POST</option>
  			</select>
  		</div>*/ ?>

		<div id="user_details" class="group_fields">
			<div class="formrow">
  				<label for="uid">User ID:</label>
  				<input name="uid" type="text" id="uid" value="<?php echo $_POST['uid'];?>" />
  			</div>

			<div class="formrow">
				<label for="uem">User Email:</label>
	  			<input name="uem" type="text" id="uem" value="<?php echo $_POST['uem'];?>" />
	  		</div>

			<div class="formrow">
  				<label for="ufn">User Firstname:</label>
  				<input name="ufn" type="text" id="ufn" value="<?php echo $_POST['ufn'];?>" />
  			</div>

			<div class="formrow">
				<label for="uln">User Last Name:</label>
  				<input name="uln" type="text" id="uln" value="<?php echo $_POST['uln'];?>" />
  			</div>

			<div class="formrow">
  				<label for="upw">User Password:</label>
  				<input name="upw" type="text" id="upw" value="<?php echo $_POST['upw'];?>" />
			</div>

			<div class="formrow">
  				<label for="newupw">New Password:</label>
  				<input name="newupw" type="text" id="newupw" value="<?php echo $_POST['newupw'];?>" />
			</div>

			<div class="formrow">
  				<label for="session-id">Session ID:</label>
  				<input name="session-id" type="text" id="session-id" value="<?php echo $_POST['session-id'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="create_session">Create Session:</label>
  				<input name="create_session" type="text" id="create_session" value="<?php echo $_POST['create_session'];?>" />
  			</div>

			<div class="formrow">
  				<label for="utp">User Type:</label>
  				<select name="utp" id="utp">
  					<option value=""></option>
  					<option value="1" <?php if ($_POST['utp'] == "1") { ?>selected="selected"<?php } ?>>Student</option>
  					<option value="2" <?php if ($_POST['utp'] == "2") { ?>selected="selected"<?php } ?>>Instructor</option>
  					<option value="3" <?php if ($_POST['utp'] == "3") { ?>selected="selected"<?php } ?>>Administrator</option>
  				</select>
  			</div>
  		</div>

  		<div id="class_details" class="group_fields">
  			<div class="formrow">
  				<label for="cid">Class ID:</label>
  				<input name="cid" type="text" id="cid" value="<?php echo $_POST['cid'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="ctl">Class Title:</label>
  				<input name="ctl" type="text" id="ctl" value="<?php echo $_POST['ctl'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="tem">Class Instructor Email:</label>
  				<input name="tem" type="text" id="tem" value="<?php echo $_POST['tem'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="ced">Class End Date:</label>
  				<input name="ced" type="text" id="ced" value="<?php echo $_POST['ced'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="cpw">Class Password:</label>
  				<input name="cpw" type="text" id="cpw" value="<?php echo $_POST['cpw'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="new_teacher_email">New Tutor Email:</label>
  				<input name="new_teacher_email" type="text" id="new_teacher_email" value="<?php echo $_POST['new_teacher_email'];?>" />
  			</div>
  		</div>

  		<div id="assignment_details" class="group_fields">
  			<div class="formrow">
  				<label for="assignid">Assignment ID:</label>
  				<input name="assignid" type="text" id="assignid" value="<?php echo $_POST['assignid'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="assign">Assignment Title:</label>
  				<input name="assign" type="text" id="assign" value="<?php echo $_POST['assign'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="max_points">Point Value:</label>
  				<input name="max_points" type="text" id="max_points" value="<?php echo $_POST['max_points'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="dtstart">Start Date:</label>
  				<input name="dtstart" type="text" id="dtstart" value="<?php echo $_POST['dtstart'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="dtdue">End Date:</label>
  				<input name="dtdue" type="text" id="dtdue" value="<?php echo $_POST['dtdue'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="dtpost">Date Posted:</label>
  				<input name="dtpost" type="text" id="dtpost" value="<?php echo $_POST['dtpost'];?>" />
  			</div>
  		</div>

  		<div id="submission_details" class="group_fields">
  			<div class="formrow">
  				<label for="oid">Paper Id:</label>
  				<input name="oid" type="text" id="oid" value="<?php echo $_POST['oid'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="ptl">Paper Title:</label>
  				<input name="ptl" type="text" id="ptl" value="<?php echo $_POST['ptl'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="paper">Paper:</label>
  				<input name="paper" type="file" id="paper" value="<?php echo $_POST['paper'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="pfn">Author Firstname:</label>
  				<input name="pfn" type="text" id="pfn" value="<?php echo $_POST['pfn'];?>" />
  			</div>

			<div class="formrow">
				<label for="pln">Author Last Name:</label>
  				<input name="pln" type="text" id="pln" value="<?php echo $_POST['pln'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="score">Score:</label>
  				<input name="score" type="text" id="score" value="<?php echo $_POST['score'];?>" />
  			</div>
  		</div>

  		<div id="misc_details" class="group_fields">
  			<div class="formrow">
  				<label for="starttime">Start Time:</label>
  				<input name="starttime" type="text" id="starttime" value="<?php echo $_POST['starttime'];?>" />
  			</div>

  			<div class="formrow">
  				<label for="paper_ids">Paper Ids:</label>
  				<input name="paper_ids" type="text" id="paper_ids" value="<?php echo $_POST['paper_ids'];?>" />
  			</div>
  		</div>

  		<div class="formrow">
  			<input name="submit" id="submit" value="Submit" type="submit" />
  		</div>
	</form>
</body>
</html>
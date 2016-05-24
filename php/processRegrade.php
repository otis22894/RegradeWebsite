<?php

if(!empty($_POST["myData"]))
{
    // Check if the given request is an AJAX request, exit otherwise 
    if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        $output = json_encode(array(
            'type'=>'error',
            'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 100.'
        ));
        die($output);
    }

    // Get data out of json AJAX request
    $obj =              $_POST['myData'];
    $student_name =     filter_var($obj["student_name"], FILTER_SANITIZE_STRING);
    $homework_num =     filter_var($obj["homework_num"],FILTER_SANITIZE_STRING);
    $homework_type =    filter_var($obj["homework_type"],FILTER_SANITIZE_STRING);
    $student_username = filter_var($obj["student_username"],FILTER_SANITIZE_STRING);
    $folder_name =      filter_var($obj["folder_name"],FILTER_SANITIZE_STRING);
    $regrade_problems = ($obj["regrade_problems"]);
    $TA_Arr =           ($obj["TAs"]);

    // Determine whether to use homework folder or resub folder
    $homework_num_separated = explode(' ',$homework_num);
    if(strcmp($homework_type,"Resubmission")==0){
        $path_to_student_folder = '../../homework_files/Homework' . $homework_num_separated[0] . '_resub/' . $folder_name . '*';
    }else{
        $path_to_student_folder = '../../homework_files/Homework' . $homework_num_separated[0] . '/' . $folder_name . '*';
    }


    // Find out if student folder exists in homework directory, exit otherwise
    $filelist = glob($path_to_student_folder,GLOB_ONLYDIR);
    if($filelist === FALSE){
        $output = json_encode(array('type'=>'error', 'text' => 'The homework you selected is not available for regrades. If the homework was just released, please wait a few days for our system to be updated. Otherwise, please contact your TA.'));
        die($output);
    }


    // Loop through TAs to gather name / email string for emails
    $TANames = "";
    $TAEmails = "";
    $fullTANames = "";
    foreach($TA_Arr as $TA){
        $TAName_split = explode(" ",$TA['name']);
        $TANames .= $TAName_split[0] . ", ";
        $TAEmails .= $TA['email'] . ",";
        $fullTANames .= $TA['name'] . ",";

    }
    $TANames = substr_replace($TANames, "!", -2);


    /*
       Loop though all problems that were submitted, for each:
          - Get submitted information about that problem
          - Create table for the email that gives details for that problem submitted
          - Get file contents for that file
          - Encode file contents (for email) and store it into an array to be attached later
          - Get file contents for the solution version of that file
          - Encode solution file contents (for email) and store it into an array to be attached later
    */
    $allFiles = array();
    $solnFiles = array();
    $regradeProblemStr = '';
    foreach($regrade_problems as $problem) {

        // Get submitted information about the problem
        $problem_name = $problem['problem_name'];
        $problem_justification = $problem['justification'];
        $problem_testCases = $problem['test_cases'];

        // Create table for the email that gives details for the problem
        // Note: the str_replace() part is a hack (feature...) to make sure the emails look good on mobile
        //       it ensures that the second column in the table is at least 20 characters long
        //       otherwise, that column will collapse and look bad on mobile
        $regradeProblemStr .= '<tr><td style="text-align: center;padding:10px;"><center><table style="border-collapse:collapse;width:75%;border-radius:3px;border: 1px solid #aaaaaa;" cellpadding="20"><tbody><tr><td style="border-bottom:1px solid #444444;white-space:nowrap;background-color: #2488B5;color:#ffffff;"><strong>Problem Name:</strong></td><td style="border-bottom:1px solid #444444;background-color: #2488B5;color:#ffffff;font-family:monospace;"><strong>' . str_replace("*","&nbsp;",str_pad($problem_name, 20,"*")) . "</strong></td></tr>";
        $regradeProblemStr .= '<tr style="background-color:#ffffff;"><td style="border-bottom:1px solid #444444;">Test Cases:</td><td style="border-bottom:1px solid #444444;">' . $problem_testCases . "</td></tr>";
        $regradeProblemStr .= '<tr style="background-color:#ffffff;"><td style="border-bottom:1px solid #444444;">Justification:</td><td style="border-bottom:1px solid #444444;font-size:10px;">' . $problem_justification . "</td></tr></tbody></table></center></td></tr>";

        // Get file contents for current problem (student submission)
        $path_to_file = $filelist[0].'/Submission attachment(s)/' . $problem_name . '.m';
        $hw_file_contents = file_get_contents($path_to_file);
        if($hw_file_contents === FALSE){
            $output = json_encode(array('type'=>'error', 'text' => 'We could not find one of the files you submitted a regrade for. If you did not submit the files on TSquare, we cannot regrade your assignment. If the homework was just released, please wait a few days for our system to be updated. Otherwise, please contact your TA.'));
            die($output);
        }

        // Encode file contents (for email) and store it into an array to be attached later
        $encoded_content = chunk_split(base64_encode($hw_file_contents));
        $allFiles[$problem_name] = $encoded_content;

        // Get file contents for the solution version of current file
        $path_to_soln = '../solutions/Homework'.$homework_num_separated[0].'/'.$problem_name.'_soln.p';
        $soln_file_conents = file_get_contents($path_to_soln);

        // Encode solution file contents (for email) and store it into an array to be attached later
        // The if statement will help later determine if there is content to attach
        $encoded_soln_content = chunk_split(base64_encode($soln_file_conents));
        if(strlen($encoded_soln_content)<5){
            $solnFiles[$problem_name.'_soln.p'] = FALSE;
        }else{
            $solnFiles[$problem_name.'_soln.p'] = $encoded_soln_content;
        }
    }


    // Find and attach grade.txt file 
    // Note that TSquare attaches the first file as grade.txt, then grade.txt+1, ...
    // We need to attach the most recent file, this while loop finds and attaches that file
    $path_to_grade_file = $filelist[0].'/Feedback Attachment(s)/grade.txt';
    $path_to_extra_grade_file = $filelist[0].'/Feedback Attachment(s)/grade.txt+1';
    $current = 2;
    while(file_exists($path_to_extra_grade_file)){
        $path_to_grade_file = $path_to_extra_grade_file;
        $path_to_extra_grade_file = substr($path_to_extra_grade_file,0,strlen($path_to_extra_grade_file)-1).(string)$current;
        $current++;
    }
    $grade_file_contents = file_get_contents($path_to_grade_file);
    $grade_file = chunk_split(base64_encode($grade_file_contents));


    // Determine which zip file to use (regular submission or resubmission)
    if(strcmp($homework_type,"Resubmission")==0){
        $path_to_supporting_files = '../solutions/Homework'.$homework_num_separated[0].'/Supporting Files Resub.zip';
    }else{
        $path_to_supporting_files = '../solutions/Homework'.$homework_num_separated[0].'/Supporting Files.zip';
    }


    // If there are supporting files (there may not be), then encode those for attachment later
    $supporting_files_exist = file_exists($path_to_supporting_files);
    if($supporting_files_exist){
        $supporting_file_contents = file_get_contents($path_to_supporting_files);
        $supporting_files = chunk_split(base64_encode($supporting_file_contents));
    }


    // Find and encode pdf file for attachment later
    $path_to_pdf_file = '../solutions/Homework'.$homework_num_separated[0].'/Homework'.sprintf("%02d", $homework_num_separated[0]).'_DrillProblems.pdf';
    $pdf_file_exists = file_exists($path_to_pdf_file);
    if($pdf_file_exists){
        $pdf_file_contents = file_get_contents($path_to_pdf_file);
        $pdf_file = chunk_split(base64_encode($pdf_file_contents));
    }

    /*=================================
       Push regrade data to database
    ==================================*/
    $servername = "localhost:3306";
    $username = "regrade_admin";
    $password = "password";
    $dbname = "Regrades";

    // Create connection to database
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check if valid connection connection
    if ($conn->connect_error) {
        $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 101.'));
        die($output);
    } 

    // Set up SQL query to insert regrade data into Regrade_Entry Table
    $sql = "INSERT INTO Regrade_Entry (homework_num, student_name, student_username, homework_type, total_problems, ta_names, ta_emails, processed) VALUES (" . ($homework_num_separated[0]) . ", '" . mysqli_real_escape_string($conn,$student_name) . "', '" . mysqli_real_escape_string($conn,$student_username) . "', '" . mysqli_real_escape_string($conn,$homework_type) . "'," . mysqli_real_escape_string($conn,sizeof($regrade_problems)) . ",'" . mysqli_real_escape_string($conn,$fullTANames) . "', '" . mysqli_real_escape_string($conn,$TAEmails) . "',0)";

    // Perform query and check for errors
    if ($result = $conn->query($sql) === TRUE) {
        //$output = json_encode(array('type'=>'success', 'text' => $conn->insert_id));
        //die($output);
    } else {
        $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 102.' . $sql));
        die($output);
    }

    // Get the unique regrade id from the last query
    $regrade_id = mysqli_insert_id($conn);

    // Form a multiquery to insert each problem as a row in the Regrade_Problem_Data table
    $sql2 = "";
    foreach($regrade_problems as $problem) {
        $problem_name = $problem['problem_name'];
        $problem_justification = $problem['justification'];
        $problem_testCases = $problem['test_cases'];

        $sql2 .= "INSERT INTO Regrade_Problem_Data (regrade_ID, problem_name, test_cases, justification) VALUES (" . mysqli_real_escape_string($conn,$regrade_id) . ", '" . mysqli_real_escape_string($conn,$problem_name) . "', '" . mysqli_real_escape_string($conn,$problem_testCases) . "', '" . mysqli_real_escape_string($conn,$problem_justification) . "');";

    }

    // Remove last semicolon from query (do I need this? Not totally sure)
    $sql2 = substr($sql2,0,strlen($sql2)-1);

    // Perform query and check for errors
    if ($conn->multi_query($sql2) === FALSE) {
        $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 103.'));
        die($output);
    }

    $conn->close();

    // Variables used in template so I don't have to type them over and over
    $accepted_email = "Hi " . $student_name . ",%0A%0AYour regrade request has been accepted!%0A%0AOld score:%0ANew Score:%0A%0AYour score should now be updated on TSquare.";
    $denied_email = "Hi " . $student_name . ",%0A%0AYour regrade request was denied. Please see the details below:";

    // Read email from template file
    ob_start();
    include("./templates/TAEmail.html");
    $message_body = ob_get_clean();

    // Read email from template file
    ob_start();
    include("./templates/StudentEmail.html");
    $student_email_body = ob_get_clean();

    $boundary = md5("sanwebe"); 

    //header
    $headers = "MIME-Version: 1.0\n"; 
    $headers .= "From:rwilliams306@gatech.edu\n"; 
    $headers .= "Reply-To:rwilliams306@gatech.edu\n";
    $headers .= 'Bcc: rwilliams306@gatech.edu' . "\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary = $boundary\n\n"; 

    //plain text 
    $body = "--$boundary\n";
    $body .= "Content-Type: text/html; charset=ISO-8859-1\n";
    $body .= "Content-Transfer-Encoding: base64\n\n"; 
    $body .= chunk_split(base64_encode($message_body)); 

    //plain text 
    $student_body = "--$boundary\n";
    $student_body .= "Content-Type: text/html; charset=ISO-8859-1\n";
    $student_body .= "Content-Transfer-Encoding: base64\n\n"; 
    $student_body .= chunk_split(base64_encode($student_email_body));

    //attachment
    foreach($allFiles as $problemName => $file_contents){
        $body .= "--$boundary\n";
        $body .="Content-Type: text/html; name=\"". $problemName .".m\"\n";
        $body .="Content-Disposition: attachment; filename=\"". $problemName .".m\"\n";
        $body .="Content-Transfer-Encoding: base64\n";
        $body .="X-Attachment-Id: ".rand(1000,99999)."\n\n"; 
        $body .= $file_contents;

        //HERE, it's attaching multiple copies of the same file
        $soln_name = $problemName.'_soln.p';
        if($solnFiles[$soln_name]){
            $body .= "--$boundary\n";
            $body .="Content-Type: text/html; name=\"". $soln_name ."\"\n";
            $body .="Content-Disposition: attachment; filename=\"". $soln_name ."\"\n";
            $body .="Content-Transfer-Encoding: base64\n";
            $body .="X-Attachment-Id: ".rand(1000,99999)."\n\n"; 
            $body .= $solnFiles[$soln_name];
        }
    }

    $body .= "--$boundary\n";
    $body .="Content-Type: text/html; name=\"grade.txt\"\n";
    $body .="Content-Disposition: attachment; filename=\"grade.txt\"\n";
    $body .="Content-Transfer-Encoding: base64\n";
    $body .="X-Attachment-Id: ".rand(1000,99999)."\n\n"; 
    $body .= $grade_file;

    if($supporting_files_exist){
        $body .= "--$boundary\n";
        $body .="Content-Type: text/html; name=\"Supporting Files.zip\"\n";
        $body .="Content-Disposition: attachment; filename=\"Supporting Files.zip\"\n";
        $body .="Content-Transfer-Encoding: base64\n";
        $body .="X-Attachment-Id: ".rand(1000,99999)."\n\n"; 
        $body .= $supporting_files;
    }

    if($pdf_file_exists){
        $body .= "--$boundary\n";
        $body .="Content-Type: text/html; name=\"Homework".sprintf("%02d", $homework_num_separated[0])."_DrillProblems.pdf\"\n";
        $body .="Content-Disposition: attachment; filename=\"Homework".sprintf("%02d", $homework_num_separated[0])."_DrillProblems.pdf\"\n";
        $body .="Content-Transfer-Encoding: base64\n";
        $body .="X-Attachment-Id: ".rand(1000,99999)."\n\n"; 
        $body .= $pdf_file;
    }

    $subject = "[CS 1371] Regrade Request";
    $student_subject = "[CS 1371] Regrade Request Confirmation";

    //$send_mail = mail($TAEmails, $subject, $body, $headers);
    $send_mail = mail("rwilliams306@gatech.edu", $subject, $body, $headers);

    //$send_mail_student = mail($student_username."@gatech.edu", $student_subject, $student_body, $headers);
    $send_mail_student = mail("rwilliams306@gatech.edu", $student_subject, $student_body, $headers);

    if(!$send_mail && $send_mail_student)
    {
        $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 104.'));
        die($output);
    }else{
        $output = json_encode(array('type'=>'success', 'text' => 'Hi Thank you for your email'));
        die($output);
    }
}else{
    $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your regrade request. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form and the error code below. Sorry for the inconvienence! Error Code: 105.'));
    die($output);
}
?>
<?php
if(!empty($_POST["formData"])){
    $to_email       = "rwilliams306@gatech.edu"; //Recipient email, Replace with own email here
    $from_email 	= "rwilliams306@gatech.edu"; //From email address (eg: no-reply@YOUR-DOMAIN.com)

    //check if its an ajax request, exit if not
    if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        $output = json_encode(array(
            'type'=>'error',
            'text' => 'Sorry Request must be Ajax POST'
        ));
        die($output);
    }

    $obj = $_POST['formData'];

    $feedback = filter_var($obj["feedback"], FILTER_SANITIZE_STRING);
    $response_request = filter_var($obj["response_request"], FILTER_VALIDATE_BOOLEAN);
    $email = filter_var($obj["email"], FILTER_SANITIZE_STRING);

    $resp_req_str = ($response_request) ? 'true' : 'false';

    $message_body = "<html><body style='font-family:sans-serif;'><table width='100%' border='0' cellspacing='0' cellpadding='0' style='font-family:sans-serif;border:1px solid #000000;background-color:#ededed;max-width:600px;'><tr style='background-color:#445;color:#ffffff;'><td style='text-align: center;padding:10px;font-size:24px;'>Regrade Request Website Feedback</td></tr><tr><td style='text-align: center;padding:10px;'>";
    $message_body .= "<h3>Website Feedback:</h3>";
    $message_body .= "<center><table style='border-collapse:collapse;' cellpadding='10'><tr style='background-color:#cccccc;'><td style='border-bottom: 1px solid #444444;font-weight:italic;'><em>Feedback:</em></td><td style='border-bottom: 1px solid #444444;'>" . $feedback . "</td></tr>";
    $message_body .= "<tr style='background-color:#ffffff;'><td style='border-bottom: 1px solid #444444;'><em>Wants A Response:</em></td><td style='border-bottom: 1px solid #444444;'>" . $resp_req_str . "</td></tr>";
    $message_body .= "<tr style='background-color:#cccccc;'><td style='border-bottom: 1px solid #444444;'><em>Email:</em></td><td style='border-bottom: 1px solid #444444;'>" . $email . "</td></tr>";
    $message_body .= "</table>";

    $boundary = md5("sanwebe"); 

    //header
    $headers = "MIME-Version: 1.0\n"; 
    $headers .= "From:".$from_email."\n"; 
    $headers .= "Reply-To: ".$from_email."" . "\n";
    $headers .= 'Bcc: rwilliams306@gatech.edu' . "\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary = $boundary\n\n"; 

    //plain text 
    $body = "--$boundary\n";
    $body .= "Content-Type: text/html; charset=ISO-8859-1\n";
    $body .= "Content-Transfer-Encoding: base64\n\n"; 
    $body .= chunk_split(base64_encode($message_body)); 


    $subject = "[CS 1371] Regrade Request Website Feedback";

    //$send_mail = mail($TAEmails, $subject, $body, $headers);
    $send_mail = mail("rwilliams306@gatech.edu", $subject, $body, $headers);

    if(!$send_mail)
    {
        $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your feedback. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form. Sorry for the inconvienence!'));
        die($output);
    }else{
        $output = json_encode(array('type'=>'success', 'text' => 'Hi Thank you for your email'));
        die($output);
    }
}else{
    $output = json_encode(array('type'=>'error', 'text' => 'Something went wrong while submitting your feedback. Please try submitting again or send an email to rwilliams306@gatech.edu and include the information you submitted on the form. Sorry for the inconvienence!'));
    die($output);
}
?>
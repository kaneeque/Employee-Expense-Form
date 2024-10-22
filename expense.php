<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer/src/Exception.php';
require 'PHPmailer/src/PHPMailer.php';
require 'PHPmailer/src/SMTP.php';

// Retrieve form fields
$employeeName = $_POST['employeeName'];
$employeeId = $_POST['employeeId'];
$employeeEmail = $_POST['employeeEmail'];
$department = $_POST['department'];
$managerName = $_POST['managerName'];
$businessPurpose = $_POST['businessPurpose'];
$expenseDates = $_POST['fromDate'];
$toDates = $_POST['toDate'];
$expenseDetails = $_POST['expenseDetails'];
$expensePrices = $_POST['expensePrice'];
$totalExpenses = $_POST['totalExpenses'];
$employeeSignature = $_POST['employeeSignature'];

// File upload handling
$uploadDir = 'Receipt_uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$receiptFilePaths = [];

if (isset($_FILES['receipt'])) {
    foreach ($_FILES['receipt']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['receipt']['error'][$index] === UPLOAD_ERR_OK) {
            $receiptFileName = uniqid() . '_' . basename($_FILES['receipt']['name'][$index]);
            $receiptFilePath = $uploadDir . $receiptFileName;
            if (move_uploaded_file($tmpName, $receiptFilePath)) {
                $receiptFilePaths[] = $receiptFilePath;
            } else {
                echo "<script>alert('Failed to upload file: " . $_FILES['receipt']['name'][$index] . "'); window.location.href = 'index.html';</script>";
            }
        }
    }
}

// Set escalation times for L2 and L3 levels
$submissionTime = date('Y-m-d H:i:s'); // Get current date and time
$l2EscalationTime = date('Y-m-d H:i:s', $submissionTime + (5 * 60)); // 5 minutes later
$l3EscalationTime = date('Y-m-d H:i:s', $submissionTime + (10 * 60)); // 10 minutes later

// Database insertion logic
$databaseErrors = false;
$conn = new mysqli('localhost', 'root', '', 'application');

if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
} else {
    // Prepare the insert query with placeholders
    $stmt = $conn->prepare('INSERT INTO expenses 
        (employeeName, employeeId, employeeEmail, department, managerName, businessPurpose, fromDate, toDate, expenseDetails, expensePrice, totalExpenses, receipt, employeeSignature, submission_time, l2_escalation_time, l3_escalation_time, status, escalation_level, l1_approval, l2_approval, l3_approval)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    // Loop through each expense entry and insert into the database
    foreach ($expenseDates as $index => $fromDate) {
        $toDate = $toDates[$index];
        $details = $expenseDetails[$index];
        $price = $expensePrices[$index];
        $receipt = isset($receiptFilePaths[$index]) ? $receiptFilePaths[$index] : null;
        $status = 'pending';
        $escalationLevel = null; // Start escalation at NULL
        $l1_approval = 'pending';
        $l2_approval = 'pending';
        $l3_approval = 'pending';

        $stmt->bind_param("sssssssssiissssssssss", $employeeName, $employeeId, $employeeEmail, $department, $managerName, $businessPurpose, $fromDate, $toDate, $details, $price, $totalExpenses, $receipt, $employeeSignature, $submissionTime, $l2EscalationTime, $l3EscalationTime, $status, $escalationLevel, $l1_approval, $l2_approval, $l3_approval);

        if (!$stmt->execute()) {
            $databaseErrors = true;
            echo "Error inserting data into the database: " . $stmt->error . "<br>";
        }
    }

    $stmt->close();
    $conn->close();

    // Notify user if no database errors
    if (!$databaseErrors) {
        echo "<script>alert('Your response has been recorded successfully.'); window.location.href = 'index.html';</script>";
    }
}

// Email sending logic - proceed only if no database errors
if (!$databaseErrors) {
    // Construct the expense details list for emails
    $expenseDetailsList = '';
    foreach ($expenseDates as $index => $fromDate) {
        $toDate = $toDates[$index];
        $details = $expenseDetails[$index];
        $price = $expensePrices[$index];
        $expenseDetailsList .= "<li>$fromDate to $toDate - $details: ₹$price</li>";
    }

    // Admin review link
    $adminReviewLink = "http://localhost/assessment/reimbursement_form/admin_review.php?employeeId=$employeeId";

    // Prepare email bodies
    $adminEmailBody = "
        <div style='font-family: Arial, sans-serif;'>
            <p>Dear Admin,</p>
            <p>You have expense entries to review.</p>
            <p><strong>Expense Report:</strong> 'Expense Report for Review' by <strong>$employeeName</strong>.</p>
            <ul>$expenseDetailsList</ul>
            <p><strong>Total Reimbursable amount:</strong> ₹$totalExpenses</p>
            <p><a href='$adminReviewLink' style='color: blue;'>Click here</a> to review the expenses and take action.</p>
            <p>Best regards,<br>CorpTeam Solutions</p>
        </div>
    ";

    $employeeEmailBodySubmitted = "
        <div style='font-family: Arial, sans-serif;'>
            <p>Dear $employeeName,</p>
            <p>Your expense reimbursement form has been submitted successfully. Below are the details:</p>
            <ul>$expenseDetailsList</ul>
            <p><strong>Total Reimbursable amount:</strong> ₹$totalExpenses</p>
            <p>Best regards,<br>CorpTeam Solutions</p>
        </div>
    ";

    $employeeEmailBodyApproved = "
        <div style='font-family: Arial, sans-serif;'>
            <p>Dear $employeeName,</p>
            <p>Your expense reimbursement form has been <strong>approved</strong>.</p>
            <ul>$expenseDetailsList</ul>
            <p>Best regards,<br>CorpTeam Solutions</p>
        </div>
    ";

    $employeeEmailBodyRejected = "
        <div style='font-family: Arial, sans-serif;'>
            <p>Dear $employeeName,</p>
            <p>Your expense reimbursement form has been <strong>rejected</strong>.</p>
            <ul>$expenseDetailsList</ul>
            <p>Best regards,<br>CorpTeam Solutions</p>
        </div>
    ";

    // Admin emails by department
    $adminEmails = [
        'Telecom' => ['L1' => 'kaneequeahmed@gmail.com', 'L2' => 'prudhvisriram29@gmail.com', 'L3' => 'rocky727983@gmail.com'],
        'Information Technology' => ['L1' => 'prudhvisriram29@gmail.com', 'L2' => 'kaneequeahmed@gmail.com', 'L3' => 'kaneeque31@gmail.com'],
        'Digital Marketing' => ['L1' => 'rocky727983@gmail.com', 'L2' => 'kaneeque31@gmail.com', 'L3' => 'bsh28032001@gmail.com'],
        'Lead Generation' => ['L1' => 'kaneeque31@gmail.com', 'L2' => 'bsh28032001@gmail.com', 'L3' => 'kaneequeahmed@gmail.com'],
        'Business Development' => ['L1' => 'bsh28032001@gmail.com', 'L2' => 'rocky727983@gmail.com', 'L3' => 'prudhvisriram29@gmail.com']
    ];

    // Validate department and fetch the respective admin email for L1
    if (isset($adminEmails[$department])) {
        $l1AdminEmail = $adminEmails[$department]['L1'];
        $l2AdminEmail = $adminEmails[$department]['L2'];
        $l3AdminEmail = $adminEmails[$department]['L3'];

        $mail = new PHPMailer(true);

        try {
            // Configure PHPMailer settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kaneequeahmed@gmail.com'; // SMTP username
            $mail->Password = 'cmuy tueo zrgs zwet'; // SMTP password
            $mail->SMTPSecure = 'ssl'; // Enable SSL encryption
            $mail->Port = 465;

            // Send email to employee (for submission confirmation)
            $mail->setFrom('kaneequeahmed@gmail.com', 'CorpTeam Solutions');
            $mail->addAddress($employeeEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Expense Reimbursement Form Submitted Successfully';
            $mail->Body = $employeeEmailBodySubmitted;
            $mail->send();
            $mail->clearAddresses();

            // Send email to L1 admin
            $mail->addAddress($l1AdminEmail);
            $mail->Subject = 'Expense Reimbursement for Review';
            $mail->Body = $adminEmailBody;
            $mail->send();
            $mail->clearAddresses();

            // Logic for admin approval process
            $conn = new mysqli('localhost', 'root', '', 'application');
            $latestExpenseQuery = "SELECT * FROM expenses WHERE employeeId = '$employeeId' ORDER BY submission_time DESC LIMIT 1";
            $result = $conn->query($latestExpenseQuery);
            
            if ($result->num_rows > 0) {
                $latestExpense = $result->fetch_assoc();

                // Approval Logic
                if ($latestExpense['l1_approval'] === 'approved') {
                    // Send to L2 admin for approval
                    $mail->addAddress($l2AdminEmail);
                    $mail->Subject = 'Expense Reimbursement for L2 Review';
                    $mail->Body = $adminEmailBody;
                    $mail->send();
                } elseif ($latestExpense['l1_approval'] === 'rejected') {
                    // Notify employee of rejection
                    $mail->addAddress($employeeEmail);
                    $mail->Subject = 'Expense Reimbursement Rejected';
                    $mail->Body = $employeeEmailBodyRejected;
                    $mail->send();
                }

                // Further checks for L2 approval
                if ($latestExpense['l2_approval'] === 'approved') {
                    // Send to L3 admin for approval
                    $mail->addAddress($l3AdminEmail);
                    $mail->Subject = 'Expense Reimbursement for L3 Review';
                    $mail->Body = $adminEmailBody;
                    $mail->send();
                } elseif ($latestExpense['l2_approval'] === 'rejected') {
                    // Notify employee of rejection
                    $mail->addAddress($employeeEmail);
                    $mail->Subject = 'Expense Reimbursement Rejected';
                    $mail->Body = $employeeEmailBodyRejected;
                    $mail->send();
                }

                // Final check for L3 approval
                if ($latestExpense['l3_approval'] === 'approved') {
                    // Notify employee of approval
                    $mail->addAddress($employeeEmail);
                    $mail->Subject = 'Expense Reimbursement Approved';
                    $mail->Body = $employeeEmailBodyApproved;
                    $mail->send();
                } elseif ($latestExpense['l3_approval'] === 'rejected') {
                    // Notify employee of rejection
                    $mail->addAddress($employeeEmail);
                    $mail->Subject = 'Expense Reimbursement Rejected';
                    $mail->Body = $employeeEmailBodyRejected;
                    $mail->send();
                }
            }
            $conn->close();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

} ?>
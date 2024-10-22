<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer/src/Exception.php';
require 'PHPmailer/src/PHPMailer.php';
require 'PHPmailer/src/SMTP.php';

// Set timezone to match your region
date_default_timezone_set('Asia/Kolkata'); // Adjust to your local timezone

// Database connection
$conn = new mysqli('localhost', 'root', '', 'application');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
} else {
    echo "Connected to database successfully...\n";
}

// Fetch unapproved expenses where status is still 'pending'
$sql = "SELECT * FROM expenses WHERE status = 'pending'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Pending expenses found...\n";

    // Dynamic L2 and L3 escalation emails based on department
    $escalationEmails = [
        'Telecom' => [
            'L2' => 'prudhvisriram29@gmail.com',
            'L3' => 'rocky727983@gmail.com',
        ],
        'Information Technology' => [
            'L2' => 'rocky727983@gmail.com',
            'L3' => 'kaneequeahmed@gmail.com',
        ],
        'Digital Marketing' => [
            'L2' => 'kaneequeahmed@gmail.com',
            'L3' => 'prudhvisriram29@gmail.com',
        ]
    ];

    // Iterate over pending expenses
    while ($row = $result->fetch_assoc()) {
        print_r($row); // Print the expense row for debugging

        $submissionTime = strtotime($row['submissionDate']);
        $currentTime = time();
        $timeDiff = ($currentTime - $submissionTime) / 60; // in minutes

        echo "Current server time: " . date("Y-m-d H:i:s", $currentTime) . "\n";
        echo "Submission time for expense ID {$row['id']}: " . $row['submissionDate'] . "\n";
        echo "Time difference for expense ID {$row['id']}: {$timeDiff} minutes\n";

        // Check if submission time is valid
        if ($timeDiff < 0) {
            echo "Submission date is in the future or incorrect for expense ID {$row['id']}. Skipping...\n";
            continue;
        }

        $department = $row['department'];

        // Escalate to L2 admin after 5 minutes if L1 hasn't approved/rejected
        if ($timeDiff >= 5 && $timeDiff < 10) {
            sendEscalationEmail($row, $escalationEmails[$department]['L2'], 'L2');

            // Check if status is still 'pending' and update escalation level to 'L2'
            if ($row['status'] == 'pending') {
                $updateStatus = $conn->query("UPDATE expenses SET escalation_level = 'L2' WHERE id = {$row['id']}");
                if (!$updateStatus) {
                    echo "Error updating escalation level to 'L2' for expense ID {$row['id']}: " . $conn->error . "\n";
                }
            }
        }

        // Escalate to L3 admin after 10 minutes if L2 hasn't approved/rejected
        elseif ($timeDiff >= 10) {
            sendEscalationEmail($row, $escalationEmails[$department]['L3'], 'L3');

            // Check if status is still 'pending' and update escalation level to 'L3'
            if ($row['status'] == 'pending') {
                $updateStatus = $conn->query("UPDATE expenses SET escalation_level = 'L3' WHERE id = {$row['id']}");
                if (!$updateStatus) {
                    echo "Error updating escalation level to 'L3' for expense ID {$row['id']}: " . $conn->error . "\n";
                }
            }
        }
    }
} else {
    echo "No pending expenses found...\n";
}

$conn->close();

// Function to send escalation email
function sendEscalationEmail($expenseData, $escalationEmail, $level) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kaneequeahmed@gmail.com'; // Replace with your email
        $mail->Password = 'cmuy tueo zrgs zwet';     // Replace with your app password
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Set email content for L2 or L3 escalation
        $mail->setFrom('kaneequeahmed@gmail.com', 'CorpTeam Solutions');
        $mail->addAddress($escalationEmail);
        $mail->isHTML(true);
        $mail->Subject = "Escalation: Pending Expense for Employee {$expenseData['employeeName']}";
        $mail->Body = "
            <p>Dear $level Admin,</p>
            <p>An expense report from employee <strong>{$expenseData['employeeName']}</strong> has not been reviewed in the expected time frame.</p>
            <p>Please review it at your earliest convenience: <a href='http://localhost/assessment/reimbursement_form/admin_review.php?employeeId={$expenseData['employeeId']}'>Click here</a></p>
            <p>Best regards,<br>CorpTeam Solutions</p>
        ";
        $mail->send();
        echo "Escalation email sent to $level admin for expense ID {$expenseData['id']}\n";
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}\n";
    }
}
?>

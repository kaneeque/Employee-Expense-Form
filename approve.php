<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'application');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Get the employee ID and approval level from the URL
$employeeId = isset($_GET['employeeId']) ? $_GET['employeeId'] : null;
$level = isset($_GET['level']) ? $_GET['level'] : null;

if ($employeeId && $level) {
    // Determine which approval field to update
    $approvalField = '';
    if ($level === 'L1') {
        $approvalField = 'l1_approval';
    } elseif ($level === 'L2') {
        $approvalField = 'l2_approval';
    } elseif ($level === 'L3') {
        $approvalField = 'l3_approval';
    }

    // Update the respective approval field to 'approved'
    if ($approvalField) {
        $stmt = $conn->prepare("UPDATE expenses SET $approvalField = 'approved' WHERE employeeId = ?");
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();
        $stmt->close();
    }

    // Now check if all approvals (L1, L2, L3) are 'approved' to update the overall status
    $stmt = $conn->prepare("SELECT l1_approval, l2_approval, l3_approval FROM expenses WHERE employeeId = ? ORDER BY submission_time DESC LIMIT 1");
    $stmt->bind_param('s', $employeeId);
    $stmt->execute();
    $stmt->bind_result($l1Approval, $l2Approval, $l3Approval);
    $stmt->fetch();
    $stmt->close();

    if ($l1Approval === 'approved' && $l2Approval === 'approved' && $l3Approval === 'approved') {
        // All levels approved, set status to 'approved'
        $stmt = $conn->prepare("UPDATE expenses SET status = 'approved' WHERE employeeId = ?");
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();
        $stmt->close();
        echo "<p>The expense for employee ID $employeeId has been fully approved.</p>";
    } else {
        echo "<p>Approval for level $level has been recorded for employee ID $employeeId.</p>";
    }

    echo "<p><a href='admin_review.php?employeeId=$employeeId'>Back to Review Page</a></p>"; // Pass employeeId in URL

} else {
    echo "<p>Invalid Employee ID or Approval Level. Please go back and try again.</p>";
}

$conn->close();
?>


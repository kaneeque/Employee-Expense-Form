<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'application');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Get the employee ID and rejection level from the URL
$employeeId = isset($_GET['employeeId']) ? $_GET['employeeId'] : null;
$level = isset($_GET['level']) ? $_GET['level'] : null;

if ($employeeId && $level) {
    // Determine which approval field to update
    $rejectionField = '';
    if ($level === 'L1') {
        $rejectionField = 'l1_approval';
    } elseif ($level === 'L2') {
        $rejectionField = 'l2_approval';
    } elseif ($level === 'L3') {
        $rejectionField = 'l3_approval';
    }

    // Update the respective approval field to 'rejected'
    if ($rejectionField) {
        $stmt = $conn->prepare("UPDATE expenses SET $rejectionField = 'rejected' WHERE employeeId = ?");
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();
        $stmt->close();
    }

    // Since a rejection has occurred, we can immediately set the status to 'rejected'
    $stmt = $conn->prepare("UPDATE expenses SET status = 'rejected' WHERE employeeId = ?");
    $stmt->bind_param('s', $employeeId);
    $stmt->execute();
    $stmt->close();

    echo "<p>The expense for employee ID $employeeId has been rejected at level $level.</p>";
    echo "<p><a href='admin_review.php?employeeId=$employeeId'>Back to Review Page</a></p>"; // Pass employeeId in URL

} else {
    echo "<p>Invalid Employee ID or Rejection Level. Please go back and try again.</p>";
}

$conn->close();
?>

<?php
// Connect to the database
$conn = new mysqli('localhost', 'root', '', 'application');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Get the employee ID from the URL
$employeeId = $_GET['employeeId'];

// Fetch the latest expense entry for this employee
$stmt = $conn->prepare('SELECT l1_approval, l2_approval, l3_approval FROM expenses WHERE employeeId = ? ORDER BY submission_time DESC LIMIT 1');
$stmt->bind_param('s', $employeeId);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($l1Approval, $l2Approval, $l3Approval);
$stmt->fetch();
$stmt->close();

if (!$l1Approval && !$l2Approval && !$l3Approval) {
    echo "No expense records found for this employee.";
    exit();
}

// Fetch all receipts for the employee from the database
$stmt = $conn->prepare('SELECT receipt FROM expenses WHERE employeeId = ?');
$stmt->bind_param('s', $employeeId);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($receipt);

$receipts = [];
while ($stmt->fetch()) {
    $receipts[] = $receipt;
}
$stmt->close();

// Display the receipts and approval/rejection buttons
if (!empty($receipts)) {
    echo "<div style='font-family: Arial, sans-serif;'>
            <h3>Review Employee Expenses</h3>
            <div style='display: flex; align-items: center; justify-content: space-between; padding: 10px; border: 1px solid #ccc;'>
                <div><strong>Employee ID:</strong> $employeeId</div>
                <div>";

    foreach ($receipts as $receipt) {
        $publicReceiptPath = urlencode(basename($receipt));

        echo "<a href='view_file.php?file=$publicReceiptPath' target='_blank' style='display: block; color: #007bff; margin-bottom: 5px;'>View Receipt: $receipt</a>";
    }

    echo "    </div>
                <div>";

    // Determine the current admin's level and show appropriate buttons
    if ($l1Approval === 'pending') {
        echo "<a href='approve.php?level=L1&employeeId=$employeeId' style='color: white; background-color: green; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>Approve</a>";
        echo "<a href='reject.php?level=L1&employeeId=$employeeId' style='color: white; background-color: red; padding: 5px 10px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>Reject</a>";
    } elseif ($l1Approval === 'approved' && $l2Approval === 'pending') {
        echo "<a href='approve.php?level=L2&employeeId=$employeeId' style='color: white; background-color: green; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>Approve</a>";
        echo "<a href='reject.php?level=L2&employeeId=$employeeId' style='color: white; background-color: red; padding: 5px 10px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>Reject</a>";
    } elseif ($l2Approval === 'approved' && $l3Approval === 'pending') {
        echo "<a href='approve.php?level=L3&employeeId=$employeeId' style='color: white; background-color: green; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>Approve</a>";
        echo "<a href='reject.php?level=L3&employeeId=$employeeId' style='color: white; background-color: red; padding: 5px 10px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>Reject</a>";
    }

    echo "    </div>
            </div>
          </div>";
} else {
    echo "No expense records found for this employee.";
}

$conn->close();
?>

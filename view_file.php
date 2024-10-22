<?php
$file = $_GET['file'];
$path = 'Receipt_uploads/' . basename($file); // Ensure the path is correct

// Ensure the file exists
if (file_exists($path)) {
    $fileType = mime_content_type($path); // Get the MIME type of the file
    header('Content-Type: ' . $fileType);
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
} else {
    echo "File not found.";
}
?>

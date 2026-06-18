<?php
// backend/test_write.php
$dir = '../uploads/';
if (is_writable($dir)) {
    echo "SUCCESS: The uploads folder is writable.";
    // Try to create a dummy file to be absolutely sure
    $testFile = $dir . 'write_test.txt';
    file_put_contents($testFile, 'Testing write access.');
    echo " ... And dummy file created successfully.";
} else {
    echo "ERROR: The uploads folder is NOT writable. Check permissions.";
}
?>
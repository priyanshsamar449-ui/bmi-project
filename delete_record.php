<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// The "AND user_id = ?" here is what stops one user from deleting another
// user's row just by guessing/changing the id in the URL. Same idea as
// clear_history.php, just for one row instead of all of them.
$stmt = mysqli_prepare($conn, "DELETE FROM bmi_records WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $record_id, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: index.php");
exit();
?>

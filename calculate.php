<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_id = $_SESSION['user_id'];

    // Height can come in as plain cm, OR as feet + inches (toggle on the form).
    // Either way we convert everything to cm before running the BMI formula,
    // so the formula itself never needs to know which unit the user picked.
    $height_unit = isset($_POST['height_unit']) ? $_POST['height_unit'] : 'cm';

    if ($height_unit === 'ft') {
        $feet   = floatval($_POST['feet']);
        $inches = floatval($_POST['inches']);
        // 1 foot = 30.48 cm, 1 inch = 2.54 cm
        $height_cm = ($feet * 30.48) + ($inches * 2.54);
    } else {
        $height_cm = floatval($_POST['height']);
    }

    $weight_kg = floatval($_POST['weight']);
    $age       = intval($_POST['age']);
    $gender    = $_POST['gender'];

    // Only allow the genders we actually put on the form (never trust POST data as-is)
    $allowed_genders = ['Male', 'Female', 'Other'];
    if (!in_array($gender, $allowed_genders)) {
        $gender = 'Other';
    }

    // Basic server-side validation (JS already checks this, but never trust the client alone)
    if ($height_cm <= 0 || $weight_kg <= 0 || $age <= 0 || $age > 120) {
        header("Location: index.php?error=invalid");
        exit();
    }

    // BMI formula: weight(kg) / height(m)^2 -- age and gender are NOT part of this
    // formula, they're only stored so we can tailor the diet suggestion text.
    $height_m = $height_cm / 100;
    $bmi = $weight_kg / ($height_m * $height_m);
    $bmi = round($bmi, 2);

    // Category cutoffs: the standard WHO/global cutoffs (same ones used by
    // most BMI calculators, e.g. calculator.net).
    if ($bmi < 18.5) {
        $category = "Underweight";
    } elseif ($bmi < 25) {
        $category = "Normal";
    } elseif ($bmi < 30) {
        $category = "Overweight";
    } else {
        $category = "Obese";
    }

    // Save this attempt to the database, tied to the logged-in user
    $stmt = mysqli_prepare($conn, "INSERT INTO bmi_records (user_id, height, weight, age, gender, bmi, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iddisds", $user_id, $height_cm, $weight_kg, $age, $gender, $bmi, $category);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    // Send the result back to the main page. Height goes along too so index.php
    // can show the healthy weight range for that exact height without a re-query.
    header("Location: index.php?bmi=$bmi&category=$category&height=$height_cm");
    exit();
}
?>

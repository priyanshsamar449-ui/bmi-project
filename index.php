<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$result_bmi      = isset($_GET['bmi']) ? floatval($_GET['bmi']) : null;
$result_category = isset($_GET['category']) ? $_GET['category'] : null;
$result_height   = isset($_GET['height']) ? floatval($_GET['height']) : null;
$show_error      = isset($_GET['error']);

// ---- Gauge needle angle -------------------------------------------------
// The gauge is a semicircle (180 degrees) that represents BMI values from
// 12 to 40. We turn the BMI into a percentage of that range (0% to 100%),
// then turn that percentage into a rotation angle for the needle (0deg to
// 180deg). Full explanation with pictures of the math is in STUDY_GUIDE.md.
$needle_rotation = null;
if ($result_bmi !== null) {
    $gauge_min = 12;
    $gauge_max = 40;
    $percent = ($result_bmi - $gauge_min) / ($gauge_max - $gauge_min);
    $percent = max(0, min(1, $percent)); // clamp so wild BMI values don't break the needle
    $needle_rotation = 180 * $percent;
}

// ---- Healthy weight range for this height --------------------------------
// Uses the same standard BMI cutoffs as the category calculation in
// calculate.php: Normal range is BMI 18.5 to 24.9.
$healthy_min = null;
$healthy_max = null;
if ($result_height !== null) {
    $h_m = $result_height / 100;
    $healthy_min = round(18.5 * $h_m * $h_m, 1);
    $healthy_max = round(24.9 * $h_m * $h_m, 1);
}

// ---- Diet suggestion text per category -----------------------------------
$diet_tips = array(
    "Underweight" => "Add more calorie-dense, nutrient-rich foods to your meals: nuts, seeds, dairy, whole grains, and healthy oils like ghee or olive oil. Eating smaller meals more often can help if big meals feel like too much.",
    "Normal"      => "You're in a healthy range - keep it up with a balanced plate of vegetables, whole grains, lean protein (dal, eggs, chicken, fish), and fruit, plus regular activity.",
    "Overweight"  => "Favour vegetables, dal, and whole grains over refined carbs and fried food. Watch portion sizes, cut down on sugary drinks, and aim for at least 30 minutes of walking or activity most days.",
    "Obese"       => "Focus on whole foods over processed/fried food, control portion sizes, and build up gradually to regular activity (start with short walks). Consider talking to a doctor or dietitian for a plan suited to you."
);

// Only this user's history - id DESC as a tiebreaker keeps order stable
$stmt = mysqli_prepare($conn, "SELECT * FROM bmi_records WHERE user_id = ? ORDER BY created_at DESC, id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);
// Pulling everything into a plain array (instead of looping the mysqli result
// once) lets us look at the two most recent rows for the trend arrow AND
// loop over every row for the table, without querying the database twice.
$history_rows = mysqli_fetch_all($history_result, MYSQLI_ASSOC);

// ---- Trend arrow: compare the newest entry to the one before it ----------
$trend = null; // 'up', 'down', 'same', or null if there's nothing to compare
if (count($history_rows) >= 2) {
    $latest = $history_rows[0]['bmi'];
    $previous = $history_rows[1]['bmi'];
    if ($latest > $previous) {
        $trend = 'up';
    } elseif ($latest < $previous) {
        $trend = 'down';
    } else {
        $trend = 'same';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BMI Calculator</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="user-bar">
        <span>Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php">Log out</a>
    </div>

    <div class="card">
        <h1>BMI Calculator</h1>
        <p class="subtitle">See where you land on the scale</p>

        <form action="calculate.php" method="POST" id="bmiForm">

            <div class="input-group">
                <label>Height</label>
                <div class="unit-toggle">
                    <button type="button" id="unitCmBtn" class="unit-btn active">cm</button>
                    <button type="button" id="unitFtBtn" class="unit-btn">ft / in</button>
                </div>
                <input type="hidden" name="height_unit" id="height_unit" value="cm">

                <div id="cmFields">
                    <input type="number" step="0.1" id="height" name="height" placeholder="170">
                </div>
                <div id="ftFields" class="ft-in-row" style="display:none;">
                    <input type="number" step="1" id="feet" name="feet" placeholder="ft">
                    <input type="number" step="0.1" id="inches" name="inches" placeholder="in">
                </div>
            </div>

            <div class="input-group">
                <label for="weight">Weight (kg)</label>
                <input type="number" step="0.1" id="weight" name="weight" placeholder="65" required>
            </div>

            <div class="input-group">
                <label for="age">Age</label>
                <input type="number" step="1" id="age" name="age" placeholder="19" required>
            </div>

            <div class="input-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <p id="livePreview" class="live-preview">&nbsp;</p>
            <button type="submit">Calculate BMI</button>
        </form>

        <?php if ($show_error): ?>
        <p class="error-message">Please check your inputs - height, weight, and age all need to be positive numbers. Try again.</p>
        <?php endif; ?>

        <?php if ($result_bmi !== null): ?>
        <div class="result-box category-<?php echo strtolower($result_category); ?>">

            <div class="gauge-row">
                <svg class="gauge" viewBox="0 0 300 170" xmlns="http://www.w3.org/2000/svg">
                    <!-- Four coloured bands, one per BMI category. Each is a stroked arc
                         (a thick curved line) rather than a filled pie slice, using the
                         standard BMI cutoffs: 18.5 / 25 / 30. -->
                    <path d="M 30 150 A 120 120 0 0 1 60.5 70" class="band band-underweight"/>
                    <path d="M 60.5 70 A 120 120 0 0 1 136.6 30.8" class="band band-normal"/>
                    <path d="M 136.6 30.8 A 120 120 0 0 1 202.1 41.9" class="band band-overweight"/>
                    <path d="M 202.1 41.9 A 120 120 0 0 1 270 150" class="band band-obese"/>

                    <!-- The needle is drawn pointing LEFT by default (from the centre
                         pivot at 150,150 out to 70,150), then rotated clockwise around
                         that same centre point. 0deg = still pointing left (BMI 12),
                         180deg = pointing right (BMI 40). See STUDY_GUIDE.md. -->
                    <line x1="150" y1="150" x2="70" y2="150" class="needle"
                          transform="rotate(<?php echo $needle_rotation; ?> 150 150)"/>
                    <circle cx="150" cy="150" r="9" class="needle-pivot"/>
                </svg>

                <div class="gauge-readout">
                    <p class="gauge-label">Your BMI</p>
                    <p class="result-bmi"><?php echo $result_bmi; ?></p>
                    <p class="result-category"><?php echo $result_category; ?></p>
                    <?php if ($trend !== null): ?>
                    <p class="trend trend-<?php echo $trend; ?>">
                        <?php if ($trend === 'up'): ?>&#9650; up from last time
                        <?php elseif ($trend === 'down'): ?>&#9660; down from last time
                        <?php else: ?>&#8212; same as last time
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gauge-legend">
                <span><i class="dot dot-underweight"></i>Under Weight</span>
                <span><i class="dot dot-normal"></i>Normal</span>
                <span><i class="dot dot-overweight"></i>Over Weight</span>
                <span><i class="dot dot-obese"></i>Obesity</span>
            </div>

            <?php if ($healthy_min !== null): ?>
            <p class="healthy-range">Healthy weight range for your height: <strong><?php echo $healthy_min; ?>&ndash;<?php echo $healthy_max; ?> kg</strong></p>
            <?php endif; ?>

            <p class="diet-tip"><?php echo htmlspecialchars($diet_tips[$result_category]); ?></p>
        </div>
        <?php endif; ?>

        <div class="scale-reference">
            <span class="scale-item underweight">Underweight &lt; 18.5</span>
            <span class="scale-item normal">Normal 18.5&ndash;24.9</span>
            <span class="scale-item overweight">Overweight 25&ndash;29.9</span>
            <span class="scale-item obese">Obese 30+</span>
        </div>
    </div>

    <div class="card history-card">
        <div class="history-header">
            <h2>History</h2>
            <a href="clear_history.php" class="clear-btn" onclick="return confirm('Clear all your history?');">Clear log</a>
        </div>

        <?php if (count($history_rows) > 0): ?>
        <table>
            <tr>
                <th>Height</th>
                <th>Weight</th>
                <th>Age</th>
                <th>Gender</th>
                <th>BMI</th>
                <th>Category</th>
                <th>Date</th>
                <th></th>
            </tr>
            <?php foreach ($history_rows as $row): ?>
            <tr>
                <td><?php echo $row['height']; ?> cm</td>
                <td><?php echo $row['weight']; ?> kg</td>
                <td><?php echo $row['age']; ?></td>
                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                <td><?php echo $row['bmi']; ?></td>
                <td class="category-<?php echo strtolower($row['category']); ?>-text"><?php echo $row['category']; ?></td>
                <td><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></td>
                <td>
                    <a href="delete_record.php?id=<?php echo $row['id']; ?>" class="delete-row-btn" onclick="return confirm('Delete this entry?');" title="Delete this entry">&times;</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p class="empty-history">No readings yet &mdash; calculate one to start your log.</p>
        <?php endif; ?>
    </div>

</div>
<script src="script.js"></script>
</body>
</html>

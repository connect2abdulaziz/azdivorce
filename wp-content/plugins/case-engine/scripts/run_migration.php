<?php
// Standalone migration: adds v6 columns to FVp_az_cases.
$conn = new mysqli('localhost', 'wpuser', 'MySQL@123', 'azdivorce');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error . PHP_EOL);
}

$prefix = 'FVp_';
$table  = $prefix . 'az_cases';

$cols = [];
$r    = $conn->query("SHOW COLUMNS FROM `{$table}`");
while ($row = $r->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo 'Existing columns: ' . implode(', ', $cols) . PHP_EOL;

$added = [];

if (!in_array('stripe_session_id', $cols)) {
    $conn->query("ALTER TABLE `{$table}` ADD COLUMN stripe_session_id varchar(255) DEFAULT '' AFTER status");
    $added[] = 'stripe_session_id';
}
if (!in_array('payment_date', $cols)) {
    $conn->query("ALTER TABLE `{$table}` ADD COLUMN payment_date datetime DEFAULT NULL AFTER stripe_session_id");
    $added[] = 'payment_date';
}
if (!in_array('payment_amount', $cols)) {
    $conn->query("ALTER TABLE `{$table}` ADD COLUMN payment_amount decimal(10,2) DEFAULT 0.00 AFTER payment_date");
    $added[] = 'payment_amount';
}
if (!in_array('questionnaire_status', $cols)) {
    $conn->query("ALTER TABLE `{$table}` ADD COLUMN questionnaire_status varchar(20) NOT NULL DEFAULT 'pending' AFTER payment_amount");
    $conn->query("ALTER TABLE `{$table}` ADD KEY questionnaire_status (questionnaire_status)");
    $added[] = 'questionnaire_status';
}

echo 'Added columns: ' . (empty($added) ? 'none (already present)' : implode(', ', $added)) . PHP_EOL;

// Show final columns
echo 'Final columns:' . PHP_EOL;
$r = $conn->query("SHOW COLUMNS FROM `{$table}`");
while ($row = $r->fetch_assoc()) {
    echo '  ' . $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}

// Bump the db version option
$opts = $prefix . 'options';
$conn->query("UPDATE `{$opts}` SET option_value='6' WHERE option_name='case_engine_db_version'");
echo 'DB version option updated to 6.' . PHP_EOL;

$conn->close();
echo 'Done.' . PHP_EOL;

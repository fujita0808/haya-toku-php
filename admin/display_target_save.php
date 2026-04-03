<?php

require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/db.php';

$planId = $_POST['display_plan_id'] ?? null;

// DBに保存
set_display_target_plan_id($planId);

// リダイレクト
header('Location: dashboard.php');
exit;
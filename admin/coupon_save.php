<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin_login();

if (request_method() !== 'POST') {
    redirect_to('/admin/dashboard.php');
}

$plan = normalize_plan_from_post();
upsert_plan($plan);
redirect_to('/admin/dashboard.php');

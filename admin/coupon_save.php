<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_admin_login();

if (request_method() !== 'POST') {
    redirect_to('/admin/dashboard.php');
}

$plan = normalize_plan_from_post($_POST);
save_plan($plan);

redirect_to('/admin/dashboard.php');
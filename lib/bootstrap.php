<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Tokyo');

const HAYA_TOKU_APP_NAME = 'HAYA-TOKU（🍊ver / PHP PoC）';
const HAYA_TOKU_STORAGE_DIR = __DIR__ . '/../storage';

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/coupon_logic.php';
require_once __DIR__ . '/auth.php';

<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Tokyo');

const HAYA_TOKU_APP_NAME = 'HAYA-TOKU（🍊ver / PHP PoC）';
const HAYA_TOKU_STORAGE_DIR = __DIR__ . '/../storage';

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

// ★ ここ重要：modelを先に読み込む
require_once __DIR__ . '/coupon_model.php';

// 橋渡し層
require_once __DIR__ . '/coupon_discount.php';

// ロジック
require_once __DIR__ . '/coupon_logic.php';

require_once __DIR__ . '/auth.php';
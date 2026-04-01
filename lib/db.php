<?php

declare(strict_types=1);


if (!function_exists('db')) {
    /**
     * PostgreSQL PDO 接続を返す
     */
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $databaseUrl = getenv('DATABASE_URL');
        if (!$databaseUrl) {
            throw new RuntimeException('DATABASE_URL が設定されていません。');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            throw new RuntimeException('DATABASE_URL の解析に失敗しました。');
        }

        $scheme = (string)($parts['scheme'] ?? '');
        if ($scheme !== 'postgres' && $scheme !== 'postgresql') {
            throw new RuntimeException('DATABASE_URL は postgres / postgresql 形式である必要があります。');
        }

        $host = (string)($parts['host'] ?? '');
        $port = (int)($parts['port'] ?? 5432);
        $user = urldecode((string)($parts['user'] ?? ''));
        $pass = urldecode((string)($parts['pass'] ?? ''));
        $dbName = ltrim((string)($parts['path'] ?? ''), '/');

        if ($host === '' || $dbName === '' || $user === '') {
            throw new RuntimeException('DATABASE_URL に必要な接続情報が不足しています。');
        }

        $sslMode = 'require';
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (!empty($query['sslmode']) && is_string($query['sslmode'])) {
                $sslMode = $query['sslmode'];
            }
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $host,
            $port,
            $dbName,
            $sslMode
        );

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec("SET TIME ZONE 'Asia/Tokyo'");
        } catch (PDOException $e) {
            throw new RuntimeException('DB 接続に失敗しました: ' . $e->getMessage(), 0, $e);
        }

        return $pdo;
    }
}

if (!function_exists('pdo')) {
    /**
     * 互換用エイリアス
     */
    function pdo(): PDO
    {
        return db();
    }
}

if (!function_exists('plan_table_columns')) {
    /**
     * coupon_plans テーブルのカラム一覧を返す
     *
     * @return string[]
     */
    function plan_table_columns(): array
    {
        static $columns = null;

        if (is_array($columns)) {
            return $columns;
        }

        $sql = <<<SQL
SELECT column_name
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'coupon_plans'
ORDER BY ordinal_position
SQL;

        $stmt = db()->query($sql);
        $rows = $stmt->fetchAll();

        $columns = array_map(
            static fn(array $row): string => (string)$row['column_name'],
            $rows
        );

        return $columns;
    }
}

if (!function_exists('plan_table_has_column')) {
    function plan_table_has_column(string $columnName): bool
    {
        return in_array($columnName, plan_table_columns(), true);
    }
}

if (!function_exists('generate_plan_id')) {
    function generate_plan_id(): string
    {
        return 'plan_' . bin2hex(random_bytes(4));
    }
}

if (!function_exists('decode_plan_row')) {
    /**
     * DBの1行をアプリ用の配列へ正規化
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function decode_plan_row(array $row): array
    {
        $plan = $row;

        $plan['id'] = (string)($row['id'] ?? '');
        $plan['title'] = (string)($row['title'] ?? '');
        $plan['description'] = (string)($row['description'] ?? '');
        $plan['product_name'] = (string)($row['product_name'] ?? '');
        $plan['start_at'] = isset($row['start_at']) ? (string)$row['start_at'] : '';
        $plan['end_at'] = isset($row['end_at']) ? (string)$row['end_at'] : '';
        $plan['notes'] = (string)($row['notes'] ?? '');
        $plan['created_at'] = isset($row['created_at']) ? (string)$row['created_at'] : '';
        $plan['updated_at'] = isset($row['updated_at']) ? (string)$row['updated_at'] : '';

        $plan['initial_discount_rate'] = isset($row['initial_discount_rate'])
            ? (float)$row['initial_discount_rate']
            : 0.0;

        $plan['min_discount_rate'] = isset($row['min_discount_rate'])
            ? (float)$row['min_discount_rate']
            : 0.0;

        $isActive = $row['is_active'] ?? false;
        if (is_bool($isActive)) {
            $plan['is_active'] = $isActive;
        } else {
            $plan['is_active'] = filter_var($isActive, FILTER_VALIDATE_BOOL);
        }

        $rulesRaw = $row['rules'] ?? '[]';
        if (is_array($rulesRaw)) {
            $plan['rules'] = array_values(array_map('strval', $rulesRaw));
        } elseif (is_string($rulesRaw) && $rulesRaw !== '') {
            $decoded = json_decode($rulesRaw, true);
            $plan['rules'] = is_array($decoded)
                ? array_values(array_map('strval', $decoded))
                : [];
        } else {
            $plan['rules'] = [];
        }

        return $plan;
    }
}

if (!function_exists('normalize_plan_for_save')) {
    /**
     * POSTなどから受け取った plan データを保存用に整形
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    function normalize_plan_for_save(array $input): array
    {
        $rules = $input['rules'] ?? $input['rules_text'] ?? [];

        if (is_string($rules)) {
            $lines = preg_split('/\R/u', $rules) ?: [];
            $rules = array_values(array_filter(array_map(
                static fn(string $line): string => trim($line),
                $lines
            ), static fn(string $line): bool => $line !== ''));
        }

        if (!is_array($rules)) {
            $rules = [];
        }

        $rules = array_values(array_map('strval', $rules));

        $plan = [
            'id' => trim((string)($input['id'] ?? '')),
            'title' => trim((string)($input['title'] ?? '')),
            'description' => trim((string)($input['description'] ?? '')),
            'product_name' => trim((string)($input['product_name'] ?? '')),
            'start_at' => trim((string)($input['start_at'] ?? '')),
            'end_at' => trim((string)($input['end_at'] ?? '')),
            'initial_discount_rate' => (float)($input['initial_discount_rate'] ?? 0),
            'min_discount_rate' => (float)($input['min_discount_rate'] ?? 0),
            'rules' => $rules,
            'notes' => trim((string)($input['notes'] ?? '')),
            'is_active' => !empty($input['is_active']),
        ];

        if ($plan['initial_discount_rate'] < 0) {
            $plan['initial_discount_rate'] = 0.0;
        }
        if ($plan['initial_discount_rate'] > 1) {
            $plan['initial_discount_rate'] = 1.0;
        }

        if ($plan['min_discount_rate'] < 0) {
            $plan['min_discount_rate'] = 0.0;
        }
        if ($plan['min_discount_rate'] > 1) {
            $plan['min_discount_rate'] = 1.0;
        }

        return $plan;
    }
}

if (!function_exists('build_plan_order_clause')) {
    function build_plan_order_clause(): string
    {
        $clauses = [];

        if (plan_table_has_column('is_active')) {
            $clauses[] = 'is_active DESC';
        }
        if (plan_table_has_column('start_at')) {
            $clauses[] = 'start_at DESC NULLS LAST';
        }
        if (plan_table_has_column('updated_at')) {
            $clauses[] = 'updated_at DESC NULLS LAST';
        }
        if (plan_table_has_column('created_at')) {
            $clauses[] = 'created_at DESC NULLS LAST';
        }

        $clauses[] = 'id DESC';

        return implode(', ', $clauses);
    }
}

if (!function_exists('find_all_plans')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function find_all_plans(): array
    {
        $sql = 'SELECT * FROM coupon_plans ORDER BY ' . build_plan_order_clause();
        $stmt = db()->query($sql);
        $rows = $stmt->fetchAll();

        return array_map('decode_plan_row', $rows);
    }
}

if (!function_exists('find_plan_by_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function find_plan_by_id(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $stmt = db()->prepare('SELECT * FROM coupon_plans WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? decode_plan_row($row) : null;
    }
}

if (!function_exists('find_current_plan')) {
    /**
     * 現在有効なプランを1件返す
     *
     * @return array<string, mixed>|null
     */
    function find_current_plan(): ?array
    {
        $conditions = [];

        if (plan_table_has_column('is_active')) {
            $conditions[] = 'is_active = TRUE';
        }
        if (plan_table_has_column('start_at')) {
            $conditions[] = 'start_at <= NOW()';
        }
        if (plan_table_has_column('end_at')) {
            $conditions[] = 'end_at >= NOW()';
        }

        $sql = 'SELECT * FROM coupon_plans';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY ' . build_plan_order_clause() . ' LIMIT 1';

        $stmt = db()->query($sql);
        $row = $stmt->fetch();

        return is_array($row) ? decode_plan_row($row) : null;
    }
}

if (!function_exists('save_plan')) {
    /**
     * plan を insert / update して、保存後の plan を返す
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    function save_plan(array $input): array
    {
        $plan = normalize_plan_for_save($input);

        if ($plan['title'] === '') {
            throw new RuntimeException('タイトルは必須です。');
        }
        if ($plan['start_at'] === '' || $plan['end_at'] === '') {
            throw new RuntimeException('公開開始日時と公開終了日時は必須です。');
        }
        if (strtotime($plan['start_at']) === false || strtotime($plan['end_at']) === false) {
            throw new RuntimeException('日時形式が不正です。');
        }
        if (strtotime($plan['start_at']) > strtotime($plan['end_at'])) {
            throw new RuntimeException('公開開始日時は公開終了日時以前である必要があります。');
        }
        if ($plan['min_discount_rate'] > $plan['initial_discount_rate']) {
            throw new RuntimeException('最低割引率は初期割引率以下である必要があります。');
        }

        $columns = plan_table_columns();
        if ($columns === []) {
            throw new RuntimeException('coupon_plans テーブルが見つかりません。');
        }

        if ($plan['id'] === '') {
            $plan['id'] = generate_plan_id();
        }

        $now = function_exists('now_iso')
            ? (string)now_iso()
            : date('c');

        $dbValues = [
            'id' => $plan['id'],
            'title' => $plan['title'],
            'description' => $plan['description'],
            'product_name' => $plan['product_name'],
            'start_at' => $plan['start_at'],
            'end_at' => $plan['end_at'],
            'initial_discount_rate' => $plan['initial_discount_rate'],
            'min_discount_rate' => $plan['min_discount_rate'],
            'rules' => json_encode($plan['rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'notes' => $plan['notes'],
            'is_active' => $plan['is_active'] ? 1 : 0,
            'created_at' => $input['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        $filtered = [];
        foreach ($dbValues as $column => $value) {
            if (in_array($column, $columns, true)) {
                $filtered[$column] = $value;
            }
        }

        $exists = find_plan_by_id($plan['id']) !== null;

        if ($exists) {
            $setParts = [];
            $params = [':id' => $plan['id']];

            foreach ($filtered as $column => $value) {
                if ($column === 'id' || $column === 'created_at') {
                    continue;
                }
                $setParts[] = sprintf('%s = :%s', $column, $column);
                $params[':' . $column] = $value;
            }

            if ($setParts === []) {
                throw new RuntimeException('更新対象カラムがありません。');
            }

            $sql = sprintf(
                'UPDATE coupon_plans SET %s WHERE id = :id',
                implode(', ', $setParts)
            );

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
        } else {
            $insertColumns = array_keys($filtered);
            $placeholders = array_map(
                static fn(string $column): string => ':' . $column,
                $insertColumns
            );

            $sql = sprintf(
                'INSERT INTO coupon_plans (%s) VALUES (%s)',
                implode(', ', $insertColumns),
                implode(', ', $placeholders)
            );

            $params = [];
            foreach ($filtered as $column => $value) {
                $params[':' . $column] = $value;
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
        }

        $saved = find_plan_by_id($plan['id']);
        if ($saved === null) {
            throw new RuntimeException('プラン保存後の再取得に失敗しました。');
        }

        return $saved;
    }
}
if (!function_exists('coupon_table_columns')) {
    /**
     * coupons テーブルのカラム一覧を返す
     *
     * @return string[]
     */
    function coupon_table_columns(): array
    {
        static $columns = null;

        if (is_array($columns)) {
            return $columns;
        }

        $sql = <<<SQL
SELECT column_name
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'coupons'
ORDER BY ordinal_position
SQL;

        $stmt = db()->query($sql);
        $rows = $stmt->fetchAll();

        $columns = array_map(
            static fn(array $row): string => (string)$row['column_name'],
            $rows
        );

        return $columns;
    }
}

if (!function_exists('decode_coupon_row')) {
    /**
     * DBの coupon 1行をアプリ用に正規化
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function decode_coupon_row(array $row): array
    {
        $coupon = $row;

        $coupon['id'] = (string)($row['id'] ?? '');
        $coupon['coupon_code'] = (string)($row['coupon_code'] ?? '');
        $coupon['coupon_plan_id'] = (string)($row['coupon_plan_id'] ?? ($row['plan_id'] ?? ''));
        $coupon['issued_at'] = isset($row['issued_at']) ? (string)$row['issued_at'] : '';
        $coupon['used_at'] = isset($row['used_at']) ? (string)$row['used_at'] : '';
        $coupon['created_at'] = isset($row['created_at']) ? (string)$row['created_at'] : '';
        $coupon['updated_at'] = isset($row['updated_at']) ? (string)$row['updated_at'] : '';

        $coupon['issued_discount_rate'] = isset($row['issued_discount_rate'])
            ? (float)$row['issued_discount_rate']
            : 0.0;

        $coupon['used_discount_rate'] = isset($row['used_discount_rate'])
            ? (float)$row['used_discount_rate']
            : 0.0;

        return $coupon;
    }
}

if (!function_exists('find_coupon_by_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function find_coupon_by_id(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $stmt = db()->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? decode_coupon_row($row) : null;
    }
}

if (!function_exists('find_coupon_by_code')) {
    /**
     * @return array<string, mixed>|null
     */
    function find_coupon_by_code(string $couponCode): ?array
    {
        $couponCode = trim($couponCode);
        if ($couponCode === '') {
            return null;
        }

        $stmt = db()->prepare('SELECT * FROM coupons WHERE coupon_code = :coupon_code LIMIT 1');
        $stmt->execute([':coupon_code' => $couponCode]);
        $row = $stmt->fetch();

        return is_array($row) ? decode_coupon_row($row) : null;
    }
}

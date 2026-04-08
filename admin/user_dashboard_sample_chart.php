<?php
declare(strict_types=1);

/**
 * user_dashboard_sample.php（全置換版）
 *
 * 目的:
 * - 参考スプレッドシート「クーポン企画シュミレート」タブを、PHP画面として再現するためのサンプル
 * - 「クーポン企画設定値」→「シミュレーション結果」→「割引適用後価格推移グラフ」→「結果表」
 * - 同一画面内で実行・再描画
 * - 表の行に hover すると、対応するグラフの点を強調
 *
 * 備考:
 * - 単体でプレビューできるよう、ロジックもこのファイル内に含めています
 * - 実運用では simulation_logic.php に分離してください
 */

$loginUserName = '店舗ユーザー（サンプル）';

$defaults = [
    'title' => '朝得クーポン',
    'description' => '時間経過とともに割引率が減衰するクーポン企画の試算',
    'product_name' => 'テスト商品',
    'unit_price' => '1000',
    'cost_rate' => '0.50',
    'initial_discount_rate' => '0.20',
    'min_discount_rate' => '0.10',
    'decay_interval_minutes' => '1440',
    'start_at' => date('Y-m-d\T00:00'),
    'end_at' => date('Y-m-d\T00:00', strtotime('+30 days')),
    'target_revenue' => '10000',
    'rules' => "店頭で画面提示\n1会計1回まで\n他クーポン併用不可",
    'notes' => '',
];

$form = $defaults;
$errors = [];
$result = null;

function buildSampleSimulation(array $input): array
{
    $unitPrice = (int)$input['unit_price'];
    $costRate = (float)$input['cost_rate'];
    $initial = (float)$input['initial_discount_rate'];
    $min = (float)$input['min_discount_rate'];
    $targetRevenue = (int)$input['target_revenue'];
    $startAt = new DateTimeImmutable($input['start_at']);
    $endAt = new DateTimeImmutable($input['end_at']);
    $intervalMinutes = (int)$input['decay_interval_minutes'];

    $durationSeconds = max(0, $endAt->getTimestamp() - $startAt->getTimestamp());
    $durationDays = max(1, (int)ceil($durationSeconds / 86400));
    $stepCount = max(1, (int)ceil($durationSeconds / max(60, $intervalMinutes * 60)));
    $decayPerStep = ($stepCount > 1) ? (($initial - $min) / ($stepCount - 1)) : 0.0;

    $rows = [];
    $totalRevenue = 0;
    $totalDiscount = 0;
    $totalGross = 0;
    $totalCv = 0;

    $avgDiscountRate = ($initial + $min) / 2;
    $avgSellingPrice = (int)round($unitPrice * (1 - $avgDiscountRate));
    $estimatedCvTotal = max(1, (int)round($targetRevenue / max(1, $avgSellingPrice)));

    for ($i = 0; $i < $stepCount; $i++) {
        $stepNo = $i + 1;
        $rate = max($min, $initial - $decayPerStep * $i);
        $date = $startAt->modify('+' . ($i * $intervalMinutes) . ' minutes');

        $weight = exp(5 * $rate);
        $cv = max(1, (int)round(($estimatedCvTotal / $stepCount) * ($weight / exp(5 * $avgDiscountRate))));
        $discountedPrice = (int)round($unitPrice * (1 - $rate));
        $revenue = $discountedPrice * $cv;
        $discountValue = (int)round(($unitPrice * $rate) * $cv);
        $gross = (int)round(($discountedPrice - ($unitPrice * $costRate)) * $cv);

        $totalCv += $cv;
        $totalRevenue += $revenue;
        $totalDiscount += $discountValue;
        $totalGross += $gross;

        $rows[] = [
            'step' => $stepNo,
            'date' => $date->format('Y/m/d'),
            'discount_rate' => round($rate, 4),
            'discounted_price' => $discountedPrice,
            'cv_count' => $cv,
            'revenue' => $revenue,
            'discount_value' => $discountValue,
            'gross' => $gross,
        ];
    }

    $cumulativeCv = 0;
    $cumulativeRevenue = 0;
    $cumulativeDiscount = 0;
    $cumulativeGross = 0;

    foreach ($rows as &$row) {
        $cumulativeCv += $row['cv_count'];
        $cumulativeRevenue += $row['revenue'];
        $cumulativeDiscount += $row['discount_value'];
        $cumulativeGross += $row['gross'];

        $row['cumulative_cv'] = $cumulativeCv;
        $row['cv_ratio'] = $totalCv > 0 ? round(($row['cv_count'] / $totalCv) * 100, 2) : 0;
        $row['cumulative_revenue'] = $cumulativeRevenue;
        $row['revenue_ratio'] = $totalRevenue > 0 ? round(($row['revenue'] / $totalRevenue) * 100, 2) : 0;
        $row['cumulative_discount'] = $cumulativeDiscount;
        $row['discount_ratio'] = $totalDiscount > 0 ? round(($row['discount_value'] / $totalDiscount) * 100, 2) : 0;
        $row['cumulative_gross'] = $cumulativeGross;
        $row['gross_ratio'] = $totalGross > 0 ? round(($row['gross'] / $totalGross) * 100, 2) : 0;
        $row['unit_profit'] = $row['cv_count'] > 0 ? (int)round($row['gross'] / $row['cv_count']) : 0;
        $row['unit_discount_rate'] = round($row['discount_rate'] * 100, 2);
        $row['unit_discount'] = $row['cv_count'] > 0 ? (int)round($row['discount_value'] / $row['cv_count']) : 0;
        $row['unit_profit_rate'] = $unitPrice > 0 ? round(($row['unit_profit'] / $unitPrice) * 100, 2) : 0;
    }
    unset($row);

    $chart = [
        'labels' => array_column($rows, 'date'),
        'prices' => array_column($rows, 'discounted_price'),
    ];

    return [
        'summary' => [
            'duration_days' => $durationDays,
            'step_count' => $stepCount,
            'decay_per_step' => round($decayPerStep, 6),
            'total_cv' => $totalCv,
            'total_revenue' => $totalRevenue,
            'total_discount' => $totalDiscount,
            'total_gross' => $totalGross,
        ],
        'chart' => $chart,
        'rows' => $rows,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $defaultValue) {
        $form[$key] = isset($_POST[$key]) ? trim((string)$_POST[$key]) : $defaultValue;
    }

    if ($form['title'] === '') $errors[] = 'クーポンタイトルを入力してください。';
    if ($form['product_name'] === '') $errors[] = '商品名を入力してください。';
    if (!is_numeric($form['unit_price']) || (int)$form['unit_price'] <= 0) $errors[] = '商品単価は正の数で入力してください。';
    if (!is_numeric($form['cost_rate']) || (float)$form['cost_rate'] < 0 || (float)$form['cost_rate'] > 1) $errors[] = '原価率は 0〜1 の範囲で入力してください。';
    if (!is_numeric($form['initial_discount_rate']) || (float)$form['initial_discount_rate'] < 0 || (float)$form['initial_discount_rate'] > 1) $errors[] = '初期割引率は 0〜1 の範囲で入力してください。';
    if (!is_numeric($form['min_discount_rate']) || (float)$form['min_discount_rate'] < 0 || (float)$form['min_discount_rate'] > 1) $errors[] = '最低割引率は 0〜1 の範囲で入力してください。';
    if ((float)$form['min_discount_rate'] > (float)$form['initial_discount_rate']) $errors[] = '最低割引率は初期割引率以下で入力してください。';
    if (!is_numeric($form['decay_interval_minutes']) || (int)$form['decay_interval_minutes'] <= 0) $errors[] = '減衰間隔（分）は正の整数で入力してください。';
    if (!is_numeric($form['target_revenue']) || (int)$form['target_revenue'] <= 0) $errors[] = '目標売上は正の数で入力してください。';

    try {
        $start = new DateTimeImmutable($form['start_at']);
        $end = new DateTimeImmutable($form['end_at']);
        if ($start >= $end) {
            $errors[] = '終了日時は開始日時より後にしてください。';
        }
    } catch (Throwable $e) {
        $errors[] = '開始日時または終了日時の形式が不正です。';
    }

    if ($errors === []) {
        $result = buildSampleSimulation($form);
    }
}

if ($result === null && $errors === []) {
    $result = buildSampleSimulation($form);
}

$chartJson = $result !== null ? json_encode($result['chart'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>店舗ユーザー向けダッシュボード（サンプル）</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f7f7; color: #222; }
    .wrap { max-width: 1400px; margin: 0 auto; padding: 24px; }
    .bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
    .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 18px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    h1 { font-size: 24px; margin: 0; }
    h2 { font-size: 18px; margin: 0 0 12px; }
    .meta { color: #666; font-size: 13px; }
    .logout { text-decoration: none; border: 1px solid #999; color: #333; padding: 8px 14px; border-radius: 6px; background: #fff; }
    .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .field { display: flex; flex-direction: column; gap: 6px; }
    .field.wide { grid-column: span 2; }
    .field.full { grid-column: 1 / -1; }
    label { font-size: 13px; font-weight: 600; }
    input, textarea { border: 1px solid #bbb; border-radius: 6px; padding: 10px; font-size: 14px; background: #fff; }
    textarea { min-height: 88px; resize: vertical; }
    .actions { margin-top: 16px; display: flex; gap: 10px; }
    button { border: 1px solid #444; background: #222; color: #fff; padding: 10px 18px; border-radius: 6px; cursor: pointer; }
    .sub { background: #fff; color: #222; }
    .errors { background: #fff1f1; border: 1px solid #e0a5a5; color: #8f2222; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px; }
    .summary .box { border: 1px solid #ddd; border-radius: 8px; padding: 12px; background: #fafafa; }
    .summary .value { font-size: 20px; font-weight: 700; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: right; white-space: nowrap; }
    th:first-child, td:first-child, th:nth-child(2), td:nth-child(2) { text-align: center; }
    thead th { background: #f0f0f0; position: sticky; top: 0; }
    .caption { font-size: 13px; color: #666; margin-bottom: 10px; }
    .chartWrap { position: relative; width: 100%; height: 360px; }
    .row-hover { background: #fff8db !important; }
    .tableScroll { overflow: auto; max-height: 520px; border: 1px solid #ddd; border-radius: 8px; }
    @media (max-width: 1100px) {
      .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 700px) {
      .grid, .summary { grid-template-columns: 1fr; }
      .field.wide { grid-column: span 1; }
      .chartWrap { height: 280px; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <div>
      <h1>クーポン企画シュミレート（PHPサンプル）</h1>
      <div class="meta">ログイン中: <?php echo htmlspecialchars($loginUserName, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <a class="logout" href="logout.php">ログアウト</a>
  </div>

  <?php if ($errors !== []): ?>
    <div class="errors">
      <strong>入力内容を確認してください。</strong>
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>■ クーポン企画設定値</h2>
    <div class="caption">参考スプレッドシートの上段入力部に寄せた構成です。初期表示時にもデフォルト値が入ります。</div>
    <form method="post" action="">
      <div class="grid">
        <div class="field wide">
          <label for="title">クーポンタイトル</label>
          <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field wide">
          <label for="description">詳細説明</label>
          <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="product_name">商品名</label>
          <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($form['product_name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="unit_price">商品単価</label>
          <input type="number" id="unit_price" name="unit_price" value="<?php echo htmlspecialchars($form['unit_price'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="cost_rate">原価率（0〜1）</label>
          <input type="text" id="cost_rate" name="cost_rate" value="<?php echo htmlspecialchars($form['cost_rate'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="target_revenue">目標売上金額</label>
          <input type="number" id="target_revenue" name="target_revenue" value="<?php echo htmlspecialchars($form['target_revenue'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="initial_discount_rate">初期割引率（0〜1）</label>
          <input type="text" id="initial_discount_rate" name="initial_discount_rate" value="<?php echo htmlspecialchars($form['initial_discount_rate'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="min_discount_rate">最低割引率（0〜1）</label>
          <input type="text" id="min_discount_rate" name="min_discount_rate" value="<?php echo htmlspecialchars($form['min_discount_rate'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label for="decay_interval_minutes">減衰間隔（分）</label>
          <input type="number" id="decay_interval_minutes" name="decay_interval_minutes" value="<?php echo htmlspecialchars($form['decay_interval_minutes'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field wide">
          <label for="start_at">表示・利用開始日時</label>
          <input type="datetime-local" id="start_at" name="start_at" value="<?php echo htmlspecialchars($form['start_at'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field wide">
          <label for="end_at">表示・利用終了日時</label>
          <input type="datetime-local" id="end_at" name="end_at" value="<?php echo htmlspecialchars($form['end_at'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field full">
          <label for="rules">利用条件</label>
          <textarea id="rules" name="rules"><?php echo htmlspecialchars($form['rules'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="field full">
          <label for="notes">備考</label>
          <textarea id="notes" name="notes"><?php echo htmlspecialchars($form['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button type="submit" name="action" value="simulate">シュミレーション実行</button>
        <button class="sub" type="submit" name="action" value="save" disabled>この条件で保存（後続実装）</button>
      </div>
    </form>
  </div>

  <?php if ($result !== null): ?>
    <div class="card">
      <h2>■ シュミレーション結果</h2>
      <div class="summary">
        <div class="box">
          <div>開催日数</div>
          <div class="value"><?php echo htmlspecialchars((string)$result['summary']['duration_days'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="box">
          <div>減衰ステップ数</div>
          <div class="value"><?php echo htmlspecialchars((string)$result['summary']['step_count'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="box">
          <div>ステップ当たり減衰率</div>
          <div class="value"><?php echo htmlspecialchars((string)$result['summary']['decay_per_step'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="box">
          <div>想定CV人数合計</div>
          <div class="value"><?php echo htmlspecialchars((string)$result['summary']['total_cv'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="box">
          <div>想定売上合計</div>
          <div class="value"><?php echo number_format((int)$result['summary']['total_revenue']); ?></div>
        </div>
        <div class="box">
          <div>想定粗利益合計</div>
          <div class="value"><?php echo number_format((int)$result['summary']['total_gross']); ?></div>
        </div>
      </div>

      <div class="caption">参考スプレッドシート下段の結果表を意識し、日別 / ステップ別の結果行を同一画面下部に表示します。</div>

      <div class="card" style="margin-top:12px; background:#fcfcfc;">
        <h2 style="margin-bottom:8px;">■ 割引適用後価格推移グラフ</h2>
        <div class="caption">横軸：日付 / 縦軸：割引適用後価格。表の行にカーソルを合わせると、対応する点を強調します。</div>
        <div class="chartWrap">
          <canvas id="priceChart"></canvas>
        </div>
      </div>

      <div class="caption" style="margin-top:12px;">結果表にカーソルを合わせると、対応する日付のグラフ点が強調されます。</div>

      <div class="tableScroll">
        <table id="resultTable">
          <thead>
            <tr>
              <th>No</th>
              <th>日付</th>
              <th>割引率</th>
              <th>割引後価格</th>
              <th>CV人数</th>
              <th>CV人数累計</th>
              <th>利用割合</th>
              <th>売上金額</th>
              <th>売上金額累計</th>
              <th>売上割合</th>
              <th>割引額</th>
              <th>割引額累計</th>
              <th>割引割合</th>
              <th>粗利益</th>
              <th>粗利益累計</th>
              <th>粗利益割合</th>
              <th>1件あたり利益額</th>
              <th>1件当たり割引率</th>
              <th>1件あたり割引額</th>
              <th>1件あたり粗利率</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($result['rows'] as $index => $row): ?>
              <tr data-index="<?php echo (int)$index; ?>">
                <td><?php echo (int)$row['step']; ?></td>
                <td><?php echo htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo number_format((float)$row['discount_rate'] * 100, 2); ?>%</td>
                <td><?php echo number_format((int)$row['discounted_price']); ?></td>
                <td><?php echo number_format((int)$row['cv_count']); ?></td>
                <td><?php echo number_format((int)$row['cumulative_cv']); ?></td>
                <td><?php echo number_format((float)$row['cv_ratio'], 2); ?>%</td>
                <td><?php echo number_format((int)$row['revenue']); ?></td>
                <td><?php echo number_format((int)$row['cumulative_revenue']); ?></td>
                <td><?php echo number_format((float)$row['revenue_ratio'], 2); ?>%</td>
                <td><?php echo number_format((int)$row['discount_value']); ?></td>
                <td><?php echo number_format((int)$row['cumulative_discount']); ?></td>
                <td><?php echo number_format((float)$row['discount_ratio'], 2); ?>%</td>
                <td><?php echo number_format((int)$row['gross']); ?></td>
                <td><?php echo number_format((int)$row['cumulative_gross']); ?></td>
                <td><?php echo number_format((float)$row['gross_ratio'], 2); ?>%</td>
                <td><?php echo number_format((int)$row['unit_profit']); ?></td>
                <td><?php echo number_format((float)$row['unit_discount_rate'], 2); ?>%</td>
                <td><?php echo number_format((int)$row['unit_discount']); ?></td>
                <td><?php echo number_format((float)$row['unit_profit_rate'], 2); ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="caption" style="margin-top:12px;">
        ※ 将来拡張として、この下に「実績入力」「差分比較」「手動再調整UI」を追加しやすい構成を想定。
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($result !== null): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const chartSource = <?php echo $chartJson; ?>;
  const ctx = document.getElementById('priceChart').getContext('2d');
  const rowElements = Array.from(document.querySelectorAll('#resultTable tbody tr'));

  const defaultPointRadius = chartSource.prices.map(() => 4);
  const defaultPointHoverRadius = chartSource.prices.map(() => 6);
  const defaultPointBackgroundColor = chartSource.prices.map(() => 'rgba(54, 162, 235, 0.9)');

  const priceChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartSource.labels,
      datasets: [{
        label: '割引適用後価格',
        data: chartSource.prices,
        borderColor: 'rgba(54, 162, 235, 1)',
        backgroundColor: 'rgba(54, 162, 235, 0.12)',
        borderWidth: 3,
        tension: 0.25,
        fill: true,
        pointRadius: defaultPointRadius.slice(),
        pointHoverRadius: defaultPointHoverRadius.slice(),
        pointBackgroundColor: defaultPointBackgroundColor.slice(),
        pointBorderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'nearest',
        intersect: false
      },
      plugins: {
        legend: {
          display: true
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return ' 割引適用後価格: ¥' + Number(context.parsed.y).toLocaleString();
            }
          }
        }
      },
      scales: {
        x: {
          title: {
            display: true,
            text: '日付'
          }
        },
        y: {
          title: {
            display: true,
            text: '割引適用後価格'
          },
          ticks: {
            callback: function(value) {
              return '¥' + Number(value).toLocaleString();
            }
          }
        }
      }
    }
  });

  function clearHighlight() {
    rowElements.forEach(row => row.classList.remove('row-hover'));
    priceChart.data.datasets[0].pointRadius = defaultPointRadius.slice();
    priceChart.data.datasets[0].pointHoverRadius = defaultPointHoverRadius.slice();
    priceChart.data.datasets[0].pointBackgroundColor = defaultPointBackgroundColor.slice();
    priceChart.update('none');
  }

  function highlightIndex(index) {
    clearHighlight();
    if (index === null || index === undefined || index < 0 || index >= rowElements.length) return;

    rowElements[index].classList.add('row-hover');
    priceChart.data.datasets[0].pointRadius[index] = 8;
    priceChart.data.datasets[0].pointHoverRadius[index] = 10;
    priceChart.data.datasets[0].pointBackgroundColor[index] = 'rgba(255, 99, 132, 1)';
    priceChart.setActiveElements([{datasetIndex: 0, index: index}]);
    priceChart.update('none');
  }

  rowElements.forEach((row, idx) => {
    row.addEventListener('mouseenter', () => highlightIndex(idx));
    row.addEventListener('mouseleave', () => {
      priceChart.setActiveElements([]);
      clearHighlight();
    });
  });

  document.getElementById('priceChart').addEventListener('mouseleave', () => {
    clearHighlight();
    priceChart.setActiveElements([]);
    priceChart.update('none');
  });
</script>
<?php endif; ?>
</body>
</html>

<?php

/** @var callable $e */
/** @var string $month */

$title = '月次CSVエクスポート';

ob_start();
?>
<h1>月次CSVエクスポート</h1>
<p>監査要件に合わせた月次勤怠CSVを生成します。UTF-8 / Excel互換 (BOM付与) を切り替えられます。</p>

<form method="get" action="/admin/export">
    <label for="month">対象月 (YYYY-MM)</label>
    <input type="text" id="month" name="month" value="<?= $e($month); ?>" required pattern="\d{4}-\d{2}">

    <label>
        <input type="checkbox" name="bom" value="1"> Excel互換のBOMを付与する
    </label>

    <button type="submit" name="download" value="1">CSVをダウンロード</button>
</form>

<section>
    <h2>出力フォーマット</h2>
    <ul>
        <li>employee_code / display_name / role / work_date / clock_in_at / clock_out_at / break_minutes / total_work_minutes / total_work_hours / correction_flag</li>
        <li>時刻はUTCで保存されています。人事提出時にはローカル時間での表示を検討してください。</li>
        <li>correction_flag が 1 の行は承認済みの修正がある日です。</li>
    </ul>
</section>

<p><a href="/admin">管理ダッシュボードに戻る</a></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';

<?php
// process/export_process.php - Export CSV/PDF
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$userId = getCurrentUserId();
$pdo = getPDO();
$type = $_GET['type'] ?? 'transactions';
$format = $_GET['format'] ?? 'csv';

try {
    if ($format === 'csv') {
        exportCSV($type, $userId, $pdo);
    } elseif ($format === 'pdf') {
        exportPDF($type, $userId, $pdo);
    } else {
        throw new Exception('Format tidak didukung');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo "Error: " . $e->getMessage();
}

function exportCSV($type, $userId, $pdo) {
    $filename = 'sakuku_' . $type . '_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    switch ($type) {
        case 'transactions':
            fputcsv($output, ['Tanggal', 'Tipe', 'Kategori', 'Jumlah (Rp)', 'Deskripsi']);
            $stmt = $pdo->prepare('
                SELECT t.transaction_date, t.type, c.name as category, t.amount, t.description
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC, t.created_at DESC
            ');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                fputcsv($output, [
                    date('d/m/Y', strtotime($row['transaction_date'])),
                    $row['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
                    $row['category'] ?? '-',
                    $row['amount'],
                    $row['description']
                ]);
            }
            break;

        case 'budgets':
            fputcsv($output, ['Bulan', 'Kategori', 'Budget (Rp)', 'Terpakai (Rp)', 'Sisa (Rp)', 'Persen']);
            $stmt = $pdo->prepare('
                SELECT b.month_year, c.name as category, b.amount_limit,
                       COALESCE(SUM(CASE WHEN t.type="expense" THEN t.amount END), 0) as spent
                FROM budgets b
                JOIN categories c ON b.category_id = c.id
                LEFT JOIN transactions t ON t.user_id = b.user_id 
                    AND t.category_id = b.category_id 
                    AND t.type = "expense"
                    AND DATE_FORMAT(t.transaction_date, "%Y-%m") = b.month_year
                WHERE b.user_id = ?
                GROUP BY b.id
                ORDER BY b.month_year DESC, c.name
            ');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $spent = (int)$row['spent'];
                $limit = (int)$row['amount_limit'];
                $remaining = max(0, $limit - $spent);
                $pct = $limit > 0 ? round(($spent / $limit) * 100) : 0;
                fputcsv($output, [
                    date('m/Y', strtotime($row['month_year'] . '-01')),
                    $row['category'],
                    $limit,
                    $spent,
                    $remaining,
                    $pct . '%'
                ]);
            }
            break;

        case 'savings':
            fputcsv($output, ['Judul', 'Target (Rp)', 'Terkumpul (Rp)', 'Sisa (Rp)', 'Persen', 'Deadline', 'Status']);
            $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id = ? ORDER BY status, deadline');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $current = (int)$row['current_amount'];
                $target = (int)$row['target_amount'];
                $remaining = max(0, $target - $current);
                $pct = $target > 0 ? round(($current / $target) * 100) : 0;
                fputcsv($output, [
                    $row['title'],
                    $target,
                    $current,
                    $remaining,
                    $pct . '%',
                    $row['deadline'] ? date('d/m/Y', strtotime($row['deadline'])) : '-',
                    $row['status']
                ]);
            }
            break;

        case 'all':
            // Create ZIP with multiple CSVs
            $zip = new ZipArchive();
            $tempZip = sys_get_temp_dir() . '/sakuku_export_' . date('Ymd_His') . '.zip';
            if ($zip->open($tempZip, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Gagal membuat ZIP');
            }

            // Transactions
            $csvContent = "Tanggal,Tipe,Kategori,Jumlah (Rp),Deskripsi\n";
            $stmt = $pdo->prepare('
                SELECT t.transaction_date, t.type, c.name as category, t.amount, t.description
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC
            ');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $csvContent .= sprintf("%s,%s,%s,%d,\"%s\"\n",
                    date('d/m/Y', strtotime($row['transaction_date'])),
                    $row['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
                    $row['category'] ?? '-',
                    $row['amount'],
                    str_replace('"', '""', $row['description'])
                );
            }
            $zip->addFromString('transaksi.csv', "\xEF\xBB\xBF" . $csvContent);

            // Budgets
            $csvContent = "Bulan,Kategori,Budget (Rp),Terpakai (Rp),Sisa (Rp),Persen\n";
            $stmt = $pdo->prepare('
                SELECT b.month_year, c.name as category, b.amount_limit,
                       COALESCE(SUM(CASE WHEN t.type="expense" THEN t.amount END), 0) as spent
                FROM budgets b
                JOIN categories c ON b.category_id = c.id
                LEFT JOIN transactions t ON t.user_id = b.user_id 
                    AND t.category_id = b.category_id 
                    AND t.type = "expense"
                    AND DATE_FORMAT(t.transaction_date, "%Y-%m") = b.month_year
                WHERE b.user_id = ?
                GROUP BY b.id
            ');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $spent = (int)$row['spent'];
                $limit = (int)$row['amount_limit'];
                $remaining = max(0, $limit - $spent);
                $pct = $limit > 0 ? round(($spent / $limit) * 100) : 0;
                $csvContent .= sprintf("%s,%s,%d,%d,%d,%d%%\n",
                    date('m/Y', strtotime($row['month_year'] . '-01')),
                    $row['category'],
                    $limit, $spent, $remaining, $pct
                );
            }
            $zip->addFromString('budget.csv', "\xEF\xBB\xBF" . $csvContent);

            // Savings
            $csvContent = "Judul,Target (Rp),Terkumpul (Rp),Sisa (Rp),Persen,Deadline,Status\n";
            $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id = ?');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $current = (int)$row['current_amount'];
                $target = (int)$row['target_amount'];
                $remaining = max(0, $target - $current);
                $pct = $target > 0 ? round(($current / $target) * 100) : 0;
                $csvContent .= sprintf("%s,%d,%d,%d,%d%%,%s,%s\n",
                    $row['title'], $target, $current, $remaining, $pct,
                    $row['deadline'] ? date('d/m/Y', strtotime($row['deadline'])) : '-',
                    $row['status']
                );
            }
            $zip->addFromString('tabungan.csv', "\xEF\xBB\xBF" . $csvContent);

            // Categories
            $csvContent = "Tipe,Nama,Ikon\n";
            $stmt = $pdo->prepare('SELECT type, name, icon FROM categories WHERE user_id = ? ORDER BY type, name');
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $csvContent .= sprintf("%s,%s,%s\n",
                    $row['type'], $row['name'], $row['icon'] ?? ''
                );
            }
            $zip->addFromString('kategori.csv', "\xEF\xBB\xBF" . $csvContent);

            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="sakuku_export_' . date('Ymd_His') . '.zip"');
            readfile($tempZip);
            unlink($tempZip);
            exit;
            break;

        default:
            throw new Exception('Tipe ekspor tidak dikenal');
    }

    fclose($output);
}

function exportPDF($type, $userId, $pdo) {
    // Simple PDF using FPDF (single file)
    // For production, include FPDF library
    // Here we generate a simple HTML-to-PDF approach or basic PDF
    
    // Since we can't guarantee FPDF is installed, return HTML that can be printed to PDF
    // Or use a simple PDF generation
    
    $title = 'SakuKu - Laporan ' . ucfirst($type);
    $date = date('d M Y H:i');
    
    $html = '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>' . $title . '</title>
<style>
    body { font-family: "Source Sans 3", Arial, sans-serif; margin: 20px; color: #1e293b; }
    h1 { color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 10px; }
    .meta { color: #64748b; margin-bottom: 20px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
    th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
    th { background: #f1f5f9; font-weight: 600; }
    tr:nth-child(even) { background: #f8fafc; }
    .text-right { text-align: right; }
    .text-green { color: #059669; }
    .text-red { color: #dc2626; }
    @media print { body { margin: 0; } .no-print { display: none; } }
</style>
</head><body>
<div class="no-print" style="text-align:center; margin-bottom:20px; padding:10px; background:#f1f5f9; border-radius:8px;">
    <button onclick="window.print()" style="padding:10px 20px; background:#1e40af; color:white; border:none; border-radius:6px; cursor:pointer;">🖨 Cetak / Simpan sebagai PDF</button>
</div>
<h1>' . $title . '</h1>
<div class="meta">Dicetak pada: ' . $date . ' | User ID: ' . $userId . '</div>';

    switch ($type) {
        case 'transactions':
            $stmt = $pdo->prepare('
                SELECT t.transaction_date, t.type, c.name as category, t.amount, t.description
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC, t.created_at DESC
            ');
            $stmt->execute([$userId]);
            
            $html .= '<table><thead><tr><th>Tanggal</th><th>Tipe</th><th>Kategori</th><th class="text-right">Jumlah</th><th>Deskripsi</th></tr></thead><tbody>';
            $totalIn = 0; $totalOut = 0;
            while ($row = $stmt->fetch()) {
                $cls = $row['type'] === 'income' ? 'text-green' : 'text-red';
                $prefix = $row['type'] === 'income' ? '+' : '-';
                if ($row['type'] === 'income') $totalIn += $row['amount'];
                else $totalOut += $row['amount'];
                $html .= sprintf('<tr><td>%s</td><td><span class="%s">%s</span></td><td>%s</td><td class="text-right %s">%sRp%s</td><td>%s</td></tr>',
                    date('d/m/Y', strtotime($row['transaction_date'])),
                    $cls,
                    $row['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
                    e($row['category'] ?? '-'),
                    $cls,
                    $prefix,
                    number_format($row['amount'], 0, ',', '.'),
                    e($row['description'])
                );
            }
            $html .= sprintf('<tr style="font-weight:bold; background:#f1f5f9;"><td colspan="3">TOTAL</td><td class="text-right text-green">+Rp%s</td><td></td></tr>', number_format($totalIn, 0, ',', '.'));
            $html .= sprintf('<tr style="font-weight:bold; background:#f1f5f9;"><td colspan="3"></td><td class="text-right text-red">-Rp%s</td><td></td></tr>', number_format($totalOut, 0, ',', '.'));
            $html .= sprintf('<tr style="font-weight:bold; background:#e0f2fe;"><td colspan="3">SALDO</td><td class="text-right">Rp%s</td><td></td></tr>', number_format($totalIn - $totalOut, 0, ',', '.'));
            $html .= '</tbody></table>';
            break;

        case 'budgets':
            $stmt = $pdo->prepare('
                SELECT b.month_year, c.name as category, b.amount_limit,
                       COALESCE(SUM(CASE WHEN t.type="expense" THEN t.amount END), 0) as spent
                FROM budgets b
                JOIN categories c ON b.category_id = c.id
                LEFT JOIN transactions t ON t.user_id = b.user_id 
                    AND t.category_id = b.category_id 
                    AND t.type = "expense"
                    AND DATE_FORMAT(t.transaction_date, "%Y-%m") = b.month_year
                WHERE b.user_id = ?
                GROUP BY b.id
                ORDER BY b.month_year DESC, c.name
            ');
            $stmt->execute([$userId]);
            
            $html .= '<table><thead><tr><th>Bulan</th><th>Kategori</th><th class="text-right">Budget</th><th class="text-right">Terpakai</th><th class="text-right">Sisa</th><th class="text-right">%</th></tr></thead><tbody>';
            while ($row = $stmt->fetch()) {
                $spent = (int)$row['spent'];
                $limit = (int)$row['amount_limit'];
                $remaining = max(0, $limit - $spent);
                $pct = $limit > 0 ? round(($spent / $limit) * 100) : 0;
                $cls = $pct >= 100 ? 'text-red' : ($pct >= 80 ? 'text-orange' : '');
                $html .= sprintf('<tr><td>%s</td><td>%s</td><td class="text-right">Rp%s</td><td class="text-right %s">Rp%s</td><td class="text-right">Rp%s</td><td class="text-right %s">%d%%</td></tr>',
                    date('m/Y', strtotime($row['month_year'] . '-01')),
                    e($row['category']),
                    number_format($limit, 0, ',', '.'),
                    $cls,
                    number_format($spent, 0, ',', '.'),
                    number_format($remaining, 0, ',', '.'),
                    $cls,
                    $pct
                );
            }
            $html .= '</tbody></table>';
            break;

        default:
            $html .= '<p>Tipe laporan tidak dikenali.</p>';
    }

    $html .= '</body></html>';
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="sakuku_' . $type . '_' . date('Ymd_His') . '.html"');
    echo $html;
}
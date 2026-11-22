<?php
// includes/helpers.php - Fungsi format Rupiah, hitung saldo, cek budget, dll
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';

/**
 * Format angka ke Rupiah Indonesia
 * Contoh: 50000 -> "Rp50.000"
 */
function formatRupiah(int|float $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

/**
 * Parse string Rupiah ke integer
 * Contoh: "Rp50.000" -> 50000
 */
function parseRupiah(string $rupiah): int
{
    $clean = preg_replace('/[^0-9]/', '', $rupiah);
    return (int)$clean;
}

/**
 * Sanitasi output untuk mencegah XSS
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Hitung total pemasukan user
 */
function getTotalIncome(int $userId, ?string $startDate = null, ?string $endDate = null): int
{
    $pdo = getPDO();
    $sql = 'SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = "income"';
    $params = [$userId];
    
    if ($startDate) {
        $sql .= ' AND transaction_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= ' AND transaction_date <= ?';
        $params[] = $endDate;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Hitung total pengeluaran user
 */
function getTotalExpense(int $userId, ?string $startDate = null, ?string $endDate = null): int
{
    $pdo = getPDO();
    $sql = 'SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = "expense"';
    $params = [$userId];
    
    if ($startDate) {
        $sql .= ' AND transaction_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= ' AND transaction_date <= ?';
        $params[] = $endDate;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Hitung total tabungan aktif user
 */
function getTotalActiveSavings(int $userId): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(current_amount), 0) as total FROM savings_goals WHERE user_id = ? AND status = "active"');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Hitung saldo tersedia: (Total Pemasukan - Total Pengeluaran - Total Tabungan Aktif)
 */
function getAvailableBalance(int $userId): int
{
    $income = getTotalIncome($userId);
    $expense = getTotalExpense($userId);
    $savings = getTotalActiveSavings($userId);
    return $income - $expense - $savings;
}

/**
 * Cek status budget kategori
 * Return: 'safe' | 'warning' | 'overbudget' | null (jika tidak ada budget)
 */
function checkBudgetStatus(int $userId, int $categoryId, string $monthYear): ?string
{
    $pdo = getPDO();
    
    // Ambil budget limit
    $stmt = $pdo->prepare('SELECT amount_limit FROM budgets WHERE user_id = ? AND category_id = ? AND month_year = ?');
    $stmt->execute([$userId, $categoryId, $monthYear]);
    $budget = $stmt->fetch();
    
    if (!$budget) {
        return null; // Tidak ada budget untuk kategori ini
    }
    
    // Hitung pengeluaran bulan ini untuk kategori tersebut
    $startDate = $monthYear . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM transactions 
        WHERE user_id = ? AND category_id = ? AND type = "expense" 
        AND transaction_date BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $categoryId, $startDate, $endDate]);
    $spent = (int)$stmt->fetchColumn();
    
    $limit = (int)$budget['amount_limit'];
    $percentage = $limit > 0 ? ($spent / $limit) * 100 : 0;
    
    if ($percentage >= 100) {
        return 'overbudget';
    } elseif ($percentage >= 80) {
        return 'warning';
    } else {
        return 'safe';
    }
}

/**
 * Get budget alert message untuk dashboard
 */
function getBudgetAlerts(int $userId): array
{
    $pdo = getPDO();
    $monthYear = date('Y-m');
    
    $stmt = $pdo->prepare('
        SELECT b.amount_limit, c.name as category_name, c.id as category_id
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ? AND b.month_year = ?
    ');
    $stmt->execute([$userId, $monthYear]);
    $budgets = $stmt->fetchAll();
    
    $alerts = [];
    foreach ($budgets as $budget) {
        $status = checkBudgetStatus($userId, $budget['category_id'], $monthYear);
        if ($status === 'warning' || $status === 'overbudget') {
            $startDate = $monthYear . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $stmt = $pdo->prepare('
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE user_id = ? AND category_id = ? AND type = "expense" 
                AND transaction_date BETWEEN ? AND ?
            ');
            $stmt->execute([$userId, $budget['category_id'], $startDate, $endDate]);
            $spent = (int)$stmt->fetchColumn();
            
            $alerts[] = [
                'category' => $budget['category_name'],
                'limit' => (int)$budget['amount_limit'],
                'spent' => $spent,
                'percentage' => round(($spent / $budget['amount_limit']) * 100),
                'status' => $status,
            ];
        }
    }
    return $alerts;
}

/**
 * Hitung net balance untuk split bill grup
 * Return array: user_id => net_balance (positif = piutang, negatif = hutang)
 */
function calculateGroupNetBalances(int $groupId): array
{
    $pdo = getPDO();
    
    // Ambil semua expense shares (new schema)
    $stmt = $pdo->prepare('
        SELECT es.user_id, 
               SUM(es.share_amount) as total_owed,
               SUM(CASE WHEN ge.paid_by = es.user_id THEN ge.total_amount ELSE 0 END) as total_paid
        FROM expense_shares es
        JOIN group_expenses ge ON es.expense_id = ge.id
        WHERE ge.group_id = ?
        GROUP BY es.user_id
    ');
    $stmt->execute([$groupId]);
    $splits = $stmt->fetchAll();
    
    $balances = [];
    foreach ($splits as $split) {
        $paid = (int)$split['total_paid'];
        $owed = (int)$split['total_owed'];
        $balances[$split['user_id']] = $paid - $owed; // Positif = harus menerima, Negatif = harus bayar
    }
    
    return $balances;
}

/**
 * Generate simplified settlement instructions
 * Return array of: ['from' => user_id, 'to' => user_id, 'amount' => int]
 */
function generateSettlementInstructions(int $groupId): array
{
    $balances = calculateGroupNetBalances($groupId);
    
    // Pisah debtor (negatif) dan creditor (positif)
    $debtors = [];
    $creditors = [];
    
    foreach ($balances as $userId => $balance) {
        if ($balance < 0) {
            $debtors[] = ['user_id' => $userId, 'amount' => abs($balance)];
        } elseif ($balance > 0) {
            $creditors[] = ['user_id' => $userId, 'amount' => $balance];
        }
    }
    
    // Sort by amount descending
    usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);
    
    $instructions = [];
    $dIdx = 0;
    $cIdx = 0;
    
    while ($dIdx < count($debtors) && $cIdx < count($creditors)) {
        $debtor = &$debtors[$dIdx];
        $creditor = &$creditors[$cIdx];
        
        $amount = min($debtor['amount'], $creditor['amount']);
        
        $instructions[] = [
            'from' => $debtor['user_id'],
            'to' => $creditor['user_id'],
            'amount' => $amount,
        ];
        
        $debtor['amount'] -= $amount;
        $creditor['amount'] -= $amount;
        
        if ($debtor['amount'] === 0) $dIdx++;
        if ($creditor['amount'] === 0) $cIdx++;
    }
    
    return $instructions;
}

/**
 * Cek apakah semua member grup net_balance = 0
 */
function isGroupBalanced(int $groupId): bool
{
    $balances = calculateGroupNetBalances($groupId);
    foreach ($balances as $balance) {
        if ($balance !== 0) {
            return false;
        }
    }
    return true;
}

/**
 * Generate invite code unik untuk grup
 */
function generateInviteCode(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Tanpa I, O, 1, 0
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Hitung pembagian tagihan (split bill)
 * Pembagi terakhir mendapat sisa (remainder) untuk akurasi Rupiah
 * 
 * @param int $totalAmount Total jumlah yang dibagi
 * @param array|int $members Array user_id atau jumlah orang (int)
 * @param string $method 'equal' | 'custom' | 'shares'
 * @param array $customShares Untuk method custom/shares: [user_id => weight/percentage]
 * @return array [user_id => amount]
 */
function splitBillCalculate(int $totalAmount, array|int $members, string $method = 'equal', array $customShares = []): array
{
    $memberList = is_array($members) ? $members : range(1, $members);
    $count = count($memberList);
    
    if ($count === 0) return [];
    if ($count === 1) return [$memberList[0] => $totalAmount];
    
    $shares = [];
    
    if ($method === 'custom' && $customShares) {
        // Custom percentage/weight per member
        $totalWeight = array_sum($customShares);
        if ($totalWeight <= 0) throw new Exception('Total weight harus > 0');
        
        $remainder = $totalAmount;
        foreach ($memberList as $i => $memberId) {
            $weight = $customShares[$memberId] ?? 0;
            $share = (int)floor($totalAmount * $weight / $totalWeight);
            if ($i === $count - 1) {
                $share = $remainder; // Last gets remainder
            }
            $shares[$memberId] = $share;
            $remainder -= $share;
        }
    } elseif ($method === 'shares' && $customShares) {
        // Shares-based (e.g., [1, 1, 2] = 4 parts)
        $totalShares = array_sum($customShares);
        $remainder = $totalAmount;
        foreach ($memberList as $i => $memberId) {
            $memberShares = $customShares[$memberId] ?? 1;
            $share = (int)floor($totalAmount * $memberShares / $totalShares);
            if ($i === $count - 1) {
                $share = $remainder;
            }
            $shares[$memberId] = $share;
            $remainder -= $share;
        }
    } else {
        // Equal split - divider (last) gets remainder
        $baseShare = (int)floor($totalAmount / $count);
        $remainder = $totalAmount - ($baseShare * $count);
        
        foreach ($memberList as $i => $memberId) {
            $shares[$memberId] = $baseShare + ($i === $count - 1 ? $remainder : 0);
        }
    }
    
    return $shares;
}

/**
 * Validasi nominal uang (harus > 0)
 */
function validateAmount(int|float $amount): bool
{
    return $amount > 0;
}

/**
 * Validasi top-up tabungan
 * Cek: savings goal exists, status active, amount > 0, tidak melebihi target, saldo tersedia cukup
 */
function validateSavingsTopUp(int $userId, int $savingsId, int $amount, PDO $pdo): void
{
    if ($amount <= 0) throw new Exception('Nominal top-up harus > 0');
    
    $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id = ? AND user_id = ?');
    $stmt->execute([$savingsId, $userId]);
    $goal = $stmt->fetch();
    if (!$goal) throw new Exception('Target tabungan tidak ditemukan');
    if ($goal['status'] !== 'active') throw new Exception('Target tabungan tidak aktif');
    
    $current = (int)$goal['current_amount'];
    $target = (int)$goal['target_amount'];
    if ($current + $amount > $target) {
        throw new Exception('Top-up melebihi target tabungan (sisa: ' . formatRupiah($target - $current) . ')');
    }
    
    $available = getAvailableBalance($userId);
    if ($amount > $available) {
        throw new Exception('Saldo tersedia tidak mencukupi (tersedia: ' . formatRupiah($available) . ')');
    }
}

/**
 * Validasi penarikan tabungan
 * Cek: savings goal exists, amount > 0, tidak melebihi current_amount
 */
function validateSavingsWithdraw(int $userId, int $savingsId, int $amount, PDO $pdo): void
{
    if ($amount <= 0) throw new Exception('Nominal penarikan harus > 0');
    
    $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id = ? AND user_id = ?');
    $stmt->execute([$savingsId, $userId]);
    $goal = $stmt->fetch();
    if (!$goal) throw new Exception('Target tabungan tidak ditemukan');
    
    $current = (int)$goal['current_amount'];
    if ($amount > $current) {
        throw new Exception('Penarikan melebihi dana terkumpul (tersedia: ' . formatRupiah($current) . ')');
    }
}

/**
 * Validasi panjang string
 */
function validateLength(string $value, int $maxLength): bool
{
    return mb_strlen($value) <= $maxLength;
}
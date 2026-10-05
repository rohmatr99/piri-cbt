<?php

declare(strict_types=1);

date_default_timezone_set("Asia/Jakarta");
session_start();

require_once __DIR__ . "/../config/database.php";

/*
|--------------------------------------------------------------------------
| CEK LOGIN ADMIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_nama = $_SESSION["admin_nama"] ?? "Administrator";
$admin_role = $_SESSION["admin_role"] ?? "admin";

/*
|--------------------------------------------------------------------------
| AMBIL SESI YANG SEDANG TERKUNCI
|--------------------------------------------------------------------------
|
| Admin biasa hanya dapat melihat ujian dari mapel yang menjadi haknya.
| Superadmin dapat melihat semua.
|
| Kode hanya diambil jika:
| - sesi masih mengerjakan
| - sesi benar-benar terkunci pengawas
| - batas waktu belum lewat
| - kode masih aktif
|
*/

$sql = "
    SELECT
        su.id AS sesi_id,
        su.siswa_id,
        su.ujian_id,
        su.mulai,
        su.batas_waktu,
        s.nama AS nama_siswa,
        s.username,
        s.kelas,
        u.nama_ujian,
        m.nama AS nama_mapel,
        kp.id AS kode_id,
        kp.kode,
        kp.dibuat
    FROM sesi_ujian su
    INNER JOIN siswa s
        ON s.id = su.siswa_id
    INNER JOIN ujian u
        ON u.id = su.ujian_id
    LEFT JOIN mata_pelajaran m
        ON m.id = u.mapel_id
    INNER JOIN kode_pengawas_ujian kp
        ON kp.sesi_id = su.id
       AND kp.status = 'aktif'
    ";

$params = [];
$types = "";

if ($admin_role !== "superadmin") {
    $sql .= "
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
    ";

    $params[] = $admin_id;
    $types .= "i";
}

$sql .= "
    WHERE su.status = 'mengerjakan'
      AND su.terkunci_pengawas = 1
      AND su.batas_waktu > NOW()
    ORDER BY kp.dibuat DESC, su.id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query kode pengawas gagal: " . htmlspecialchars($conn->error));
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$daftar = [];

while ($row = $result->fetch_assoc()) {
    $daftar[] = $row;
}

$stmt->close();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<title>Kode Pengawas — PIRI CBT</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    color: #0f172a;
}
.topbar {
    background: #0f172a;
    color: white;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.brand { font-size: 20px; font-weight: 800; }
.admin { font-size: 13px; opacity: .85; }
.container {
    width: min(1180px, calc(100% - 32px));
    margin: 28px auto;
}
.header-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 25px rgba(15,23,42,.07);
    margin-bottom: 20px;
}
h1 { margin: 0 0 7px; font-size: 27px; }
.subtitle { margin: 0; color: #64748b; }
.actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
.btn {
    display: inline-block;
    text-decoration: none;
    border: 0;
    border-radius: 9px;
    padding: 10px 15px;
    font-weight: 700;
    cursor: pointer;
}
.btn-refresh { background: #2563eb; color: white; }
.btn-back { background: #e2e8f0; color: #0f172a; }
.card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(15,23,42,.07);
    overflow: hidden;
}
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 850px; }
th, td {
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
}
th {
    background: #f8fafc;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #475569;
}
td { font-size: 14px; }
.code {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 9px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 21px;
    font-weight: 900;
    letter-spacing: .12em;
    font-variant-numeric: tabular-nums;
}
.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 12px;
    font-weight: 800;
}
.empty {
    padding: 45px 24px;
    text-align: center;
    color: #64748b;
}
.note {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-size: 13px;
}
@media (max-width: 650px) {
    .topbar { padding: 15px 16px; }
    .container { width: min(100% - 20px, 1180px); margin: 16px auto; }
    .header-card { padding: 18px; }
    h1 { font-size: 23px; }
}
</style>
</head>
<body>

<header class="topbar">
    <div class="brand">🛡️ PIRI CBT — Kode Pengawas</div>
    <div class="admin">👤 <?= e((string)$admin_nama) ?></div>
</header>

<main class="container">

    <section class="header-card">
        <h1>🔑 Kode Pengawas Aktif</h1>
        <p class="subtitle">
            Kode yang tampil di halaman ini hanya untuk pengawas/admin dan digunakan untuk membuka sesi ujian yang terkunci.
        </p>

        <div class="actions">
            <a class="btn btn-refresh" href="kode-pengawas.php">↻ Refresh</a>
            <a class="btn btn-back" href="dashboard.php">← Dashboard</a>
        </div>

        <div class="note">
            Jangan memberikan kode kepada siswa selain siswa yang sesuai dengan sesi pada baris tersebut.
            Setelah berhasil digunakan, kode otomatis berubah status menjadi <strong>digunakan</strong> dan tidak dapat dipakai kembali.
        </div>
    </section>

    <section class="card">
        <?php if (!$daftar): ?>
            <div class="empty">
                <div style="font-size:42px; margin-bottom:10px;">🔒</div>
                <strong>Tidak ada sesi yang sedang terkunci.</strong>
                <div style="margin-top:7px;">Jika siswa baru saja keluar fullscreen, tekan Refresh.</div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sesi</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Ujian</th>
                            <th>Mapel</th>
                            <th>Kode Pengawas</th>
                            <th>Dibuat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $row): ?>
                        <tr>
                            <td><strong>#<?= (int)$row["sesi_id"] ?></strong></td>
                            <td>
                                <strong><?= e((string)$row["nama_siswa"]) ?></strong><br>
                                <small style="color:#64748b;"><?= e((string)$row["username"]) ?></small>
                            </td>
                            <td><?= e((string)$row["kelas"]) ?></td>
                            <td><?= e((string)$row["nama_ujian"]) ?></td>
                            <td><?= e((string)($row["nama_mapel"] ?? "-")) ?></td>
                            <td><span class="code"><?= e((string)$row["kode"]) ?></span></td>
                            <td><?= e((string)$row["dibuat"]) ?></td>
                            <td><span class="badge">AKTIF</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

</body>
</html>

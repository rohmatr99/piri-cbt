<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| CEK LOGIN ADMIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER["PHP_SELF"]);

$admin_id   = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

/*
|--------------------------------------------------------------------------
| AMBIL FILTER
|--------------------------------------------------------------------------
*/

$ujian_id = isset($_GET["ujian_id"])
    ? (int)$_GET["ujian_id"]
    : 0;

$kelas = trim($_GET["kelas"] ?? "");

$siswa_id = isset($_GET["siswa_id"])
    ? (int)$_GET["siswa_id"]
    : 0;

/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR UJIAN SESUAI HAK AKSES
|--------------------------------------------------------------------------
*/

if ($admin_role === "superadmin") {

    $stmtUjian = $conn->prepare("
        SELECT
            id,
            nama_ujian
        FROM ujian
        ORDER BY id DESC
    ");

} else {

    $stmtUjian = $conn->prepare("
        SELECT DISTINCT
            u.id,
            u.nama_ujian
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        ORDER BY u.id DESC
    ");

    $stmtUjian->bind_param("i", $admin_id);
}

if (!$stmtUjian || !$stmtUjian->execute()) {
    die(
        "Gagal mengambil daftar ujian: "
        . $conn->error
    );
}

$result_ujian = $stmtUjian->get_result();

/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR KELAS
|--------------------------------------------------------------------------
*/

$result_kelas = $conn->query("
    SELECT DISTINCT kelas
    FROM siswa
    WHERE kelas IS NOT NULL
      AND kelas != ''
    ORDER BY kelas ASC
");

if (!$result_kelas) {
    die(
        "Gagal mengambil daftar kelas: "
        . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR SISWA
|--------------------------------------------------------------------------
*/

$result_siswa = $conn->query("
    SELECT
        id,
        nama,
        username,
        kelas
    FROM siswa
    ORDER BY nama ASC
");

if (!$result_siswa) {
    die(
        "Gagal mengambil daftar siswa: "
        . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| QUERY HASIL UJIAN
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        h.id,
        h.siswa_id,
        h.ujian_id,
        h.jumlah_soal,
        h.jumlah_dijawab,
        h.jumlah_benar,
        h.jumlah_salah,
        h.nilai,

        s.nama,
        s.username,
        s.kelas,

        u.nama_ujian

    FROM hasil_ujian h

    LEFT JOIN siswa s
        ON h.siswa_id = s.id

    LEFT JOIN ujian u
        ON h.ujian_id = u.id
";

/*
|--------------------------------------------------------------------------
| BATAS AKSES MAPEL UNTUK ADMIN/GURU
|--------------------------------------------------------------------------
*/

$params = [];
$types = "";

if ($admin_role !== "superadmin") {

    $sql .= "
        INNER JOIN admin_mapel am_hasil
            ON am_hasil.mapel_id = u.mapel_id
           AND am_hasil.admin_id = ?
    ";

    $params[] = $admin_id;
    $types .= "i";
}

$sql .= "
    WHERE 1 = 1
";

/*
|--------------------------------------------------------------------------
| FILTER UJIAN
|--------------------------------------------------------------------------
*/

if ($ujian_id > 0) {

    $sql .= "
        AND h.ujian_id = ?
    ";

    $params[] = $ujian_id;
    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| FILTER KELAS
|--------------------------------------------------------------------------
*/

if ($kelas !== "") {

    $sql .= "
        AND s.kelas = ?
    ";

    $params[] = $kelas;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| FILTER SISWA
|--------------------------------------------------------------------------
*/

if ($siswa_id > 0) {

    $sql .= "
        AND h.siswa_id = ?
    ";

    $params[] = $siswa_id;
    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| URUTKAN
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY h.id DESC
";

/*
|--------------------------------------------------------------------------
| JALANKAN QUERY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Query gagal: "
        . $conn->error
    );
}

if (count($params) > 0) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}

$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    die(
        "Gagal mengambil hasil ujian: "
        . $stmt->error
    );
}

/*
|--------------------------------------------------------------------------
| HITUNG REKAP
|--------------------------------------------------------------------------
*/

$total_peserta =
    $result->num_rows;

$total_nilai = 0;

$nilai_tertinggi = null;

$nilai_terendah = null;

while (
    $data_rekap =
    $result->fetch_assoc()
) {

    $nilai =
        (float)$data_rekap["nilai"];

    $total_nilai += $nilai;

    if (
        $nilai_tertinggi === null ||
        $nilai > $nilai_tertinggi
    ) {

        $nilai_tertinggi =
            $nilai;

    }

    if (
        $nilai_terendah === null ||
        $nilai < $nilai_terendah
    ) {

        $nilai_terendah =
            $nilai;

    }

}

/*
|--------------------------------------------------------------------------
| RATA-RATA
|--------------------------------------------------------------------------
*/

$rata_rata = 0;

if ($total_peserta > 0) {

    $rata_rata =
        $total_nilai /
        $total_peserta;

}

/*
|--------------------------------------------------------------------------
| QUERY ULANG UNTUK TABEL
|--------------------------------------------------------------------------
*/

$stmt->execute();

$result =
    $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Ujian - PIRI CBT</title>
<style>

        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --sidebar: #0f172a;
            --sidebar-soft: #1e293b;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #dc2626;
            --success: #16a34a;
        }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        body {
            display: flex;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            color: white;
            padding: 22px 16px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 18px rgba(15,23,42,.12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 10px 24px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .brand-text strong {
            display: block;
            font-size: 17px;
            letter-spacing: .3px;
        }

        .brand-text span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 11px;
        }

        .menu-title {
            margin: 24px 10px 10px;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            transition: .18s ease;
        }

        .nav a:hover {
            background: var(--sidebar-soft);
            color: white;
            transform: translateX(2px);
        }

        .nav a.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 8px 18px rgba(37,99,235,.22);
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .admin-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 10px;
            background: rgba(255,255,255,.05);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-mini strong {
            display: block;
            font-size: 13px;
        }

        .admin-mini span {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 11px;
        }

        .logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 9px;
            background: rgba(220,38,38,.12);
            color: #fca5a5;
            text-decoration: none;
            font-size: 13px;
        }

        .logout:hover {
            background: #dc2626;
            color: white;
        }

        .main {
            width: 100%;
            min-height: 100vh;
            margin-left: 260px;
        }

        .topbar {
            height: 72px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .page-title h1 {
            margin: 0;
            font-size: 20px;
        }

        .page-title p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .top-user {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 13px;
        }

        .top-user-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #eff6ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            padding: 28px 32px 40px;
            max-width: 1500px;
        }

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 14px rgba(15,23,42,.05);
        }

        .content-card h2 {
            margin: 0 0 6px;
            font-size: 23px;
        }

        .content-card > p {
            margin-top: 0;
            color: var(--muted);
        }

        .filter {
            margin-top: 20px;
            padding: 17px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .filter label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
            font-size: 13px;
        }

        .filter select {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .tombol {
            margin-top: 15px;
        }

        .tombol button,
        .tombol a {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            margin-right: 5px;
        }

        .tampilkan { background: #198754; color: white; }
        .reset { background: #64748b; color: white; }
        .export { background: #0d6efd; color: white; }
        .cetak { background: #7c3aed; color: white; }

        .rekap {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 22px 0;
        }

        .rekap-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 18px;
            border-radius: 12px;
        }

        .rekap-box span {
            display: block;
            color: #64748b;
            font-size: 12px;
        }

        .rekap-box strong {
            display: block;
            margin-top: 7px;
            font-size: 25px;
        }

        .table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            min-width: 900px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            white-space: nowrap;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .nilai {
            font-weight: 700;
        }

        .checkbox-hasil {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .area-masal {
            margin: 15px 0;
            padding: 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
        }

        .btn-reset-masal {
            display: inline-block;
            margin-left: 15px;
            padding: 9px 14px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .kosong {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .mobile-header {
            display: none;
        }

        .overlay {
            display: none;
        }

        @media (max-width: 1050px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .rekap {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 760px) {
            body {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform .22s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                display: none;
            }

            .mobile-header {
                height: 62px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                background: white;
                border-bottom: 1px solid var(--border);
                position: sticky;
                top: 0;
                z-index: 800;
            }

            .mobile-title {
                font-weight: 700;
                font-size: 15px;
            }

            .menu-button {
                width: 40px;
                height: 40px;
                border: 0;
                border-radius: 10px;
                background: #eff6ff;
                color: var(--primary);
                font-size: 20px;
                cursor: pointer;
            }

            .overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15,23,42,.45);
                z-index: 950;
            }

            .content {
                padding: 20px 16px 30px;
            }

            .content-card {
                padding: 18px;
                border-radius: 14px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .rekap {
                grid-template-columns: 1fr 1fr;
            }

            .tombol a,
            .tombol button {
                margin-bottom: 7px;
            }
        }

        @media print {
            .sidebar,
            .topbar,
            .mobile-header,
            .filter,
            .tombol,
            .area-masal,
            .aksi,
            .no-print {
                display: none !important;
            }

            .main {
                margin-left: 0;
            }

            .content {
                max-width: none;
                padding: 0;
            }

            .content-card {
                box-shadow: none;
                border: 0;
                padding: 0;
            }

            .rekap {
                grid-template-columns: repeat(4, 1fr);
            }

            table {
                min-width: 0;
                font-size: 11px;
            }

            th, td {
                font-size: 11px;
            }
        }

</style>
</head>
<body>

<aside class="sidebar" id="sidebar">

    <div class="brand">
        <div class="brand-logo">📝</div>
        <div class="brand-text">
            <strong>PIRI CBT</strong>
            <span>ADMINISTRATOR PANEL</span>
        </div>
    </div>

    <div class="menu-title">Menu Utama</div>

    <nav class="nav">

        <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <a href="siswa.php" class="<?= $current_page === 'siswa.php' ? 'active' : '' ?>">
            <span class="nav-icon">👨‍🎓</span>
            <span>Data Siswa</span>
        </a>

        <a href="ujian.php" class="<?= $current_page === 'ujian.php' ? 'active' : '' ?>">
            <span class="nav-icon">📝</span>
            <span>Ujian</span>
        </a>

        <a href="bank-soal.php" class="<?= $current_page === 'bank-soal.php' ? 'active' : '' ?>">
            <span class="nav-icon">📚</span>
            <span>Bank Soal</span>
        </a>

        <a href="hasil-ujian.php" class="<?= $current_page === 'hasil-ujian.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Hasil Ujian</span>
        </a>

        <a href="pelanggaran.php" class="<?= $current_page === 'pelanggaran.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚠️</span>
            <span>Pelanggaran Ujian</span>
        </a>

    </nav>

    <div class="sidebar-bottom">

        <div class="admin-mini">
            <div class="avatar">👤</div>
            <div>
                <strong><?= htmlspecialchars($_SESSION["admin_nama"] ?? "Administrator") ?></strong>
                <span>Administrator</span>
            </div>
        </div>

        <a href="logout.php" class="logout no-print">
            🚪 Keluar
        </a>

    </div>

</aside>

<div class="overlay" id="overlay" onclick="tutupSidebar()"></div>

<main class="main">

    <header class="topbar">
        <div class="page-title">
            <h1>Hasil Ujian</h1>
            <p>Rekap, nilai, detail, export, cetak, dan reset hasil ujian</p>
        </div>

        <div class="top-user">
            <div class="top-user-icon">👤</div>
            <span><?= htmlspecialchars($_SESSION["admin_nama"] ?? "Administrator") ?></span>
        </div>
    </header>

    <header class="mobile-header">
        <button type="button" class="menu-button" onclick="bukaSidebar()">☰</button>
        <div class="mobile-title">PIRI CBT — Admin</div>
        <div style="width:40px;"></div>
    </header>

    <section class="content">
        <div class="content-card">



<h2>
Hasil Ujian
</h2>


<p>

Selamat datang,
<strong>
<?= htmlspecialchars(
    $_SESSION["admin_nama"]
) ?>
</strong>

</p>


<!-- =========================================================
     FILTER
========================================================= -->


<div class="filter no-print">

<form
    method="GET"
    action="hasil-ujian.php"
>


<div class="filter-grid">


<div>

<label>
Ujian
</label>


<select name="ujian_id">

<option value="0">
-- Semua Ujian --
</option>


<?php while (
    $ujian =
    $result_ujian->fetch_assoc()
): ?>

<option
    value="<?= (int) $ujian["id"] ?>"
    <?= $ujian_id == $ujian["id"]
        ? "selected"
        : ""
    ?>
>

<?= htmlspecialchars(
    $ujian["nama_ujian"]
) ?>

</option>

<?php endwhile; ?>


</select>

</div>


<div>

<label>
Kelas
</label>


<select name="kelas">

<option value="">
-- Semua Kelas --
</option>


<?php while (
    $data_kelas =
    $result_kelas->fetch_assoc()
): ?>

<option
    value="<?= htmlspecialchars(
        $data_kelas["kelas"]
    ) ?>"
    <?= $kelas === $data_kelas["kelas"]
        ? "selected"
        : ""
    ?>
>

<?= htmlspecialchars(
    $data_kelas["kelas"]
) ?>

</option>

<?php endwhile; ?>


</select>

</div>


<div>

<label>
Siswa
</label>


<select name="siswa_id">

<option value="0">
-- Semua Siswa --
</option>


<?php while (
    $data_siswa =
    $result_siswa->fetch_assoc()
): ?>

<option
    value="<?= (int) $data_siswa["id"] ?>"
    <?= $siswa_id == $data_siswa["id"]
        ? "selected"
        : ""
    ?>
>

<?= htmlspecialchars(
    $data_siswa["nama"]
) ?>

-
<?= htmlspecialchars(
    $data_siswa["username"]
) ?>

</option>

<?php endwhile; ?>


</select>

</div>


</div>


<div class="tombol">


<button
    type="submit"
    class="tampilkan"
>
Tampilkan
</button>


<a
    href="hasil-ujian.php"
    class="reset"
>
Reset Filter
</a>


<a
    href="export-hasil.php?ujian_id=<?= $ujian_id ?>&kelas=<?= urlencode($kelas) ?>&siswa_id=<?= $siswa_id ?>"
    class="export"
>
📥 Export Excel
</a>


<button
    type="button"
    class="cetak"
    onclick="window.print()"
>
🖨️ Cetak
</button>


</div>


</form>

</div>


<!-- =========================================================
     REKAP
========================================================= -->


<div class="rekap">


<div class="rekap-box">

<span>
Total Peserta
</span>

<strong>
<?= $total_peserta ?>
</strong>

</div>


<div class="rekap-box">

<span>
Nilai Rata-rata
</span>

<strong>
<?= number_format(
    $rata_rata,
    2
) ?>
</strong>

</div>


<div class="rekap-box">

<span>
Nilai Tertinggi
</span>

<strong>

<?= $nilai_tertinggi !== null
    ? number_format(
        $nilai_tertinggi,
        0
    )
    : "-"
?>

</strong>

</div>


<div class="rekap-box">

<span>
Nilai Terendah
</span>

<strong>

<?= $nilai_terendah !== null
    ? number_format(
        $nilai_terendah,
        0
    )
    : "-"
?>

</strong>

</div>


</div>


<!-- =========================================================
     DATA HASIL
========================================================= -->


<?php if (
    $result->num_rows === 0
): ?>


<div class="kosong">

Belum ada hasil ujian
sesuai filter.

</div>


<?php else: ?>


<form
    method="POST"
    action="reset-hasil-masal.php"
    id="formResetMasal"
>


<div class="area-masal no-print">


<label>

<input
    type="checkbox"
    id="pilihSemua"
    class="checkbox-hasil"
>

<strong>
Pilih Semua Hasil
</strong>

</label>


<button
    type="submit"
    class="btn-reset-masal"
    onclick="
        return konfirmasiResetMasal();
    "
>
🗑️ Reset Hasil Terpilih
</button>


</div>


<div class="table-wrapper">


<table>


<thead>

<tr>

<th>
Pilih
</th>

<th>
No
</th>

<th>
Nama Siswa
</th>

<th>
Username
</th>

<th>
Kelas
</th>

<th>
Ujian
</th>

<th>
Soal
</th>

<th>
Dijawab
</th>

<th>
Benar
</th>

<th>
Salah
</th>

<th>
Nilai
</th>

<th class="aksi no-print">
Aksi
</th>

</tr>

</thead>


<tbody>


<?php

$no = 1;

while (
    $row =
    $result->fetch_assoc()
):

?>


<tr>


<td>

<input
    type="checkbox"
    name="hasil_id[]"
    value="<?= (int) $row["id"] ?>"
    class="checkbox-hasil hasil-pilih"
>

</td>


<td>
<?= $no++ ?>
</td>


<td>

<?= htmlspecialchars(
    $row["nama"]
    ?? "-"
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["username"]
    ?? "-"
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["kelas"]
    ?? "-"
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row["nama_ujian"]
    ?? "-"
) ?>

</td>


<td>
<?= (int)
    $row["jumlah_soal"]
?>
</td>


<td>
<?= (int)
    $row["jumlah_dijawab"]
?>
</td>


<td>
<?= (int)
    $row["jumlah_benar"]
?>
</td>


<td>
<?= (int)
    $row["jumlah_salah"]
?>
</td>


<td class="nilai">

<?= number_format(
    (float)
    $row["nilai"],
    0
) ?>

</td>


<td class="aksi no-print">


<a
    href="detail-hasil.php?id=<?= (int) $row["id"] ?>"
    style="
        display: inline-block;
        padding: 8px 12px;
        background: #198754;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        margin-bottom: 5px;
    "
>
Detail
</a>


<form
    method="POST"
    action="reset-hasil.php"
    style="display: inline;"
    onsubmit="
        return confirm(
            'Yakin ingin mereset ujian siswa ini?\\n\\n' +
            'Jawaban, hasil, dan sesi ujian siswa akan dihapus.\\n' +
            'Siswa dapat mengerjakan ujian kembali dari awal.'
        );
    "
>


<input
    type="hidden"
    name="id"
    value="<?= (int) $row["id"] ?>"
>


<button
    type="submit"
    style="
        display: inline-block;
        padding: 8px 12px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    "
>
Reset
</button>


</form>


</td>


</tr>


<?php endwhile; ?>


</tbody>

</table>

</div>


</form>


<?php endif; ?>



        </div>
    </section>
</main>

<script>
function bukaSidebar() {
    document.getElementById("sidebar").classList.add("open");
    document.getElementById("overlay").classList.add("show");
}

function tutupSidebar() {
    document.getElementById("sidebar").classList.remove("open");
    document.getElementById("overlay").classList.remove("show");
}
</script>
<script>

const pilihSemua =
    document.getElementById(
        "pilihSemua"
    );


if (pilihSemua) {

    pilihSemua.addEventListener(
        "change",
        function () {

            const checkbox =
                document.querySelectorAll(
                    ".hasil-pilih"
                );


            checkbox.forEach(
                function (item) {

                    item.checked =
                        pilihSemua.checked;

                }
            );

        }
    );

}


function konfirmasiResetMasal() {

    const terpilih =
        document.querySelectorAll(
            ".hasil-pilih:checked"
        );


    if (
        terpilih.length === 0
    ) {

        alert(
            "Silakan pilih hasil ujian yang ingin direset terlebih dahulu."
        );

        return false;

    }


    return confirm(
        "Yakin ingin mereset " +
        terpilih.length +
        " hasil ujian?\n\n" +
        "Jawaban, hasil, dan sesi ujian siswa " +
        "yang dipilih akan dihapus.\n\n" +
        "Siswa dapat mengerjakan ujian kembali."
    );

}

</script>
</body>
</html>

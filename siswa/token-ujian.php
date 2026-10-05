<?php

date_default_timezone_set("Asia/Jakarta");

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["siswa_id"])) {
    header("Location: login.php");
    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];
$kelas = (string) ($_SESSION["kelas"] ?? "");

$ujian_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : (isset($_POST["ujian_id"]) ? (int) $_POST["ujian_id"] : 0);

if ($ujian_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nama_ujian,
        u.kelas,
        u.durasi,
        u.token,
        u.tanggal_mulai,
        u.tanggal_selesai,
        u.status,
        m.nama AS nama_mapel
    FROM ujian u
    JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.id = ?
      AND u.status = 'aktif'
    LIMIT 1
");

$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    die("Ujian tidak tersedia.");
}

/*
|--------------------------------------------------------------------------
| PASTIKAN UJIAN SESUAI KELAS SISWA
|--------------------------------------------------------------------------
*/

if ((string) $ujian["kelas"] !== $kelas) {
    die("Anda tidak memiliki akses ke ujian ini.");
}

/*
|--------------------------------------------------------------------------
| CEK SESI YANG SUDAH ADA
|--------------------------------------------------------------------------
|
| Jika siswa sudah mempunyai sesi mengerjakan, token tidak diminta lagi.
| Ini memungkinkan siswa melanjutkan ujian setelah refresh/menutup browser.
|
*/

$stmt = $conn->prepare("
    SELECT id, status, batas_waktu
    FROM sesi_ujian
    WHERE siswa_id = ?
      AND ujian_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $siswa_id, $ujian_id);
$stmt->execute();

$sesi = $stmt->get_result()->fetch_assoc();

if ($sesi) {

    if ($sesi["status"] === "mengerjakan") {

        if (
            !empty($sesi["batas_waktu"]) &&
            strtotime($sesi["batas_waktu"]) <= time()
        ) {
            header("Location: auto-kirim.php");
            exit;
        }

        header("Location: ujian.php?id=" . $ujian_id);
        exit;
    }

    die("Ujian sudah selesai dan tidak dapat dikerjakan kembali.");
}

/*
|--------------------------------------------------------------------------
| PROSES TOKEN
|--------------------------------------------------------------------------
*/

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token_input = trim((string) ($_POST["token"] ?? ""));

    if ($token_input === "") {

        $error = "Token ujian wajib diisi.";

    } elseif (!hash_equals((string) $ujian["token"], $token_input)) {

        $error = "Token ujian salah. Silakan periksa kembali token yang diberikan guru.";

    } else {

        /*
        | Token benar.
        |
        | Otorisasi disimpan di session dan hanya digunakan oleh ujian.php
        | untuk membuat sesi ujian baru. Token tidak dimasukkan ke URL.
        */
        if (!isset($_SESSION["ujian_token_verified"])) {
            $_SESSION["ujian_token_verified"] = [];
        }

        $_SESSION["ujian_token_verified"][$ujian_id] = [
            "verified_at" => time(),
            "siswa_id" => $siswa_id
        ];

        header("Location: ujian.php?id=" . $ujian_id);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Token Ujian - PIRI CBT</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5f9;
}

.container {
    max-width: 500px;
    margin: 60px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 28px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
    margin-bottom: 8px;
}

.info {
    color: #555;
    line-height: 1.7;
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 16px;
    text-transform: uppercase;
}

button {
    width: 100%;
    padding: 13px;
    margin-top: 15px;
    border: 0;
    border-radius: 8px;
    background: #198754;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.back {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #555;
    text-decoration: none;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    line-height: 1.5;
}

.warning {
    margin-top: 18px;
    font-size: 13px;
    color: #666;
    line-height: 1.5;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>🔐 Token Ujian</h1>

<div class="info">

<strong><?= htmlspecialchars($ujian["nama_ujian"]) ?></strong>

<br>

Mapel:
<?= htmlspecialchars($ujian["nama_mapel"]) ?>

<br>

Kelas:
<?= htmlspecialchars($ujian["kelas"]) ?>

<br>

Durasi:
<?= htmlspecialchars($ujian["durasi"]) ?> menit

</div>

<?php if ($error !== ""): ?>

    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

<form method="post" autocomplete="off">

    <input
        type="hidden"
        name="ujian_id"
        value="<?= (int) $ujian_id ?>"
    >

    <label for="token">
        Masukkan Token Ujian
    </label>

    <input
        type="text"
        id="token"
        name="token"
        maxlength="100"
        autocomplete="off"
        autocapitalize="characters"
        spellcheck="false"
        required
        autofocus
    >

    <button type="submit">
        MULAI UJIAN
    </button>

</form>

<a class="back" href="dashboard.php">
    ← Kembali ke Dashboard
</a>

<div class="warning">
    Token digunakan hanya untuk memulai ujian.
    Jika ujian sudah dimulai, Anda tidak perlu memasukkan token lagi
    ketika melanjutkan sesi yang sama.
</div>

</div>

</div>

</body>

</html>

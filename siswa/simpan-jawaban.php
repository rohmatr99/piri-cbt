<?php

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function balas($status, $pesan, $kode = null, $tambahan = [])
{
    $out = [
        "status" => $status,
        "pesan"  => $pesan
    ];

    if ($kode !== null) {
        $out["kode_error"] = $kode;
    }

    if (!empty($tambahan)) {
        $out = array_merge($out, $tambahan);
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. METHOD
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    balas(false, "Metode tidak diizinkan.", "A01");
}

/*
|--------------------------------------------------------------------------
| 2. LOGIN SISWA
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["siswa_id"])) {
    balas(false, "Sesi login siswa tidak ditemukan.", "A02");
}

$siswa_id = (int) $_SESSION["siswa_id"];

/*
|--------------------------------------------------------------------------
| 3. CEK KONEKSI DATABASE
|--------------------------------------------------------------------------
*/
if (!isset($conn) || !($conn instanceof mysqli)) {
    balas(false, "Koneksi database tidak tersedia.", "A03");
}

if ($conn->connect_errno) {
    balas(
        false,
        "Koneksi database bermasalah.",
        "A03",
        ["detail" => $conn->connect_error]
    );
}

/*
|--------------------------------------------------------------------------
| 4. BACA JSON
|--------------------------------------------------------------------------
*/
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    balas(false, "Data JSON tidak valid.", "A04");
}

$sesi_id = isset($data["sesi_id"]) ? (int) $data["sesi_id"] : 0;
$soal_id = isset($data["soal_id"]) ? (int) $data["soal_id"] : 0;
$opsi_id = isset($data["opsi_id"]) ? (int) $data["opsi_id"] : 0;

if ($sesi_id <= 0 || $soal_id <= 0 || $opsi_id <= 0) {
    balas(
        false,
        "ID jawaban tidak valid.",
        "A05",
        [
            "sesi_id" => $sesi_id,
            "soal_id" => $soal_id,
            "opsi_id" => $opsi_id
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 5. AMBIL SESI TANPA LANGSUNG MEMBATASI STATUS
|    Tujuannya agar kita tahu alasan sebenarnya jika gagal.
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        id,
        siswa_id,
        ujian_id,
        status,
        terkunci_pengawas,
        batas_waktu
    FROM sesi_ujian
    WHERE id = ?
      AND siswa_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    balas(
        false,
        "Gagal menyiapkan pemeriksaan sesi.",
        "A06",
        ["detail" => $conn->error]
    );
}

$stmt->bind_param("ii", $sesi_id, $siswa_id);

if (!$stmt->execute()) {
    balas(
        false,
        "Gagal memeriksa sesi ujian.",
        "A07",
        ["detail" => $stmt->error]
    );
}

$sesi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sesi) {
    balas(
        false,
        "Sesi ujian tidak ditemukan atau bukan milik siswa ini.",
        "A08",
        [
            "sesi_id" => $sesi_id,
            "siswa_id" => $siswa_id
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 6. STATUS SESI
|--------------------------------------------------------------------------
*/
if ($sesi["status"] !== "mengerjakan") {
    balas(
        false,
        "Jawaban tidak dapat disimpan karena status sesi adalah: " . $sesi["status"],
        "A09",
        [
            "sesi_status" => $sesi["status"],
            "sesi_id" => $sesi_id
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 7. KUNCI PENGAWAS
|--------------------------------------------------------------------------
*/
if ((int) $sesi["terkunci_pengawas"] === 1) {
    balas(
        false,
        "Jawaban belum dapat disimpan karena ujian sedang dikunci pengawas.",
        "A10",
        [
            "terkunci_pengawas" => 1
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 8. DEADLINE
|--------------------------------------------------------------------------
*/
if (empty($sesi["batas_waktu"]) || strtotime($sesi["batas_waktu"]) === false) {
    balas(false, "Batas waktu sesi tidak valid.", "A11");
}

if (strtotime($sesi["batas_waktu"]) <= time()) {
    balas(
        false,
        "Waktu ujian telah habis.",
        "A12",
        [
            "batas_waktu" => $sesi["batas_waktu"]
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 9. CEK SOAL
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id
    FROM soal
    WHERE id = ?
      AND ujian_id = ?
    LIMIT 1
");

if (!$stmt) {
    balas(
        false,
        "Gagal menyiapkan pemeriksaan soal.",
        "A13",
        ["detail" => $conn->error]
    );
}

$stmt->bind_param("ii", $soal_id, $sesi["ujian_id"]);

if (!$stmt->execute()) {
    balas(
        false,
        "Gagal memeriksa soal.",
        "A14",
        ["detail" => $stmt->error]
    );
}

$cekSoal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cekSoal) {
    balas(
        false,
        "Soal tidak valid atau bukan bagian dari ujian ini.",
        "A15",
        [
            "soal_id" => $soal_id,
            "ujian_id" => (int) $sesi["ujian_id"]
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 10. CEK OPSI
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id
    FROM opsi_soal
    WHERE id = ?
      AND soal_id = ?
    LIMIT 1
");

if (!$stmt) {
    balas(
        false,
        "Gagal menyiapkan pemeriksaan pilihan jawaban.",
        "A16",
        ["detail" => $conn->error]
    );
}

$stmt->bind_param("ii", $opsi_id, $soal_id);

if (!$stmt->execute()) {
    balas(
        false,
        "Gagal memeriksa pilihan jawaban.",
        "A17",
        ["detail" => $stmt->error]
    );
}

$cekOpsi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cekOpsi) {
    balas(
        false,
        "Pilihan jawaban tidak valid atau bukan milik soal tersebut.",
        "A18",
        [
            "soal_id" => $soal_id,
            "opsi_id" => $opsi_id
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 11. SIMPAN / UPDATE JAWABAN
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    INSERT INTO jawaban
    (
        sesi_id,
        soal_id,
        opsi_id
    )
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        opsi_id = VALUES(opsi_id)
");

if (!$stmt) {
    balas(
        false,
        "Gagal menyiapkan penyimpanan jawaban.",
        "A19",
        ["detail" => $conn->error]
    );
}

$stmt->bind_param(
    "iii",
    $sesi_id,
    $soal_id,
    $opsi_id
);

if (!$stmt->execute()) {
    balas(
        false,
        "Database menolak penyimpanan jawaban.",
        "A20",
        [
            "detail" => $stmt->error
        ]
    );
}

/*
|--------------------------------------------------------------------------
| 12. VERIFIKASI ULANG
|--------------------------------------------------------------------------
*/
$stmt->close();

$stmt = $conn->prepare("
    SELECT id, opsi_id
    FROM jawaban
    WHERE sesi_id = ?
      AND soal_id = ?
    LIMIT 1
");

if (!$stmt) {
    balas(
        false,
        "Jawaban diduga tersimpan, tetapi verifikasi gagal.",
        "A21",
        ["detail" => $conn->error]
    );
}

$stmt->bind_param("ii", $sesi_id, $soal_id);
$stmt->execute();

$hasil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hasil) {
    balas(
        false,
        "Server tidak menemukan jawaban setelah proses simpan.",
        "A22"
    );
}

if ((int) $hasil["opsi_id"] !== $opsi_id) {
    balas(
        false,
        "Jawaban tersimpan tetapi pilihan tidak sesuai.",
        "A23",
        [
            "tersimpan_opsi_id" => (int) $hasil["opsi_id"],
            "diminta_opsi_id"   => $opsi_id
        ]
    );
}

balas(
    true,
    "Jawaban tersimpan.",
    null,
    [
        "sesi_id" => $sesi_id,
        "soal_id" => $soal_id,
        "opsi_id" => $opsi_id
    ]
);
?>

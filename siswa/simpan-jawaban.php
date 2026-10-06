<?php

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => false,
        "pesan" => "Metode tidak diizinkan."
    ]);
    exit;
}

if (!isset($_SESSION["siswa_id"])) {
    echo json_encode([
        "status" => false,
        "pesan" => "Sesi login tidak ditemukan."
    ]);
    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];

/*
|--------------------------------------------------------------------------
| Ambil data dari JavaScript
|--------------------------------------------------------------------------
*/

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "status" => false,
        "pesan" => "Data tidak valid."
    ]);
    exit;
}

$sesi_id = isset($data["sesi_id"])
    ? (int) $data["sesi_id"]
    : 0;

$soal_id = isset($data["soal_id"])
    ? (int) $data["soal_id"]
    : 0;

$opsi_id = isset($data["opsi_id"])
    ? (int) $data["opsi_id"]
    : 0;

if ($sesi_id <= 0 || $soal_id <= 0 || $opsi_id <= 0) {
    echo json_encode([
        "status" => false,
        "pesan" => "Data jawaban tidak lengkap."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Pastikan sesi milik siswa
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM sesi_ujian
    WHERE id = ?
      AND siswa_id = ?
      AND status = 'mengerjakan'
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "pesan" => "Sistem gagal memeriksa sesi."
    ]);
    exit;
}

$stmt->bind_param(
    "ii",
    $sesi_id,
    $siswa_id
);

$stmt->execute();

$sesi = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$sesi) {
    echo json_encode([
        "status" => false,
        "pesan" => "Sesi ujian tidak valid."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| LEVEL 15 — Pastikan sesi belum dikunci pengawas
|--------------------------------------------------------------------------
*/

if ((int) ($sesi["terkunci_pengawas"] ?? 0) === 1) {
    echo json_encode([
        "status" => false,
        "terkunci_pengawas" => true,
        "pesan" => "Ujian sedang dikunci oleh pengawas. Masukkan Kode Pengawas terlebih dahulu."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Periksa waktu ujian
|--------------------------------------------------------------------------
*/

if (strtotime($sesi["batas_waktu"]) <= time()) {
    echo json_encode([
        "status" => false,
        "pesan" => "Waktu ujian telah habis."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Pastikan soal berada dalam ujian
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
    echo json_encode([
        "status" => false,
        "pesan" => "Sistem gagal memeriksa soal."
    ]);
    exit;
}

$stmt->bind_param(
    "ii",
    $soal_id,
    $sesi["ujian_id"]
);

$stmt->execute();

$cekSoal = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$cekSoal) {
    echo json_encode([
        "status" => false,
        "pesan" => "Soal tidak valid."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Pastikan opsi memang milik soal
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
    echo json_encode([
        "status" => false,
        "pesan" => "Sistem gagal memeriksa pilihan jawaban."
    ]);
    exit;
}

$stmt->bind_param(
    "ii",
    $opsi_id,
    $soal_id
);

$stmt->execute();

$cekOpsi = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$cekOpsi) {
    echo json_encode([
        "status" => false,
        "pesan" => "Pilihan jawaban tidak valid."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Simpan / update jawaban
|
| MEKANISME INI DIPERTAHANKAN SAMA DENGAN FILE LAMA
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
    echo json_encode([
        "status" => false,
        "pesan" => "Sistem gagal menyiapkan penyimpanan jawaban."
    ]);
    exit;
}

$stmt->bind_param(
    "iii",
    $sesi_id,
    $soal_id,
    $opsi_id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => true,
        "pesan" => "Jawaban tersimpan."
    ]);

} else {

    echo json_encode([
        "status" => false,
        "pesan" => "Jawaban gagal disimpan."
    ]);
}

$stmt->close();

?>

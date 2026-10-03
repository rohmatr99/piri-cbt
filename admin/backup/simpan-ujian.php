<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


/* =========================
   AMBIL DATA FORM
========================= */

$nama_ujian = trim($_POST["nama_ujian"] ?? "");
$mapel_id = (int)($_POST["mapel_id"] ?? 0);
$kelas = trim($_POST["kelas"] ?? "");
$durasi = (int)($_POST["durasi"] ?? 0);
$minimal_menit = (int)($_POST["minimal_menit"] ?? 0);
$token = trim($_POST["token"] ?? "");
$tanggal_mulai = $_POST["tanggal_mulai"] ?? "";
$tanggal_selesai = $_POST["tanggal_selesai"] ?? "";
$status = trim($_POST["status"] ?? "");


/* =========================
   VALIDASI
========================= */

if (
    $nama_ujian === "" ||
    $mapel_id <= 0 ||
    $kelas === "" ||
    $durasi <= 0 ||
    $minimal_menit < 0 ||
    $minimal_menit > $durasi ||
    $token === "" ||
    $tanggal_mulai === "" ||
    $tanggal_selesai === "" ||
    $status === ""
) {
    die("Data ujian belum lengkap atau minimal waktu tidak valid.");
}


/* =========================
   CEK MATA PELAJARAN
========================= */

$stmt = $conn->prepare("
    SELECT id
    FROM mata_pelajaran
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $mapel_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Mata pelajaran tidak ditemukan.");
}


/* =========================
   CEK TOKEN
========================= */

$stmt = $conn->prepare("
    SELECT id
    FROM ujian
    WHERE token = ?
    LIMIT 1
");

$stmt->bind_param("s", $token);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("
        Token ujian sudah digunakan.
        <br><br>
        Silakan gunakan token lain.
        <br><br>
        <a href='tambah-ujian.php'>
            ← Kembali
        </a>
    ");
}


/* =========================
   SIMPAN UJIAN
========================= */

$stmt = $conn->prepare("
    INSERT INTO ujian
    (
        nama_ujian,
        mapel_id,
        kelas,
        durasi,
        minimal_menit,
        token,
        tanggal_mulai,
        tanggal_selesai,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sisiissss",
    $nama_ujian,
    $mapel_id,
    $kelas,
    $durasi,
    $minimal_menit,
    $token,
    $tanggal_mulai,
    $tanggal_selesai,
    $status
);


/* =========================
   EKSEKUSI
========================= */

if (!$stmt->execute()) {

    die(
        "Gagal menyimpan ujian: " .
        htmlspecialchars($stmt->error)
    );
}


/* =========================
   BERHASIL
========================= */

header("Location: ujian.php");
exit;
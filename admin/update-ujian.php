<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION["admin_id"] ?? 0);
$admin_role = $_SESSION["admin_role"] ?? "admin";

/* Ambil data dari form */
$id = (int)($_POST["id"] ?? 0);
$nama_ujian = trim($_POST["nama_ujian"] ?? "");
$mapel_id = (int)($_POST["mapel_id"] ?? 0);
$kelas = trim($_POST["kelas"] ?? "");
$durasi = (int)($_POST["durasi"] ?? 0);
$minimal_menit = (int)($_POST["minimal_menit"] ?? 0);
$maks_pelanggaran = (int)($_POST["maks_pelanggaran"] ?? 0);
$token = trim($_POST["token"] ?? "");
$tanggal_mulai = $_POST["tanggal_mulai"] ?? "";
$tanggal_selesai = $_POST["tanggal_selesai"] ?? "";
$status = $_POST["status"] ?? "";

/* Validasi dasar */
if (
    $id <= 0 ||
    $nama_ujian === "" ||
    $mapel_id <= 0 ||
    $kelas === "" ||
    $durasi <= 0 ||
    $minimal_menit < 0 ||
    $minimal_menit > $durasi ||
    $maks_pelanggaran < 0 ||
    $token === "" ||
    $tanggal_mulai === "" ||
    $tanggal_selesai === "" ||
    $status === ""
) {
    die("Data ujian belum lengkap atau minimal waktu tidak valid.");
}

/* Validasi status */
if (!in_array($status, ["aktif", "nonaktif"], true)) {
    die("Status ujian tidak valid.");
}

/* Pastikan ujian memang ada dan boleh diakses admin */
if ($admin_role === "superadmin") {
    $stmt = $conn->prepare("
        SELECT id, mapel_id
        FROM ujian
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("
        SELECT u.id, u.mapel_id
        FROM ujian u
        INNER JOIN admin_mapel am
            ON am.mapel_id = u.mapel_id
           AND am.admin_id = ?
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admin_id, $id);
}

$stmt->bind_param("i", $id);
$stmt->execute();

$cekUjian = $stmt->get_result()->fetch_assoc();

if (!$cekUjian) {
    die("Ujian tidak ditemukan.");
}

/* Pastikan mata pelajaran ada */
$stmt = $conn->prepare("
    SELECT id
    FROM mata_pelajaran
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $mapel_id);
$stmt->execute();

$cekMapel = $stmt->get_result()->fetch_assoc();

if (!$cekMapel) {
    die("Mata pelajaran tidak ditemukan.");
}

/* Admin hanya boleh memakai mapel yang ditugaskan */
if ($admin_role !== "superadmin") {
    $stmt = $conn->prepare("
        SELECT id
        FROM admin_mapel
        WHERE admin_id = ? AND mapel_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admin_id, $mapel_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        die("Anda tidak memiliki akses ke mata pelajaran tersebut.");
    }
}

/* Cek token tidak boleh dipakai ujian lain */
$stmt = $conn->prepare("
    SELECT id
    FROM ujian
    WHERE token = ?
      AND id <> ?
    LIMIT 1
");

$stmt->bind_param("si", $token, $id);
$stmt->execute();

$cekToken = $stmt->get_result()->fetch_assoc();

if ($cekToken) {
    die("Token sudah digunakan oleh ujian lain.");
}

/* Validasi tanggal */
$mulai = strtotime($tanggal_mulai);
$selesai = strtotime($tanggal_selesai);

if ($mulai === false || $selesai === false) {
    die("Format tanggal tidak valid.");
}

if ($selesai <= $mulai) {
    die("Tanggal selesai harus lebih besar dari tanggal mulai.");
}

/* Update data ujian */
$stmt = $conn->prepare("
    UPDATE ujian
    SET
        nama_ujian = ?,
        mapel_id = ?,
        kelas = ?,
        durasi = ?,
        minimal_menit = ?,
        maks_pelanggaran = ?,
        token = ?,
        tanggal_mulai = ?,
        tanggal_selesai = ?,
        status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "sisiiissssi",
    $nama_ujian,
    $mapel_id,
    $kelas,
    $durasi,
    $minimal_menit,
    $maks_pelanggaran,
    $token,
    $tanggal_mulai,
    $tanggal_selesai,
    $status,
    $id
);

if (!$stmt->execute()) {
    die("Gagal memperbarui ujian: " . $stmt->error);
}

/* Kembali ke daftar ujian */
header("Location: ujian.php");
exit;
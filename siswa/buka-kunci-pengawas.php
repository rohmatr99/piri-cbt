<?php

date_default_timezone_set("Asia/Jakarta");
session_start();
require_once "../config/database.php";
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["siswa_id"])) {
    echo json_encode(["status" => false, "pesan" => "Sesi login tidak ditemukan."]);
    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];
$sesi_id = isset($_POST["sesi_id"]) ? (int) $_POST["sesi_id"] : 0;
$ujian_id = isset($_POST["ujian_id"]) ? (int) $_POST["ujian_id"] : 0;
$kode = trim((string)($_POST["kode"] ?? ""));

if ($sesi_id <= 0 || $ujian_id <= 0 || !preg_match('/^\d{6}$/', $kode)) {
    echo json_encode(["status" => false, "pesan" => "Kode pengawas harus terdiri dari 6 digit."]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id, status, batas_waktu, terkunci_pengawas FROM sesi_ujian WHERE id = ? AND siswa_id = ? AND ujian_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param("iii", $sesi_id, $siswa_id, $ujian_id);
    $stmt->execute();
    $sesi = $stmt->get_result()->fetch_assoc();

    if (!$sesi) throw new Exception("Sesi ujian tidak valid.");
    if ($sesi["status"] !== "mengerjakan") throw new Exception("Ujian sudah selesai.");
    if (strtotime($sesi["batas_waktu"]) <= time()) throw new Exception("Waktu ujian telah habis.");
    if ((int)$sesi["terkunci_pengawas"] !== 1) throw new Exception("Sesi tidak sedang terkunci.");

    $stmt = $conn->prepare("SELECT id FROM kode_pengawas_ujian WHERE sesi_id = ? AND kode = ? AND status = 'aktif' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param("is", $sesi_id, $kode);
    $stmt->execute();
    $kode_row = $stmt->get_result()->fetch_assoc();

    if (!$kode_row) throw new Exception("Kode pengawas salah atau sudah tidak berlaku.");

    $kode_id = (int)$kode_row["id"];

    $stmt = $conn->prepare("UPDATE kode_pengawas_ujian SET status = 'digunakan', digunakan = NOW() WHERE id = ?");
    $stmt->bind_param("i", $kode_id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE sesi_ujian SET terkunci_pengawas = 0 WHERE id = ?");
    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "status" => true,
        "terkunci_pengawas" => false,
        "pesan" => "Kode pengawas benar."
    ]);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(["status" => false, "pesan" => $e->getMessage()]);
    exit;
}

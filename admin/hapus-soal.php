<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$ujian_id = isset($_GET["ujian_id"]) ? (int)$_GET["ujian_id"] : 0;

if ($id <= 0 || $ujian_id <= 0) {
    die("Data tidak valid.");
}

/* Pastikan soal milik ujian */
$stmt = $conn->prepare("
    SELECT id
    FROM soal
    WHERE id = ? AND ujian_id = ?
");

$stmt->bind_param("ii", $id, $ujian_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    die("Soal tidak ditemukan.");
}

$conn->begin_transaction();

try {

    /* Hapus pilihan jawaban terlebih dahulu */
    $stmt = $conn->prepare("
        DELETE FROM opsi_soal
        WHERE soal_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    /* Hapus soal */
    $stmt = $conn->prepare("
        DELETE FROM soal
        WHERE id = ? AND ujian_id = ?
    ");

    $stmt->bind_param("ii", $id, $ujian_id);
    $stmt->execute();

    $conn->commit();

    header("Location: soal.php?ujian_id=" . $ujian_id);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    die("Gagal menghapus soal: " . $e->getMessage());
}
?>
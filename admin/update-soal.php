<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
$ujian_id = isset($_POST["ujian_id"]) ? (int)$_POST["ujian_id"] : 0;
$nomor = isset($_POST["nomor"]) ? (int)$_POST["nomor"] : 0;
$pertanyaan = trim($_POST["pertanyaan"] ?? "");
$bobot = isset($_POST["bobot"]) ? (int)$_POST["bobot"] : 0;

$kunci = $_POST["kunci"] ?? "";

$opsi_data = [
    "A" => trim($_POST["opsi_A"] ?? ""),
    "B" => trim($_POST["opsi_B"] ?? ""),
    "C" => trim($_POST["opsi_C"] ?? ""),
    "D" => trim($_POST["opsi_D"] ?? "")
];

/* Validasi */
if (
    $id <= 0 ||
    $ujian_id <= 0 ||
    $nomor <= 0 ||
    $pertanyaan === "" ||
    $bobot <= 0 ||
    !in_array($kunci, ["A", "B", "C", "D"])
) {
    die("Data soal tidak lengkap.");
}

foreach ($opsi_data as $kode => $teks) {
    if ($teks === "") {
        die("Pilihan jawaban $kode belum diisi.");
    }
}

/* Cek nomor soal tidak bentrok */
$stmt = $conn->prepare("
    SELECT id
    FROM soal
    WHERE ujian_id = ?
    AND nomor = ?
    AND id <> ?
");

$stmt->bind_param("iii", $ujian_id, $nomor, $id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    die("Nomor soal tersebut sudah digunakan.");
}

/* Pastikan soal memang milik ujian */
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

/* Mulai transaksi */
$conn->begin_transaction();

try {

    /* Update soal */
    $stmt = $conn->prepare("
        UPDATE soal
        SET nomor = ?, pertanyaan = ?, bobot = ?
        WHERE id = ? AND ujian_id = ?
    ");

    $stmt->bind_param(
        "isiii",
        $nomor,
        $pertanyaan,
        $bobot,
        $id,
        $ujian_id
    );

    $stmt->execute();

    /* Update pilihan A-D */
    foreach ($opsi_data as $kode => $teks) {

        $benar = ($kode === $kunci) ? 1 : 0;

        $stmt = $conn->prepare("
            UPDATE opsi_soal
            SET teks = ?, benar = ?
            WHERE soal_id = ? AND kode = ?
        ");

        $stmt->bind_param(
            "siis",
            $teks,
            $benar,
            $id,
            $kode
        );

        $stmt->execute();
    }

    $conn->commit();

    header("Location: soal.php?ujian_id=" . $ujian_id);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    die("Gagal menyimpan perubahan: " . $e->getMessage());
}
?>
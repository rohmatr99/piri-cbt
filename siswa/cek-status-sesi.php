<?php

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


/*
|----------------------------------------------------------------------
| CEK LOGIN SISWA
|----------------------------------------------------------------------
*/

if (!isset($_SESSION["siswa_id"])) {

    http_response_code(401);

    echo json_encode([
        "status" => false,
        "pesan" => "Sesi login tidak ditemukan."
    ]);

    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];


/*
|----------------------------------------------------------------------
| HANYA TERIMA GET
|----------------------------------------------------------------------
|
| ujian.php saat ini memanggil endpoint ini menggunakan GET.
| Jangan mengubah menjadi POST agar client yang sudah berjalan
| tetap kompatibel.
|
*/

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    header("Allow: GET");

    echo json_encode([
        "status" => false,
        "pesan" => "Metode tidak diizinkan."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| AMBIL PARAMETER
|----------------------------------------------------------------------
*/

$sesi_id = isset($_GET["sesi_id"])
    ? (int) $_GET["sesi_id"]
    : 0;

$ujian_id = isset($_GET["ujian_id"])
    ? (int) $_GET["ujian_id"]
    : 0;


if ($sesi_id <= 0 || $ujian_id <= 0) {

    http_response_code(400);

    echo json_encode([
        "status" => false,
        "pesan" => "Parameter sesi tidak lengkap."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| AMBIL STATUS SESI MILIK SISWA
|----------------------------------------------------------------------
|
| Identitas siswa TIDAK dipercaya dari parameter browser.
| Siswa diambil dari PHP session.
|
| Endpoint hanya dapat membaca sesi yang:
| - sesuai sesi_id
| - milik siswa yang sedang login
| - sesuai ujian_id
|
*/

$stmt = $conn->prepare("
    SELECT
        su.id,
        su.status,
        su.terkunci_pengawas,
        su.batas_waktu,
        (
            SELECT COUNT(*)
            FROM pelanggaran_ujian pu
            WHERE pu.sesi_id = su.id
        ) AS total_pelanggaran,
        su.selesai,
        (
            SELECT hu.id
            FROM hasil_ujian hu
            WHERE hu.sesi_id = su.id
            ORDER BY hu.id DESC
            LIMIT 1
        ) AS hasil_id
    FROM sesi_ujian su
    WHERE su.id = ?
      AND su.siswa_id = ?
      AND su.ujian_id = ?
    LIMIT 1
");

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "pesan" => "Gagal menyiapkan pemeriksaan sesi."
    ]);

    exit;
}

$stmt->bind_param(
    "iii",
    $sesi_id,
    $siswa_id,
    $ujian_id
);

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "pesan" => "Gagal memeriksa status sesi."
    ]);

    exit;
}

$sesi = $stmt
    ->get_result()
    ->fetch_assoc();


/*
|----------------------------------------------------------------------
| SESI TIDAK DITEMUKAN / BUKAN MILIK SISWA
|----------------------------------------------------------------------
*/

if (!$sesi) {

    http_response_code(403);

    echo json_encode([
        "status" => false,
        "pesan" => "Sesi ujian tidak ditemukan."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| JIKA STATUS MASIH MENGERJAKAN TETAPI WAKTU HABIS
|----------------------------------------------------------------------
|
| Jangan membuat hasil di endpoint ini.
| Alur hasil tetap menggunakan auto-kirim.php seperti sebelumnya.
|
*/

if (
    $sesi["status"] === "mengerjakan" &&
    !empty($sesi["batas_waktu"]) &&
    strtotime($sesi["batas_waktu"]) <= time()
) {

    echo json_encode([
        "status" => true,
        "sesi_status" => "mengerjakan",
        "waktu_habis" => true,
        "hasil_id" => 0,
        "total_pelanggaran" => (int) ($sesi["total_pelanggaran"] ?? 0),
        "batas_waktu" => $sesi["batas_waktu"],
        "pesan" => "Waktu ujian telah habis."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| RESPONSE STATUS SESI
|----------------------------------------------------------------------
*/

echo json_encode([
    "status" => true,
    "sesi_status" => $sesi["status"],
    "terkunci_pengawas" => !empty($sesi["terkunci_pengawas"]),
    "waktu_habis" => false,
    "hasil_id" => (int) ($sesi["hasil_id"] ?? 0),
    "total_pelanggaran" => (int) ($sesi["total_pelanggaran"] ?? 0),
    "selesai" => $sesi["selesai"],
    "pesan" => "Status sesi berhasil diperiksa."
]);

exit;

?>

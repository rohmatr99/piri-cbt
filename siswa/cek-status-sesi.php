<?php

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");


/*
|--------------------------------------------------------------------------
| CEK LOGIN SISWA
|--------------------------------------------------------------------------
*/

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
| AMBIL PARAMETER
|--------------------------------------------------------------------------
*/

$sesi_id =
    isset($_GET["sesi_id"])
        ? (int) $_GET["sesi_id"]
        : 0;

$ujian_id =
    isset($_GET["ujian_id"])
        ? (int) $_GET["ujian_id"]
        : 0;


if (
    $sesi_id <= 0 ||
    $ujian_id <= 0
) {

    echo json_encode([
        "status" => false,
        "pesan" => "Parameter sesi tidak lengkap."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL STATUS SESI MILIK SISWA
|--------------------------------------------------------------------------
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

$stmt->bind_param(
    "iii",
    $sesi_id,
    $siswa_id,
    $ujian_id
);

$stmt->execute();

$sesi =
    $stmt
        ->get_result()
        ->fetch_assoc();


if (!$sesi) {

    echo json_encode([
        "status" => false,
        "pesan" => "Sesi ujian tidak ditemukan."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| JIKA STATUS MASIH MENGERJAKAN TETAPI WAKTU HABIS
|--------------------------------------------------------------------------
|
| Pengaman tambahan agar status server konsisten.
| Proses hasil lengkap tetap ditangani oleh auto-kirim.php ketika
| halaman ujian diarahkan ke sana.
|--------------------------------------------------------------------------
*/

if (
    $sesi["status"] === "mengerjakan" &&
    !empty($sesi["batas_waktu"]) &&
    strtotime($sesi["batas_waktu"]) <= time()
) {

    /*
    | Jangan membuat hasil di sini.
    | Arahkan client ke auto-kirim.php agar alur waktu habis
    | tetap menggunakan mekanisme yang sudah ada.
    */

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
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
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

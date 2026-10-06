<?php

date_default_timezone_set("Asia/Jakarta");

session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


/*
|----------------------------------------------------------------------
| CEK LOGIN
|----------------------------------------------------------------------
*/

if (!isset($_SESSION["siswa_id"])) {

    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "message" => "Sesi login tidak valid."
    ]);

    exit;
}

$siswa_id = (int) $_SESSION["siswa_id"];


/*
|----------------------------------------------------------------------
| HANYA TERIMA POST
|----------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    header("Allow: POST");

    echo json_encode([
        "status" => "error",
        "message" => "Metode tidak diizinkan."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| AMBIL PARAMETER
|----------------------------------------------------------------------
|
| ujian.php mengirim heartbeat sebagai
| application/x-www-form-urlencoded.
|
*/

$sesi_id = isset($_POST["sesi_id"])
    ? (int) $_POST["sesi_id"]
    : 0;

$ujian_id = isset($_POST["ujian_id"])
    ? (int) $_POST["ujian_id"]
    : 0;


if ($sesi_id <= 0 || $ujian_id <= 0) {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "message" => "Parameter sesi tidak lengkap."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| UPDATE HEARTBEAT
|----------------------------------------------------------------------
|
| Identitas siswa TIDAK diambil dari browser.
| Siswa diambil dari PHP session.
|
| Heartbeat hanya boleh memperbarui sesi:
| - milik siswa yang sedang login
| - ujian sesuai
| - status masih mengerjakan
| - belum melewati batas waktu
|
| Heartbeat TIDAK memperpanjang batas_waktu.
|
*/

$stmt = $conn->prepare("
    UPDATE sesi_ujian
    SET terakhir_aktif = NOW()
    WHERE id = ?
      AND siswa_id = ?
      AND ujian_id = ?
      AND status = 'mengerjakan'
      AND batas_waktu > NOW()
");

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Gagal menyiapkan heartbeat."
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
        "status" => "error",
        "message" => "Gagal memperbarui heartbeat."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| HEARTBEAT BERHASIL
|----------------------------------------------------------------------
*/

if ($stmt->affected_rows > 0) {

    echo json_encode([
        "status" => "ok",
        "message" => "Heartbeat diterima.",
        "sesi_id" => $sesi_id,
        "ujian_id" => $ujian_id,
        "server_time" => date("Y-m-d H:i:s")
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| UPDATE TIDAK TERJADI
|----------------------------------------------------------------------
|
| Periksa penyebab tanpa mengubah data.
|
*/

$stmt_check = $conn->prepare("
    SELECT
        id,
        siswa_id,
        ujian_id,
        batas_waktu,
        status
    FROM sesi_ujian
    WHERE id = ?
    LIMIT 1
");

if (!$stmt_check) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Gagal memeriksa sesi."
    ]);

    exit;
}

$stmt_check->bind_param(
    "i",
    $sesi_id
);

$stmt_check->execute();

$data = $stmt_check
    ->get_result()
    ->fetch_assoc();


/*
|----------------------------------------------------------------------
| SESI TIDAK DITEMUKAN / BUKAN MILIK SISWA
|----------------------------------------------------------------------
*/

if (
    !$data ||
    (int) $data["siswa_id"] !== $siswa_id ||
    (int) $data["ujian_id"] !== $ujian_id
) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "message" => "Sesi ujian tidak valid."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| STATUS SUDAH BERUBAH
|----------------------------------------------------------------------
*/

if ($data["status"] !== "mengerjakan") {

    echo json_encode([
        "status" => "inactive",
        "message" => "Sesi ujian sudah tidak aktif.",
        "session_status" => $data["status"]
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| WAKTU SUDAH HABIS
|----------------------------------------------------------------------
*/

if (strtotime($data["batas_waktu"]) <= time()) {

    echo json_encode([
        "status" => "expired",
        "message" => "Waktu ujian sudah habis."
    ]);

    exit;
}


/*
|----------------------------------------------------------------------
| KONDISI LAIN
|----------------------------------------------------------------------
*/

http_response_code(409);

echo json_encode([
    "status" => "error",
    "message" => "Heartbeat tidak dapat diperbarui."
]);

exit;
?>

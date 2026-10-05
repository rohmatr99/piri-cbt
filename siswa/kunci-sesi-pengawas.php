<?php
declare(strict_types=1);

date_default_timezone_set("Asia/Jakarta");
session_start();

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function kirimJson(bool $status, string $pesan, array $tambahan = []): never
{
    echo json_encode(
        array_merge(
            [
                "status" => $status,
                "pesan" => $pesan
            ],
            $tambahan
        ),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!isset($_SESSION["siswa_id"])) {
    http_response_code(401);
    kirimJson(false, "Sesi login siswa tidak ditemukan.");
}

$siswa_id = (int) $_SESSION["siswa_id"];

$sesi_id = isset($_POST["sesi_id"])
    ? (int) $_POST["sesi_id"]
    : 0;

$ujian_id = isset($_POST["ujian_id"])
    ? (int) $_POST["ujian_id"]
    : 0;

if ($sesi_id <= 0 || $ujian_id <= 0) {
    http_response_code(400);
    kirimJson(false, "Data sesi ujian tidak lengkap.");
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT
            id,
            siswa_id,
            ujian_id,
            status,
            batas_waktu,
            terkunci_pengawas
        FROM sesi_ujian
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan pemeriksaan sesi.");
    }

    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();
    $sesi = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sesi) {
        throw new Exception("Sesi ujian tidak ditemukan.");
    }

    if ((int) $sesi["siswa_id"] !== $siswa_id) {
        throw new Exception("Sesi ujian bukan milik siswa yang sedang login.");
    }

    if ((int) $sesi["ujian_id"] !== $ujian_id) {
        throw new Exception("Ujian tidak sesuai dengan sesi.");
    }

    if ($sesi["status"] !== "mengerjakan") {
        throw new Exception("Ujian sudah selesai.");
    }

    if (strtotime((string) $sesi["batas_waktu"]) <= time()) {
        throw new Exception("Waktu ujian sudah habis.");
    }

    if ((int) $sesi["terkunci_pengawas"] === 1) {
        $stmt = $conn->prepare("
            SELECT id
            FROM kode_pengawas_ujian
            WHERE sesi_id = ?
              AND status = 'aktif'
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Gagal memeriksa kode pengawas.");
        }

        $stmt->bind_param("i", $sesi_id);
        $stmt->execute();
        $kode_aktif = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($kode_aktif) {
            $conn->commit();
            kirimJson(true, "Sesi sudah terkunci.", ["terkunci_pengawas" => true]);
        }
    }

    $stmt = $conn->prepare("
        UPDATE kode_pengawas_ujian
        SET status = 'batal'
        WHERE sesi_id = ?
          AND status = 'aktif'
    ");

    if (!$stmt) {
        throw new Exception("Gagal membatalkan kode lama.");
    }

    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();
    $stmt->close();

    $kode = (string) random_int(100000, 999999);

    $stmt = $conn->prepare("
        INSERT INTO kode_pengawas_ujian
        (
            sesi_id,
            kode,
            dibuat,
            digunakan,
            status
        )
        VALUES (?, ?, NOW(), NULL, 'aktif')
    ");

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan penyimpanan kode pengawas.");
    }

    $stmt->bind_param("is", $sesi_id, $kode);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan kode pengawas.");
    }

    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE sesi_ujian
        SET terkunci_pengawas = 1
        WHERE id = ?
          AND siswa_id = ?
          AND ujian_id = ?
          AND status = 'mengerjakan'
    ");

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan penguncian sesi.");
    }

    $stmt->bind_param("iii", $sesi_id, $siswa_id, $ujian_id);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new Exception("Sesi gagal dikunci.");
    }

    $stmt->close();
    $conn->commit();

    // Kode TIDAK dikirim ke browser siswa.
    kirimJson(true, "Sesi berhasil dikunci. Kode pengawas telah dibuat.", [
        "terkunci_pengawas" => true
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log("kunci-sesi-pengawas.php: " . $e->getMessage());

    $pesan = $e->getMessage();
    $pesanAman = $pesan;

    if (
        stripos($pesan, "SQL") !== false ||
        stripos($pesan, "prepare") !== false ||
        stripos($pesan, "query") !== false ||
        stripos($pesan, "database") !== false
    ) {
        $pesanAman = "Sistem gagal memproses penguncian sesi.";
    }

    http_response_code(400);
    kirimJson(false, $pesanAman);
}
?>

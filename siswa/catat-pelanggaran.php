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
| AMBIL DATA REQUEST
|--------------------------------------------------------------------------
*/

$data = json_decode(
    file_get_contents("php://input"),
    true
);


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

$ujian_id_request = isset($data["ujian_id"])
    ? (int) $data["ujian_id"]
    : 0;

$jenis = trim($data["jenis"] ?? "");


if (
    $sesi_id <= 0 ||
    $ujian_id_request <= 0 ||
    $jenis === ""
) {

    echo json_encode([
        "status" => false,
        "pesan" => "Data pelanggaran tidak lengkap."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| JENIS PELANGGARAN YANG DIIZINKAN
|--------------------------------------------------------------------------
*/

$jenis_diizinkan = [
    "Pindah tab",
    "Halaman ditinggalkan",
    "Browser diminimalkan"
];


if (!in_array($jenis, $jenis_diizinkan, true)) {

    echo json_encode([
        "status" => false,
        "pesan" => "Jenis pelanggaran tidak valid."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| TRANSAKSI
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |-----------------------------------------------------------------------
    | Ambil sesi milik siswa yang sedang login.
    | Sesi dikunci agar dua request pelanggaran bersamaan tidak
    | menghasilkan proses auto-submit ganda.
    |-----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            su.id,
            su.siswa_id,
            su.ujian_id,
            su.status,
            su.batas_waktu,
            u.maks_pelanggaran
        FROM sesi_ujian su
        INNER JOIN ujian u
            ON u.id = su.ujian_id
        WHERE su.id = ?
          AND su.siswa_id = ?
          AND su.ujian_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param(
        "iii",
        $sesi_id,
        $siswa_id,
        $ujian_id_request
    );

    $stmt->execute();

    $sesi = $stmt
        ->get_result()
        ->fetch_assoc();


    if (!$sesi) {

        throw new Exception(
            "Sesi ujian tidak valid."
        );
    }


    if ($sesi["status"] !== "mengerjakan") {

        throw new Exception(
            "Ujian sudah selesai."
        );
    }


    /*
    |-----------------------------------------------------------------------
    | Jika waktu sudah habis, jangan lanjut mencatat pelanggaran sebagai
    | pelanggaran baru.
    |-----------------------------------------------------------------------
    */

    if (
        strtotime($sesi["batas_waktu"]) <= time()
    ) {

        throw new Exception(
            "Waktu ujian telah habis."
        );
    }


    /*
    |-----------------------------------------------------------------------
    | Simpan pelanggaran
    |-----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO pelanggaran_ujian
        (
            sesi_id,
            siswa_id,
            ujian_id,
            jenis
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiis",
        $sesi_id,
        $siswa_id,
        $ujian_id_request,
        $jenis
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Gagal menyimpan pelanggaran."
        );
    }


    /*
    |-----------------------------------------------------------------------
    | Hitung total pelanggaran sesi ini
    |-----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM pelanggaran_ujian
        WHERE sesi_id = ?
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();

    $data_total = $stmt
        ->get_result()
        ->fetch_assoc();

    $total_pelanggaran = (int) $data_total["total"];

    $maks_pelanggaran = (int) $sesi["maks_pelanggaran"];


    /*
    |-----------------------------------------------------------------------
    | Batas tidak aktif jika nilainya 0
    |-----------------------------------------------------------------------
    */

    if (
        $maks_pelanggaran <= 0 ||
        $total_pelanggaran < $maks_pelanggaran
    ) {

        $conn->commit();

        echo json_encode([
            "status" => true,
            "auto_submit" => false,
            "total_pelanggaran" => $total_pelanggaran,
            "maks_pelanggaran" => $maks_pelanggaran,
            "pesan" => "Pelanggaran berhasil dicatat."
        ]);

        exit;
    }


    /*
    |-----------------------------------------------------------------------
    | MAKSIMAL PELANGGARAN TERCAPAI
    |
    | Hasil dihitung dari jawaban yang sudah tersimpan.
    | Tidak mengubah nilai menjadi 0.
    |-----------------------------------------------------------------------
    */

    /* Jumlah soal */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM soal
        WHERE ujian_id = ?
    ");

    $stmt->bind_param(
        "i",
        $ujian_id_request
    );

    $stmt->execute();

    $data = $stmt
        ->get_result()
        ->fetch_assoc();

    $jumlah_soal = (int) $data["total"];


    /* Jumlah jawaban tersimpan */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM jawaban
        WHERE sesi_id = ?
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();

    $data = $stmt
        ->get_result()
        ->fetch_assoc();

    $jumlah_dijawab = (int) $data["total"];


    /* Jumlah benar */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM jawaban j
        INNER JOIN opsi_soal o
            ON j.opsi_id = o.id
        WHERE j.sesi_id = ?
          AND o.benar = 1
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();

    $data = $stmt
        ->get_result()
        ->fetch_assoc();

    $jumlah_benar = (int) $data["total"];


    $jumlah_salah =
        $jumlah_dijawab - $jumlah_benar;


    $nilai = 0;

    if ($jumlah_soal > 0) {

        $nilai =
            ($jumlah_benar / $jumlah_soal) * 100;
    }


    /*
    |-----------------------------------------------------------------------
    | Cegah hasil ganda
    |-----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id
        FROM hasil_ujian
        WHERE sesi_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();

    $hasil_lama = $stmt
        ->get_result()
        ->fetch_assoc();


    if ($hasil_lama) {

        $hasil_id = (int) $hasil_lama["id"];

    } else {

        $stmt = $conn->prepare("
            INSERT INTO hasil_ujian
            (
                sesi_id,
                siswa_id,
                ujian_id,
                jumlah_soal,
                jumlah_dijawab,
                jumlah_benar,
                jumlah_salah,
                nilai
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiiiiiid",
            $sesi_id,
            $siswa_id,
            $ujian_id_request,
            $jumlah_soal,
            $jumlah_dijawab,
            $jumlah_benar,
            $jumlah_salah,
            $nilai
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Gagal menyimpan hasil ujian."
            );
        }

        $hasil_id = $conn->insert_id;
    }


    /*
    |-----------------------------------------------------------------------
    | Tandai sesi selesai
    |-----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE sesi_ujian
        SET
            status = 'selesai',
            selesai = NOW()
        WHERE id = ?
          AND siswa_id = ?
          AND status = 'mengerjakan'
    ");

    $stmt->bind_param(
        "ii",
        $sesi_id,
        $siswa_id
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Gagal menyelesaikan sesi ujian."
        );
    }


    $conn->commit();


    echo json_encode([
        "status" => true,
        "auto_submit" => true,
        "total_pelanggaran" => $total_pelanggaran,
        "maks_pelanggaran" => $maks_pelanggaran,
        "hasil_id" => $hasil_id,
        "jumlah_soal" => $jumlah_soal,
        "jumlah_dijawab" => $jumlah_dijawab,
        "jumlah_benar" => $jumlah_benar,
        "jumlah_salah" => $jumlah_salah,
        "nilai" => $nilai,
        "pesan" =>
            "Batas maksimal pelanggaran tercapai. Ujian dikirim otomatis."
    ]);

    exit;


} catch (Throwable $e) {

    $conn->rollback();

    echo json_encode([
        "status" => false,
        "auto_submit" => false,
        "pesan" => $e->getMessage()
    ]);

    exit;
}

?>

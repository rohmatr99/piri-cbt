<?php

require __DIR__ . "/includes/auth.php";
require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| VARIABEL
|--------------------------------------------------------------------------
*/

$preview = [];

$hasil_import = [];

$error = "";

$pesan = "";

$nama_file = "";

$total_data = 0;

$jumlah_berhasil = 0;

$jumlah_gagal = 0;


/*
|--------------------------------------------------------------------------
| AMBIL PREVIEW DARI SESSION
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["import_preview"])) {

    $preview =
        $_SESSION["import_preview"];

    $nama_file =
        $_SESSION["import_nama_file"] ?? "";

}


/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | TOMBOL BACA CSV
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST["aksi"]) &&
        $_POST["aksi"] === "preview"
    ) {

        $preview = [];

        $error = "";

        $nama_file = "";


        if (
            !isset($_FILES["file_csv"]) ||
            $_FILES["file_csv"]["error"] !== UPLOAD_ERR_OK
        ) {

            $error =
                "Silakan pilih file CSV terlebih dahulu.";

        } else {

            $nama_file =
                $_FILES["file_csv"]["name"];

            $tmp_file =
                $_FILES["file_csv"]["tmp_name"];

            $ukuran_file =
                $_FILES["file_csv"]["size"];


            /*
            |--------------------------------------------------------------------------
            | CEK EKSTENSI
            |--------------------------------------------------------------------------
            */

            $ekstensi =
                strtolower(
                    pathinfo(
                        $nama_file,
                        PATHINFO_EXTENSION
                    )
                );


            if ($ekstensi !== "csv") {

                $error =
                    "File harus berformat CSV.";

            } elseif ($ukuran_file > 5 * 1024 * 1024) {

                $error =
                    "Ukuran file maksimal 5 MB.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | BUKA FILE
                |--------------------------------------------------------------------------
                */

                $handle =
                    fopen(
                        $tmp_file,
                        "r"
                    );


                if ($handle === false) {

                    $error =
                        "File CSV tidak dapat dibaca.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | BACA BARIS PERTAMA
                    |--------------------------------------------------------------------------
                    */

                    $baris_pertama =
                        fgets($handle);


                    if ($baris_pertama === false) {

                        $error =
                            "File CSV kosong.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | HAPUS BOM UTF-8
                        |--------------------------------------------------------------------------
                        */

                        $baris_pertama =
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                $baris_pertama
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | TENTUKAN PEMISAH
                        |--------------------------------------------------------------------------
                        */

                        $jumlah_koma =
                            substr_count(
                                $baris_pertama,
                                ","
                            );

                        $jumlah_titik_koma =
                            substr_count(
                                $baris_pertama,
                                ";"
                            );


                        if (
                            $jumlah_titik_koma >
                            $jumlah_koma
                        ) {

                            $pemisah = ";";

                        } else {

                            $pemisah = ",";

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PARSE HEADER
                        |--------------------------------------------------------------------------
                        */

                        $header =
                            str_getcsv(
                                trim($baris_pertama),
                                $pemisah
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | NORMALISASI HEADER
                        |--------------------------------------------------------------------------
                        */

                        $header =
                            array_map(
                                function ($item) {

                                    return strtolower(
                                        trim(
                                            preg_replace(
                                                '/^\xEF\xBB\xBF/',
                                                '',
                                                $item
                                            )
                                        )
                                    );

                                },
                                $header
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | HEADER WAJIB
                        |--------------------------------------------------------------------------
                        */

                        $header_wajib = [

                            "username",

                            "nama",

                            "kelas",

                            "password",

                            "status"

                        ];


                        $header_lengkap =
                            true;


                        foreach (
                            $header_wajib
                            as $kolom
                        ) {

                            if (
                                !in_array(
                                    $kolom,
                                    $header
                                )
                            ) {

                                $header_lengkap =
                                    false;

                                break;

                            }

                        }


                        if (!$header_lengkap) {

                            $error =
                                "Header CSV harus berisi: "
                                . "username,nama,kelas,password,status";

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | BACA DATA
                            |--------------------------------------------------------------------------
                            */

                            while (
                                (
                                    $data =
                                    fgetcsv(
                                        $handle,
                                        0,
                                        $pemisah
                                    )
                                ) !== false
                            ) {


                                /*
                                |--------------------------------------------------------------------------
                                | LEWATI BARIS KOSONG
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    count(
                                        array_filter(
                                            $data,
                                            function ($value) {

                                                return trim(
                                                    $value
                                                ) !== "";

                                            }
                                        )
                                    ) === 0
                                ) {

                                    continue;

                                }


                                /*
                                |--------------------------------------------------------------------------
                                | BENTUK DATA
                                |--------------------------------------------------------------------------
                                */

                                $baris = [];


                                foreach (
                                    $header
                                    as $index => $kolom
                                ) {

                                    $baris[$kolom] =
                                        trim(
                                            $data[$index]
                                            ?? ""
                                        );

                                }


                                $preview[] =
                                    $baris;

                            }

                        }

                    }


                    fclose($handle);

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | SIMPAN PREVIEW KE SESSION
        |--------------------------------------------------------------------------
        */

        if (
            $error === "" &&
            count($preview) > 0
        ) {

            $_SESSION["import_preview"] =
                $preview;

            $_SESSION["import_nama_file"] =
                $nama_file;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | TOMBOL IMPORT DATA
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST["aksi"]) &&
        $_POST["aksi"] === "import"
    ) {


        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA PREVIEW
        |--------------------------------------------------------------------------
        */

        $data_import =
            $_SESSION["import_preview"]
            ?? [];


        $nama_file =
            $_SESSION["import_nama_file"]
            ?? "";


        if (count($data_import) === 0) {

            $error =
                "Tidak ada data yang dapat diimport. "
                . "Silakan baca file CSV terlebih dahulu.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | PROSES DATA SATU PER SATU
            |--------------------------------------------------------------------------
            */

            $username_dalam_file = [];


            foreach (
                $data_import
                as $index => $row
            ) {

                $nomor =
                    $index + 1;


                $username =
                    trim(
                        $row["username"]
                        ?? ""
                    );

                $nama =
                    trim(
                        $row["nama"]
                        ?? ""
                    );

                $kelas =
                    trim(
                        $row["kelas"]
                        ?? ""
                    );

                $password =
                    trim(
                        $row["password"]
                        ?? ""
                    );

                $status =
                    trim(
                        $row["status"]
                        ?? ""
                    );


                /*
                |--------------------------------------------------------------------------
                | VALIDASI DATA KOSONG
                |--------------------------------------------------------------------------
                */

                $kolom_kosong = [];


                if ($username === "") {

                    $kolom_kosong[] =
                        "username";

                }

                if ($nama === "") {

                    $kolom_kosong[] =
                        "nama";

                }

                if ($kelas === "") {

                    $kolom_kosong[] =
                        "kelas";

                }

                if ($password === "") {

                    $kolom_kosong[] =
                        "password";

                }

                if ($status === "") {

                    $kolom_kosong[] =
                        "status";

                }


                if (count($kolom_kosong) > 0) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Data belum lengkap: "
                            . implode(
                                ", ",
                                $kolom_kosong
                            )

                    ];


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | CEK DUPLIKAT DALAM FILE CSV
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $username_dalam_file[
                            strtolower($username)
                        ]
                    )
                ) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Username duplikat di dalam file CSV"

                    ];


                    continue;

                }


                $username_dalam_file[
                    strtolower($username)
                ] = true;


                /*
                |--------------------------------------------------------------------------
                | CEK USERNAME DI DATABASE
                |--------------------------------------------------------------------------
                */

                $stmt_cek =
                    $conn->prepare(
                        "SELECT id
                         FROM siswa
                         WHERE username = ?
                         LIMIT 1"
                    );


                if (!$stmt_cek) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Kesalahan database saat memeriksa username"

                    ];


                    continue;

                }


                $stmt_cek->bind_param(
                    "s",
                    $username
                );

                $stmt_cek->execute();

                $hasil_cek =
                    $stmt_cek->get_result();

                $sudah_ada =
                    $hasil_cek->num_rows > 0;

                $stmt_cek->close();


                if ($sudah_ada) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Username sudah terdaftar"

                    ];


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT DATA
                |--------------------------------------------------------------------------
                */

                $stmt_insert =
                    $conn->prepare(
                        "INSERT INTO siswa
                        (
                            username,
                            nama,
                            kelas,
                            password,
                            status,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NOW()
                        )"
                    );


                if (!$stmt_insert) {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Gagal menyiapkan data ke database"

                    ];


                    continue;

                }


                $stmt_insert->bind_param(
                    "sssss",
                    $username,
                    $nama,
                    $kelas,
                    $password,
                    $status
                );


                if (
                    $stmt_insert->execute()
                ) {

                    $jumlah_berhasil++;

                } else {

                    $jumlah_gagal++;


                    $hasil_import[] = [

                        "nomor" =>
                            $nomor,

                        "username" =>
                            $username,

                        "nama" =>
                            $nama,

                        "kelas" =>
                            $kelas,

                        "alasan" =>
                            "Gagal menyimpan ke database"

                    ];

                }


                $stmt_insert->close();

            }


            /*
            |--------------------------------------------------------------------------
            | TOTAL DATA
            |--------------------------------------------------------------------------
            */

            $total_data =
                count($data_import);


            /*
            |--------------------------------------------------------------------------
            | HAPUS PREVIEW DARI SESSION
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION["import_preview"]
            );

            unset(
                $_SESSION["import_nama_file"]
            );


            /*
            |--------------------------------------------------------------------------
            | PESAN HASIL
            |--------------------------------------------------------------------------
            */

            $pesan =
                "Proses import selesai.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| PAGE LAYOUT
|--------------------------------------------------------------------------
*/

$page_title = "Import Siswa";

$page_description = "Import data siswa melalui file CSV";

require __DIR__ . "/includes/header.php";

require __DIR__ . "/includes/sidebar.php";

?>

<style>

/* =========================================================
   IMPORT SISWA
========================================================= */

.import-wrapper {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.import-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .05);
    margin-bottom: 20px;
}

.import-card h2,
.import-card h3 {
    margin-top: 0;
    color: var(--text);
}

.import-card h2 {
    margin-bottom: 8px;
    font-size: 21px;
}

.import-card h3 {
    font-size: 17px;
}

.import-subtitle {
    margin: 0 0 20px;
    color: var(--muted);
    font-size: 13px;
}

.info-box,
.error-box,
.success-box,
.warning-box {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 18px;
    line-height: 1.6;
    font-size: 13px;
}

.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

.error-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.success-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.warning-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.info-box code {
    display: inline-block;
    margin-top: 4px;
    padding: 5px 8px;
    background: rgba(255,255,255,.8);
    border-radius: 6px;
    font-size: 12px;
    word-break: break-word;
}

.upload-area {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 24px;
    background: #f8fafc;
}

.upload-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text);
    font-size: 14px;
}

.file-input {
    width: 100%;
    padding: 12px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 9px;
    font-size: 13px;
}

.file-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.button-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}

.btn-import,
.btn-success,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    padding: 10px 16px;
    border: 0;
    border-radius: 9px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: .18s ease;
}

.btn-import {
    background: var(--primary);
    color: white;
}

.btn-import:hover {
    background: var(--primary-dark);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #15803d;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-secondary:hover {
    background: #475569;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 20px 0;
}

.summary-box {
    padding: 18px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid var(--border);
}

.summary-box.berhasil {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}

.summary-box.gagal {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.summary-box span {
    display: block;
    font-size: 12px;
    font-weight: 600;
}

.summary-box strong {
    display: block;
    margin-top: 4px;
    font-size: 28px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.import-table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
    background: white;
}

.import-table th,
.import-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
    font-size: 12px;
}

.import-table th {
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    white-space: nowrap;
}

.import-table tbody tr:last-child td {
    border-bottom: 0;
}

.import-table tbody tr:hover {
    background: #f8fafc;
}

.failed-table th {
    background: #fef2f2;
    color: #991b1b;
}

.preview-meta {
    margin: 0 0 16px;
    color: var(--muted);
    font-size: 13px;
}

.preview-meta strong {
    color: var(--text);
}

@media (max-width: 760px) {

    .import-card {
        padding: 18px;
        border-radius: 12px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .button-row {
        flex-direction: column;
    }

    .btn-import,
    .btn-success,
    .btn-secondary {
        width: 100%;
    }

    .upload-area {
        padding: 18px;
    }

}

@media (max-width: 480px) {

    .import-card {
        padding: 15px;
    }

    .import-card h2 {
        font-size: 19px;
    }

    .summary-box {
        padding: 15px;
    }

    .summary-box strong {
        font-size: 24px;
    }

}

</style>


<main class="main">

    <div class="topbar">

        <div class="page-title">

            <h1>
                Import Siswa
            </h1>

            <p>
                Import data siswa melalui file CSV
            </p>

        </div>

        <div class="top-user">

            <div class="top-user-icon">
                👤
            </div>

            <span>
                <?= htmlspecialchars($_SESSION["admin_nama"] ?? "Administrator") ?>
            </span>

        </div>

    </div>


    <div class="mobile-header">

        <button
            type="button"
            class="menu-button"
            onclick="bukaSidebar()"
            aria-label="Buka menu"
        >
            ☰
        </button>

        <div class="mobile-title">
            Import Siswa
        </div>

        <div style="width:40px;"></div>

    </div>


    <section class="content">

        <div class="import-wrapper">


            <div class="import-card">

                <h2>
                    Import Data Siswa
                </h2>

                <p class="import-subtitle">
                    Upload file CSV untuk menambahkan banyak siswa sekaligus.
                </p>


                <div class="info-box">

                    <strong>Format CSV:</strong>

                    <br>

                    Kolom wajib:

                    <br>

                    <code>
                        username,nama,kelas,password,status
                    </code>

                    <br><br>

                    CSV dari Excel dengan pemisah
                    <strong>koma (,)</strong>
                    atau
                    <strong>titik koma (;)</strong>
                    dapat digunakan.

                    <br><br>

                    Contoh:

                    <br>

                    <code>
                        24001,Ahmad Fauzan,VIII A,123456,aktif
                    </code>

                </div>


                <?php if ($error !== ""): ?>

                    <div class="error-box">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <?php if ($pesan !== ""): ?>

                    <div class="success-box">

                        <?= htmlspecialchars($pesan) ?>

                    </div>

                <?php endif; ?>


                <?php if ($total_data > 0): ?>


                    <div class="summary-grid">

                        <div class="summary-box">

                            <span>
                                Total Data
                            </span>

                            <strong>
                                <?= $total_data ?>
                            </strong>

                        </div>


                        <div class="summary-box berhasil">

                            <span>
                                Berhasil
                            </span>

                            <strong>
                                <?= $jumlah_berhasil ?>
                            </strong>

                        </div>


                        <div class="summary-box gagal">

                            <span>
                                Gagal
                            </span>

                            <strong>
                                <?= $jumlah_gagal ?>
                            </strong>

                        </div>

                    </div>


                    <?php if ($jumlah_gagal > 0): ?>

                        <div class="warning-box">

                            <strong>
                                Perhatian:
                            </strong>

                            Ada
                            <?= $jumlah_gagal ?>
                            data yang tidak berhasil diimport.

                            Silakan periksa tabel data gagal di bawah ini,
                            perbaiki data tersebut di Excel,
                            kemudian import ulang.

                        </div>


                        <div class="import-card">

                            <h3>
                                Data yang Gagal Diimport
                            </h3>


                            <div class="table-wrapper">

                                <table class="import-table failed-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                No Baris
                                            </th>

                                            <th>
                                                Username
                                            </th>

                                            <th>
                                                Nama
                                            </th>

                                            <th>
                                                Kelas
                                            </th>

                                            <th>
                                                Alasan
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach (
                                            $hasil_import
                                            as $gagal
                                        ): ?>

                                            <tr>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $gagal["nomor"]
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $gagal["username"]
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $gagal["nama"]
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $gagal["kelas"]
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $gagal["alasan"]
                                                    ) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="success-box">

                            <strong>
                                Semua data berhasil diimport.
                            </strong>

                        </div>

                    <?php endif; ?>


                    <div class="button-row">

                        <a
                            href="siswa.php"
                            class="btn-secondary"
                        >
                            ← Kembali ke Data Siswa
                        </a>

                        <a
                            href="import-siswa.php"
                            class="btn-import"
                        >
                            ↻ Import File Lain
                        </a>

                    </div>


                <?php else: ?>


                    <div class="upload-area">

                        <form
                            method="POST"
                            enctype="multipart/form-data"
                        >

                            <input
                                type="hidden"
                                name="aksi"
                                value="preview"
                            >


                            <label
                                class="upload-label"
                                for="file_csv"
                            >
                                Pilih File CSV
                            </label>


                            <input
                                type="file"
                                id="file_csv"
                                name="file_csv"
                                class="file-input"
                                accept=".csv"
                                required
                            >


                            <div class="button-row">

                                <button
                                    type="submit"
                                    class="btn-import"
                                >
                                    📄 Baca File CSV
                                </button>

                                <a
                                    href="siswa.php"
                                    class="btn-secondary"
                                >
                                    ← Kembali
                                </a>

                            </div>

                        </form>

                    </div>


                <?php endif; ?>


            </div>


            <?php if (
                count($preview) > 0 &&
                $total_data === 0
            ): ?>


                <div class="import-card">

                    <h3>
                        Preview Data
                    </h3>


                    <p class="preview-meta">

                        File:

                        <strong>
                            <?= htmlspecialchars($nama_file) ?>
                        </strong>

                        <br>

                        Jumlah baris:

                        <strong>
                            <?= count($preview) ?>
                        </strong>

                    </p>


                    <div class="table-wrapper">

                        <table class="import-table">

                            <thead>

                                <tr>

                                    <th>
                                        No
                                    </th>

                                    <th>
                                        Username
                                    </th>

                                    <th>
                                        Nama
                                    </th>

                                    <th>
                                        Kelas
                                    </th>

                                    <th>
                                        Password
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php

                                $no = 1;

                                foreach (
                                    $preview
                                    as $row
                                ):

                                ?>

                                    <tr>

                                        <td>
                                            <?= $no++ ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row["username"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row["nama"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row["kelas"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row["password"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row["status"]
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <form
                        method="POST"
                        onsubmit="
                            return confirm(
                                'Yakin ingin mengimport data siswa ke database?'
                            );
                        "
                    >

                        <input
                            type="hidden"
                            name="aksi"
                            value="import"
                        >


                        <div class="button-row">

                            <button
                                type="submit"
                                class="btn-success"
                            >
                                ✓ Import Data ke Database
                            </button>

                            <a
                                href="siswa.php"
                                class="btn-secondary"
                            >
                                Batal
                            </a>

                        </div>

                    </form>


                </div>


            <?php endif; ?>


        </div>

    </section>

</main>


<?php

require __DIR__ . "/includes/footer.php";

?>
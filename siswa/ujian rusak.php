<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["siswa_id"])) {

    header("Location: login.php");
    exit;

}

$siswa_id = (int) $_SESSION["siswa_id"];


/*
|--------------------------------------------------------------------------
| ID UJIAN
|--------------------------------------------------------------------------
*/

$ujian_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 1;


/*
|--------------------------------------------------------------------------
| AMBIL DATA UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
    u.id,
    u.nama_ujian,
    u.kelas,
    u.durasi,
    u.minimal_menit,
    u.status,
    m.nama AS nama_mapel
	
    FROM ujian u
    JOIN mata_pelajaran m
        ON u.mapel_id = m.id
    WHERE u.id = ?
      AND u.status = 'aktif'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $ujian_id
);

$stmt->execute();

$ujian = $stmt
    ->get_result()
    ->fetch_assoc();


if (!$ujian) {

    die("Ujian tidak tersedia.");

}


/*
|--------------------------------------------------------------------------
| CEK SESI UJIAN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM sesi_ujian
    WHERE siswa_id = ?
      AND ujian_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $siswa_id,
    $ujian_id
);

$stmt->execute();

$sesi = $stmt
    ->get_result()
    ->fetch_assoc();


/*
|--------------------------------------------------------------------------
| BUAT SESI BARU JIKA BELUM ADA
|--------------------------------------------------------------------------
*/

if (!$sesi) {

    $mulai = date("Y-m-d H:i:s");

    $batas_waktu = date(
        "Y-m-d H:i:s",
        time() + ($ujian["durasi"] * 60)
    );


    $stmt = $conn->prepare("
        INSERT INTO sesi_ujian
        (
            siswa_id,
            ujian_id,
            mulai,
            batas_waktu,
            status
        )
        VALUES (?, ?, ?, ?, 'mengerjakan')
    ");


    $stmt->bind_param(
        "iiss",
        $siswa_id,
        $ujian_id,
        $mulai,
        $batas_waktu
    );


    if (!$stmt->execute()) {

        die(
            "Gagal membuat sesi ujian: "
            . $stmt->error
        );

    }


    $sesi_id = $conn->insert_id;


    /*
    |----------------------------------------------------------------------
    | Ambil kembali sesi
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM sesi_ujian
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $sesi_id
    );

    $stmt->execute();

    $sesi = $stmt
        ->get_result()
        ->fetch_assoc();

}


/*
|--------------------------------------------------------------------------
| CEK STATUS SESI
|--------------------------------------------------------------------------
*/

if ($sesi["status"] !== "mengerjakan") {

    die(
        "Ujian sudah selesai dan tidak dapat dikerjakan kembali."
    );

}


/*
|--------------------------------------------------------------------------
| HITUNG SISA WAKTU
|--------------------------------------------------------------------------
*/

$sisa_detik =
    strtotime($sesi["batas_waktu"])
    - time();


/*
|--------------------------------------------------------------------------
| JIKA WAKTU SUDAH HABIS
|--------------------------------------------------------------------------
|
| Kita arahkan langsung ke auto-kirim.
| Jangan hanya menampilkan "waktu habis".
|--------------------------------------------------------------------------
*/

if ($sisa_detik <= 0) {

    header("Location: auto-kirim.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| AMBIL SEMUA SOAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM soal
    WHERE ujian_id = ?
    ORDER BY RAND(" . (int)$sesi["id"] . ")
");

$stmt->bind_param(
    "i",
    $ujian_id
);

$stmt->execute();

$soal_result = $stmt->get_result();


$soal = [];


while ($row = $soal_result->fetch_assoc()) {

    $soal[] = $row;

}


/*
|--------------------------------------------------------------------------
| AMBIL JAWABAN SISWA
|--------------------------------------------------------------------------
*/

$jawaban_siswa = [];


$stmt = $conn->prepare("
    SELECT
        soal_id,
        opsi_id
    FROM jawaban
    WHERE sesi_id = ?
");

$stmt->bind_param(
    "i",
    $sesi["id"]
);

$stmt->execute();


$result_jawaban =
    $stmt->get_result();


while (
    $row =
    $result_jawaban->fetch_assoc()
) {

    $jawaban_siswa[
        $row["soal_id"]
    ] = $row["opsi_id"];

}
$total_soal = count($soal);

$sudah_dijawab =
    count($jawaban_siswa);

$belum_dijawab =
    $total_soal - $sudah_dijawab;


/*
|--------------------------------------------------------------------------
| AMBIL OPSI JAWABAN
|--------------------------------------------------------------------------
*/

$opsi = [];


/* 
|--------------------------------------------------------------------------
| AMBIL OPSI JAWABAN
|--------------------------------------------------------------------------
*/

$opsi = [];

if (count($soal) > 0) {

    $soal_ids = [];

    foreach ($soal as $s) {

        $soal_ids[] =
            (int)$s["id"];

    }


    $id_string =
        implode(",", $soal_ids);


    /*
    |--------------------------------------------------------------------------
    | AMBIL SEMUA OPSI
    |--------------------------------------------------------------------------
    */

    $query = "
        SELECT *
        FROM opsi_soal
        WHERE soal_id IN ($id_string)
        ORDER BY
            soal_id ASC,
            kode ASC
    ";


    $result_opsi =
        $conn->query($query);


    if (!$result_opsi) {

        die(
            "Gagal mengambil pilihan jawaban: " .
            $conn->error
        );

    }


    /*
    |--------------------------------------------------------------------------
    | MASUKKAN OPSI KE ARRAY BERDASARKAN SOAL
    |--------------------------------------------------------------------------
    */

    while (
        $row =
        $result_opsi->fetch_assoc()
    ) {

        $opsi[
            $row["soal_id"]
        ][] = $row;

    }


    /*
    |--------------------------------------------------------------------------
    | ACAK PILIHAN JAWABAN PER SOAL
    |--------------------------------------------------------------------------
    |
    | Menggunakan:
    | - ID sesi
    | - ID soal
    | - ID opsi
    |
    | sehingga urutan pilihan:
    | - berbeda antar sesi
    | - tetap sama ketika refresh
    | - tidak mengubah opsi_id
    | - tidak mengubah nilai benar
    |
    */

    foreach (
        $opsi
        as $soal_id => &$daftarOpsi
    ) {

        usort(
            $daftarOpsi,
            function (
                $a,
                $b
            ) use (
                $sesi,
                $soal_id
            ) {

                $nilaiA = crc32(
                    $sesi["id"] .
                    "-" .
                    $soal_id .
                    "-" .
                    $a["id"]
                );


                $nilaiB = crc32(
                    $sesi["id"] .
                    "-" .
                    $soal_id .
                    "-" .
                    $b["id"]
                );


                return $nilaiA <=> $nilaiB;

            }
        );

    }


    unset($daftarOpsi);

}
?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
<?= htmlspecialchars($ujian["nama_ujian"]) ?>
</title>


<style>

/*
|--------------------------------------------------------------------------
| DASAR
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background: #f2f5f9;

}


.container {

    max-width: 700px;

    margin: auto;

    padding: 15px;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {

    background: white;

    padding: 18px;

    border-radius: 12px;

    margin-bottom: 15px;

    position: sticky;

    top: 0;

    z-index: 10;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.08);

}


.header h2 {

    margin:
        0 0 5px 0;

}


.mapel {

    color: #666;

}


/*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

.timer {

    margin-top: 12px;

    background: #dc3545;

    color: white;

    padding: 10px;

    border-radius: 8px;

    text-align: center;

    font-size: 20px;

    font-weight: bold;

}


/*
|--------------------------------------------------------------------------
| STATUS SIMPAN
|--------------------------------------------------------------------------
*/

.status {

    background: white;

    padding: 10px;

    border-radius: 8px;

    margin-bottom: 15px;

    text-align: center;

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| SOAL
|--------------------------------------------------------------------------
*/

.soal {

    display: none;

    background: white;

    padding: 20px;

    border-radius: 12px;

    margin-bottom: 15px;

}


.soal.aktif {

    display: block;

}


.nomor-soal {

    font-weight: bold;

    margin-bottom: 15px;

}


.pertanyaan {

    line-height: 1.6;

    margin-bottom: 20px;

}


/*
|--------------------------------------------------------------------------
| OPSI
|--------------------------------------------------------------------------
*/

.opsi {

    display: block;

    padding: 13px;

    margin-bottom: 10px;

    border:
        1px solid #ddd;

    border-radius: 8px;

    cursor: pointer;

}


.opsi:hover {

    background: #f5f5f5;

}


.opsi input {

    margin-right: 8px;

}


/*
|--------------------------------------------------------------------------
| NAVIGASI
|--------------------------------------------------------------------------
*/

.navigasi {

    background: white;

    padding: 15px;

    border-radius: 12px;

    margin-bottom: 15px;

}


.navigasi h4 {

    margin-top: 0;

}


.nomor {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 42px;

    height: 42px;

    margin: 4px;

    border-radius: 8px;

    background: #e9ecef;

    color: #333;

    cursor: pointer;

    font-weight: bold;

}


.nomor.aktif {

    background: #198754;

    color: white;

}


.nomor.terjawab {

    background: #0d6efd;

    color: white;

}


/*
|--------------------------------------------------------------------------
| TOMBOL
|--------------------------------------------------------------------------
*/

.tombol {

    display: flex;

    gap: 10px;

    margin-bottom: 15px;

}


.tombol button {

    flex: 1;

    padding: 13px;

    border: none;

    border-radius: 8px;

    font-size: 15px;

    cursor: pointer;

}


.sebelumnya {

    background: #6c757d;

    color: white;

}


.berikutnya {

    background: #198754;

    color: white;

}


/*
|--------------------------------------------------------------------------
| KIRIM
|--------------------------------------------------------------------------
*/

.kirim {

    width: 100%;

    padding: 15px;

    border: none;

    border-radius: 8px;

    background: #dc3545;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

}


</style>

</head>


<body>


<div class="container">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

<h2>

<?= htmlspecialchars(
    $ujian["nama_ujian"]
) ?>

</h2>


<div class="mapel">

<?= htmlspecialchars(
    $ujian["nama_mapel"]
) ?>

</div>


<div class="timer">

Waktu tersisa:

<span id="timer">
00:00
</span>

</div>

</div>


<!-- =========================================================
     STATUS
========================================================= -->

<div
    class="status"
    id="status-simpan"
>

Belum ada jawaban disimpan

</div>
<div
    class="status"
    id="status-pelanggaran"
    style="
        background: #fff3cd;
        color: #856404;
        font-weight: bold;
    "
>
    ⚠️ Pelanggaran: 0
</div>


<!-- =========================================================
     DAFTAR SOAL
========================================================= -->

<div id="daftar-soal">


<?php foreach (
    $soal
    as $index => $s
): ?>


<div
    class="soal
    <?= $index === 0
        ? 'aktif'
        : ''
    ?>"
    id="soal-<?= $index ?>"
>


<div class="nomor-soal">

Soal <?= $index + 1 ?>

</div>


<div class="pertanyaan">

<?= nl2br(
    htmlspecialchars(
        $s["pertanyaan"]
    )
) ?>

</div>


<?php

if (
    isset(
        $opsi[$s["id"]]
    )
):

?>


<?php foreach (
    $opsi[$s["id"]]
    as $indexOpsi => $o
):

    $kodeTampilan = chr(65 + $indexOpsi);

?>


<label class="opsi">


<input

    type="radio"

    name="soal_<?= $s["id"] ?>"

    value="<?= $o["id"] ?>"

    <?= isset(
        $jawaban_siswa[
            $s["id"]
        ]
    )
    &&
    $jawaban_siswa[
        $s["id"]
    ] == $o["id"]

        ? "checked"

        : ""
    ?>

    onchange="
        simpanJawaban(
            <?= $s["id"] ?>,
            <?= $o["id"] ?>
        )
    "

>


<?= $kodeTampilan ?>.

<?= htmlspecialchars(
    $o["teks"]
) ?>


</label>


<?php endforeach; ?>


<?php endif; ?>


</div>


<?php endforeach; ?>


</div>


<!-- =========================================================
     NAVIGASI NOMOR
========================================================= -->

<div class="navigasi">


<h4>
Nomor Soal
</h4>


<?php foreach (
    $soal
    as $index => $s
): ?>


<span

    class="
        nomor

        <?= $index === 0
            ? 'aktif'
            : ''
        ?>

        <?= isset(
            $jawaban_siswa[
                $s["id"]
            ]
        )
            ? 'terjawab'
            : ''
        ?>
    "

    id="nav-<?= $index ?>"

    onclick="
        tampilSoal(
            <?= $index ?>
        )
    "

>

<?= $index + 1 ?>

</span>


<?php endforeach; ?>


</div>


<!-- =========================================================
     TOMBOL SEBELUM / BERIKUTNYA
========================================================= -->

<div class="tombol">


<button
    type="button"
    class="sebelumnya"
    onclick="soalSebelumnya()"
>

← Sebelumnya

</button>


<button
    type="button"
    class="berikutnya"
    onclick="soalBerikutnya()"
>

Berikutnya →

</button>


</div>

<div
    id="info-minimal-waktu"
    class="status"
    style="
        background: #fff3cd;
        color: #856404;
        font-weight: bold;
    "
>
    🔒 Menghitung waktu minimal...
</div>
<!-- =========================================================
     KIRIM MANUAL
========================================================= -->

<form
    action="kirim-ujian.php"
    method="POST"
    onpointerdown="
        tombolKirimDitekan = true;
    "
    onsubmit="
        return konfirmasiKirim();
    "
>


<button
    type="submit"
    class="kirim"
    id="tombol-kirim"
    disabled
    style="
        opacity: 0.5;
        cursor: not-allowed;
    "
>
    🔒 KIRIM UJIAN
</button>

 
</form>


</div>


<script>
function updateMinimalWaktu() {

    if (
        !tombolKirim ||
        !infoMinimalWaktu
    ) {
        return;
    }

    const sekarang =
        Math.floor(
            Date.now() / 1000
        );

    const sisaMinimal =
        waktuMinimalSelesai - sekarang;

    // lanjutkan kode lama di bawah sini

    const sekarang =
        Math.floor(Date.now() / 1000);

    const sisaMinimal =
        waktuMinimalSelesai - sekarang;

    /*
    |--------------------------------------------------------------------------
    | Tidak ada minimal waktu
    |--------------------------------------------------------------------------
    */

    if (minimalMenit <= 0) {

        tombolKirim.disabled = false;

        tombolKirim.style.opacity = "1";
        tombolKirim.style.cursor = "pointer";

        tombolKirim.innerText =
            "KIRIM UJIAN";

        infoMinimalWaktu.innerText =
            "✅ Ujian dapat dikirim kapan saja.";

        infoMinimalWaktu.style.background =
            "#d1e7dd";

        infoMinimalWaktu.style.color =
            "#0f5132";

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Minimal waktu belum tercapai
    |--------------------------------------------------------------------------
    */

    if (sisaMinimal > 0) {

        const menit =
            Math.floor(
                sisaMinimal / 60
            );

        const detik =
            sisaMinimal % 60;

        tombolKirim.disabled = true;

        tombolKirim.style.opacity = "0.5";
        tombolKirim.style.cursor = "not-allowed";

        tombolKirim.innerText =
            "🔒 BELUM DAPAT DIKIRIM";

        infoMinimalWaktu.innerText =
            "🔒 KIRIM UJIAN TERSEDIA DALAM " +
            String(menit).padStart(2, "0") +
            ":" +
            String(detik).padStart(2, "0");

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Minimal waktu sudah tercapai
    |--------------------------------------------------------------------------
    */

    tombolKirim.disabled = false;

    tombolKirim.style.opacity = "1";
    tombolKirim.style.cursor = "pointer";

    tombolKirim.innerText =
        "KIRIM UJIAN";

    infoMinimalWaktu.innerText =
        "✅ Minimal waktu telah tercapai. Ujian dapat dikirim.";

    infoMinimalWaktu.style.background =
        "#d1e7dd";

    infoMinimalWaktu.style.color =
        "#0f5132";
}
/*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

let sisaDetik =
    <?= (int) $sisa_detik ?>;

let sedangMengirim =
    false;

/*
|--------------------------------------------------------------------------
| MINIMAL WAKTU MENGERJAKAN
|--------------------------------------------------------------------------
*/

const minimalMenit =
    <?= (int) $ujian["minimal_menit"] ?>;

const mulaiUjian =
    <?= strtotime($sesi["mulai"]) ?>;

const waktuMinimalSelesai =
    mulaiUjian + (minimalMenit * 60);

const tombolKirim =
    document.getElementById(
        "tombol-kirim"
    );

const infoMinimalWaktu =
    document.getElementById(
        "info-minimal-waktu"
    );


/*
|--------------------------------------------------------------------------
| UPDATE TIMER
|--------------------------------------------------------------------------
*/

function updateTimer() {


    /*
    |----------------------------------------------------------------------
    | Jika waktu habis
    |----------------------------------------------------------------------
    */

    if (
        sisaDetik <= 0
    ) {

        document
            .getElementById(
                "timer"
            )
            .innerText =
            "00:00";


        if (
            !sedangMengirim
        ) {

            sedangMengirim =
                true;


            alert(
                "Waktu ujian telah habis. Jawaban akan dikirim otomatis."
            );


            /*
            |------------------------------------------------------------------
            | KIRIM OTOMATIS
            |------------------------------------------------------------------
            */

            window.location.href =
                "auto-kirim.php";

        }


        return;

    }


    /*
    |----------------------------------------------------------------------
    | Hitung menit dan detik
    |----------------------------------------------------------------------
    */

    let menit =
        Math.floor(
            sisaDetik / 60
        );


    let detik =
        sisaDetik % 60;


    document
        .getElementById(
            "timer"
        )
        .innerText =

        String(
            menit
        ).padStart(
            2,
            "0"
        )

        + ":"

        +

        String(
            detik
        ).padStart(
            2,
            "0"
        );


    sisaDetik--;

}


/*
|--------------------------------------------------------------------------
| Jalankan timer
|--------------------------------------------------------------------------
*/

updateTimer();
updateMinimalWaktu();


setInterval(
    updateTimer,
    1000
);

setInterval(
    updateMinimalWaktu,
    1000
);


/*
|--------------------------------------------------------------------------
| SIMPAN JAWABAN
|--------------------------------------------------------------------------
*/

function simpanJawaban(
    soalId,
    opsiId
) {


    document
        .getElementById(
            "status-simpan"
        )
        .innerText =
        "Menyimpan jawaban...";


    fetch(
        "simpan-jawaban.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/json"

            },

            body:
                JSON.stringify({

                    sesi_id:
                        <?= (int) $sesi["id"] ?>,

                    soal_id:
                        soalId,

                    opsi_id:
                        opsiId

                })

        }

    )


    .then(
        response =>
            response.json()
    )


    .then(
        data => {


            if (
                data.status
            ) {

                document
                    .getElementById(
                        "status-simpan"
                    )
                    .innerText =
                    data.pesan;


                /*
                |--------------------------------------------------------------
                | Tandai nomor sebagai sudah dijawab
                |--------------------------------------------------------------
                */

                let index =
                    cariIndexSoal(
                        soalId
                    );


                let nav =
                    document
                        .getElementById(
                            "nav-" + index
                        );


                if (nav) {

                    nav.classList
                        .add(
                            "terjawab"
                        );

                }


            } else {

                document
                    .getElementById(
                        "status-simpan"
                    )
                    .innerText =
                    "Gagal menyimpan jawaban.";

            }


        }

    )


    .catch(
        error => {

            console.error(
                error
            );


            document
                .getElementById(
                    "status-simpan"
                )
                .innerText =
                "Gagal menyimpan jawaban.";

        }
    );

}


/*
|--------------------------------------------------------------------------
| CARI INDEX SOAL
|--------------------------------------------------------------------------
*/

function cariIndexSoal(
    soalId
) {


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    for (
        let i = 0;
        i < semuaSoal.length;
        i++
    ) {


        if (
            semuaSoal[i]
                .querySelector(
                    'input[name="soal_' +
                    soalId +
                    '"]'
                )
        ) {

            return i;

        }

    }


    return 0;

}


/*
|--------------------------------------------------------------------------
| SOAL SEKARANG
|--------------------------------------------------------------------------
*/

let soalSekarang = 0;


/*
|--------------------------------------------------------------------------
| TAMPILKAN SOAL
|--------------------------------------------------------------------------
*/

function tampilSoal(
    index
) {


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    let semuaNomor =
        document.querySelectorAll(
            ".nomor"
        );


    if (
        index < 0
        ||
        index >=
        semuaSoal.length
    ) {

        return;

    }


    /*
    |----------------------------------------------------------------------
    | Sembunyikan semua soal
    |----------------------------------------------------------------------
    */

    semuaSoal.forEach(
        soal => {

            soal.classList
                .remove(
                    "aktif"
                );

        }
    );


    /*
    |----------------------------------------------------------------------
    | Hilangkan nomor aktif
    |----------------------------------------------------------------------
    */

    semuaNomor.forEach(
        nomor => {

            nomor.classList
                .remove(
                    "aktif"
                );

        }
    );


    /*
    |----------------------------------------------------------------------
    | Tampilkan soal yang dipilih
    |----------------------------------------------------------------------
    */

    semuaSoal[index]
        .classList
        .add(
            "aktif"
        );


    semuaNomor[index]
        .classList
        .add(
            "aktif"
        );


    soalSekarang =
        index;


    /*
    |----------------------------------------------------------------------
    | Scroll ke atas
    |----------------------------------------------------------------------
    */

    window.scrollTo({

        top: 0,

        behavior: "smooth"

    });

}


/*
|--------------------------------------------------------------------------
| SOAL BERIKUTNYA
|--------------------------------------------------------------------------
*/

function soalBerikutnya() {


    let totalSoal =
        document.querySelectorAll(
            ".soal"
        ).length;


    if (
        soalSekarang
        <
        totalSoal - 1
    ) {

        tampilSoal(
            soalSekarang + 1
        );

    }

}


/*
|--------------------------------------------------------------------------
| SOAL SEBELUMNYA
|--------------------------------------------------------------------------
*/

function soalSebelumnya() {


    if (
        soalSekarang > 0
    ) {

        tampilSoal(
            soalSekarang - 1
        );

    }

}


/*
|--------------------------------------------------------------------------
| KONFIRMASI KIRIM MANUAL
|--------------------------------------------------------------------------
*/

function konfirmasiKirim() {
	    const sekarang =
        Math.floor(Date.now() / 1000);

    if (
        sekarang < waktuMinimalSelesai
    ) {

        alert(
            "Ujian belum dapat dikirim.\n\n" +
            "Minimal waktu mengerjakan adalah " +
            minimalMenit +
            " menit."
        );

        return false;
    }

    if (
        sedangMengirim
    ) {

        return false;

    }


    let semuaSoal =
        document.querySelectorAll(
            ".soal"
        );


    let totalSoal =
        semuaSoal.length;


    let sudahDijawab = 0;


    semuaSoal.forEach(
        function(soal) {

            let pilihan =
                soal.querySelector(
                    'input[type="radio"]:checked'
                );


            if (pilihan) {

                sudahDijawab++;

            }

        }
    );


    let belumDijawab =
        totalSoal - sudahDijawab;


    if (
        belumDijawab > 0
    ) {

        alert(
            "Ujian belum dapat dikirim.\n\n" +
            "Total soal      : " +
            totalSoal +
            "\n" +
            "Sudah dijawab   : " +
            sudahDijawab +
            "\n" +
            "Belum dijawab   : " +
            belumDijawab +
            "\n\n" +
            "Silakan jawab semua soal terlebih dahulu."
        );


        return false;

    }


    let yakin = confirm(
    "Semua soal sudah dijawab.\n\n" +
    "Total soal    : " +
    totalSoal +
    "\n" +
    "Sudah dijawab : " +
    sudahDijawab +
    "\n\n" +
    "Apakah Anda yakin ingin mengirim ujian?"
);

if (yakin) {

    sedangMengirim = true;

    return true;

}

tombolKirimDitekan = false;

return false;

}

// =========================================================
// ANTI-CURANG TAHAP 1
// DETEKSI MENINGGALKAN HALAMAN UJIAN
// =========================================================

let jumlahPelanggaran = 0;
let sedangMeninggalkanHalaman = false;
let waktuTerakhirPelanggaran = 0;


/*
|--------------------------------------------------------------------------
| CATAT PELANGGARAN
|--------------------------------------------------------------------------
*/

function catatPelanggaran(jenis) {

    /*
    | Jangan mencatat pelanggaran ketika ujian sedang dikirim.
    */
    if (
        sedangMengirim ||
        tombolKirimDitekan
    ) {
        return;
    }


    /*
    | Cegah satu kejadian tercatat dua kali.
    | visibilitychange dan blur dapat terjadi bersamaan.
    */
    const sekarang = Date.now();

    if (
        sekarang - waktuTerakhirPelanggaran < 2000
    ) {
        return;
    }

    waktuTerakhirPelanggaran = sekarang;


    /*
    | Tambah counter di layar.
    */
    jumlahPelanggaran++;

    const statusPelanggaran =
        document.getElementById(
            "status-pelanggaran"
        );

    if (statusPelanggaran) {

        statusPelanggaran.innerText =
            "⚠️ Pelanggaran: " +
            jumlahPelanggaran;

    }


    /*
    | Kirim ke server.
    */
    fetch(
        "catat-pelanggaran.php",
        {
            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({
                sesi_id: <?= (int) $sesi["id"] ?>,
                ujian_id: <?= (int) $ujian_id ?>,
                jenis: jenis
            })
        }
    )
    .then(
        response => response.json()
    )
    .then(
        data => {

            if (data.status) {

                alert(
                    "⚠️ PERINGATAN UJIAN\n\n" +
                    "Anda meninggalkan halaman ujian.\n\n" +
                    "Pelanggaran: " +
                    jumlahPelanggaran +
                    "\n\n" +
                    "Silakan kembali fokus pada ujian."
                );

            } else {

                console.error(
                    "Pelanggaran gagal dicatat:",
                    data.pesan || data
                );

            }

        }
    )
    .catch(
        error => {

            console.error(
                "Gagal mencatat pelanggaran:",
                error
            );

        }
    );
}


/*
|--------------------------------------------------------------------------
| DETEKSI PINDAH TAB / BROWSER KE BACKGROUND
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "visibilitychange",
    function () {

        if (
            document.visibilityState === "hidden"
        ) {

            sedangMeninggalkanHalaman = true;

            catatPelanggaran(
                "Pindah tab"
            );

        } else {

            sedangMeninggalkanHalaman = false;

        }

    }
);


/*
|--------------------------------------------------------------------------
| DETEKSI WINDOW KEHILANGAN FOKUS
|--------------------------------------------------------------------------
*/

window.addEventListener(
    "blur",
    function () {

        /*
        | Jika halaman kehilangan fokus karena klik
        | tombol kirim, jangan anggap sebagai pelanggaran.
        */
        if (
            sedangMengirim ||
            tombolKirimDitekan
        ) {
            return;
        }

        catatPelanggaran(
            "Halaman ditinggalkan"
        );

    }
);

</script>


</body>

</html>

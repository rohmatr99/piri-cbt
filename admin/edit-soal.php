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
    die("Data soal tidak valid.");
}

/* Ambil data soal */
$stmt = $conn->prepare("
    SELECT id, ujian_id, nomor, pertanyaan, bobot
    FROM soal
    WHERE id = ? AND ujian_id = ?
");
$stmt->bind_param("ii", $id, $ujian_id);
$stmt->execute();

$result = $stmt->get_result();
$soal = $result->fetch_assoc();

if (!$soal) {
    die("Soal tidak ditemukan.");
}

/* Ambil data ujian */
$stmt = $conn->prepare("
    SELECT id, nama_ujian
    FROM ujian
    WHERE id = ?
");
$stmt->bind_param("i", $ujian_id);
$stmt->execute();

$ujian = $stmt->get_result()->fetch_assoc();

if (!$ujian) {
    die("Ujian tidak ditemukan.");
}

/* Ambil pilihan jawaban */
$stmt = $conn->prepare("
    SELECT id, kode, teks, benar
    FROM opsi_soal
    WHERE soal_id = ?
    ORDER BY kode ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();

$result_opsi = $stmt->get_result();

$opsi = [];

while ($row = $result_opsi->fetch_assoc()) {
    $opsi[$row["kode"]] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Soal</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .opsi {
            margin-bottom: 15px;
        }

        .kunci {
            margin-top: 10px;
            padding: 15px;
            background: #f1f5f9;
            border-radius: 6px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            margin-top: 20px;
        }

        .btn-simpan {
            background: #198754;
            color: white;
        }

        .btn-kembali {
            background: #6c757d;
            color: white;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Edit Soal</h2>

    <p>
        <strong>Ujian:</strong>
        <?= htmlspecialchars($ujian["nama_ujian"]) ?>
    </p>

    <form action="update-soal.php" method="POST">

        <input type="hidden" name="id" value="<?= $soal["id"] ?>">
        <input type="hidden" name="ujian_id" value="<?= $soal["ujian_id"] ?>">

        <label>Nomor Soal</label>
        <input
            type="number"
            name="nomor"
            value="<?= $soal["nomor"] ?>"
            min="1"
            required
        >

        <label>Pertanyaan</label>
        <textarea name="pertanyaan" required><?= htmlspecialchars($soal["pertanyaan"]) ?></textarea>

        <label>Bobot</label>
        <input
            type="number"
            name="bobot"
            value="<?= $soal["bobot"] ?>"
            min="1"
            required
        >

        <h3>Pilihan Jawaban</h3>

        <?php foreach (["A", "B", "C", "D"] as $kode): ?>

            <div class="opsi">

                <label>
                    Pilihan <?= $kode ?>
                </label>

                <input
                    type="text"
                    name="opsi_<?= $kode ?>"
                    value="<?= isset($opsi[$kode]) ? htmlspecialchars($opsi[$kode]["teks"]) : "" ?>"
                    required
                >

            </div>

        <?php endforeach; ?>

        <div class="kunci">

            <strong>Kunci Jawaban</strong>

            <br><br>

            <?php foreach (["A", "B", "C", "D"] as $kode): ?>

                <label style="display:inline-block; margin-right:20px;">

                    <input
                        type="radio"
                        name="kunci"
                        value="<?= $kode ?>"
                        <?= (isset($opsi[$kode]) && $opsi[$kode]["benar"] == 1) ? "checked" : "" ?>
                        required
                    >

                    <?= $kode ?>

                </label>

            <?php endforeach; ?>

        </div>

        <button type="submit" class="btn btn-simpan">
            💾 Simpan Perubahan
        </button>

        <a
            href="soal.php?ujian_id=<?= $ujian_id ?>"
            class="btn btn-kembali"
        >
            Kembali
        </a>

    </form>

</div>

</body>
</html>
<?php

require __DIR__ . "/includes/auth.php";
if (($_SESSION["admin_role"] ?? "") !== "superadmin") {
    header("Location: dashboard.php");
    exit;
}
require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| VARIABEL
|--------------------------------------------------------------------------
*/

$pesan = "";
$tipe_pesan = "";

$edit_id = isset($_GET["edit"])
    ? (int)$_GET["edit"]
    : 0;


/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | TAMBAH
    |--------------------------------------------------------------------------
    */

    if ($aksi === "tambah") {

        $kode = strtoupper(
            trim($_POST["kode"] ?? "")
        );

        $nama = trim(
            $_POST["nama"] ?? ""
        );

        $status = trim(
            $_POST["status"] ?? "aktif"
        );


        if ($kode === "" || $nama === "") {

            $pesan =
                "Kode dan nama mata pelajaran wajib diisi.";

            $tipe_pesan = "error";

        } else {

            /*
            | Cek kode
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM mata_pelajaran
                WHERE kode = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "s",
                $kode
            );

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {

                $pesan =
                    "Kode mata pelajaran sudah digunakan.";

                $tipe_pesan = "error";

                $stmt->close();

            } else {

                $stmt->close();


                /*
                | Simpan
                */

                $stmt = $conn->prepare("
                    INSERT INTO mata_pelajaran
                    (
                        nama,
                        kode,
                        status
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->bind_param(
                    "sss",
                    $nama,
                    $kode,
                    $status
                );


                if ($stmt->execute()) {

                    $pesan =
                        "Mata pelajaran berhasil ditambahkan.";

                    $tipe_pesan = "success";

                } else {

                    $pesan =
                        "Gagal menambahkan mata pelajaran: " .
                        $stmt->error;

                    $tipe_pesan = "error";

                }

                $stmt->close();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    elseif ($aksi === "edit") {

        $id = (int)(
            $_POST["id"] ?? 0
        );

        $kode = strtoupper(
            trim($_POST["kode"] ?? "")
        );

        $nama = trim(
            $_POST["nama"] ?? ""
        );

        $status = trim(
            $_POST["status"] ?? "aktif"
        );


        if (
            $id <= 0 ||
            $kode === "" ||
            $nama === ""
        ) {

            $pesan =
                "Data mata pelajaran belum lengkap.";

            $tipe_pesan = "error";

        } else {

            /*
            | Cek kode milik mapel lain
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM mata_pelajaran
                WHERE kode = ?
                AND id <> ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "si",
                $kode,
                $id
            );

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {

                $pesan =
                    "Kode mata pelajaran sudah digunakan.";

                $tipe_pesan = "error";

                $stmt->close();

            } else {

                $stmt->close();


                /*
                | Update
                */

                $stmt = $conn->prepare("
                    UPDATE mata_pelajaran
                    SET
                        nama = ?,
                        kode = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssi",
                    $nama,
                    $kode,
                    $status,
                    $id
                );


                if ($stmt->execute()) {

                    $pesan =
                        "Mata pelajaran berhasil diperbarui.";

                    $tipe_pesan = "success";

                    $edit_id = 0;

                } else {

                    $pesan =
                        "Gagal memperbarui mata pelajaran: " .
                        $stmt->error;

                    $tipe_pesan = "error";

                }

                $stmt->close();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */

    elseif ($aksi === "hapus") {

        $id = (int)(
            $_POST["id"] ?? 0
        );


        if ($id <= 0) {

            $pesan =
                "ID mata pelajaran tidak valid.";

            $tipe_pesan = "error";

        } else {

            /*
            | Cek apakah sudah dipakai ujian
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM ujian
                WHERE mapel_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $id
            );

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {

                $pesan =
                    "Mata pelajaran tidak dapat dihapus " .
                    "karena sudah digunakan pada ujian.";

                $tipe_pesan = "error";

                $stmt->close();

            } else {

                $stmt->close();


                /*
                | Hapus
                */

                $stmt = $conn->prepare("
                    DELETE FROM mata_pelajaran
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $id
                );


                if ($stmt->execute()) {

                    $pesan =
                        "Mata pelajaran berhasil dihapus.";

                    $tipe_pesan = "success";

                } else {

                    $pesan =
                        "Gagal menghapus mata pelajaran: " .
                        $stmt->error;

                    $tipe_pesan = "error";

                }

                $stmt->close();

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| AMBIL DATA UNTUK EDIT
|--------------------------------------------------------------------------
*/

$data_edit = null;


if ($edit_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            nama,
            kode,
            status
        FROM mata_pelajaran
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $edit_id
    );

    $stmt->execute();

    $result_edit =
        $stmt->get_result();

    $data_edit =
        $result_edit->fetch_assoc();

    $stmt->close();


    if (!$data_edit) {

        $edit_id = 0;

    }

}


/*
|--------------------------------------------------------------------------
| AMBIL SEMUA DATA
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT
        id,
        nama,
        kode,
        status
    FROM mata_pelajaran
    ORDER BY nama ASC
");


if (!$result) {

    die(
        "Gagal mengambil data mata pelajaran: " .
        $conn->error
    );

}


/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

$page_title = "Mata Pelajaran";

$page_description =
    "Kelola data mata pelajaran PIRI CBT";

require __DIR__ . "/includes/header.php";

require __DIR__ . "/includes/sidebar.php";

?>


<style>

/* =========================================================
   MATA PELAJARAN
========================================================= */

.mapel-grid {

    display: grid;

    grid-template-columns:
        minmax(280px, 340px)
        1fr;

    gap: 20px;

}


/* =========================================================
   CARD
========================================================= */

.mapel-card {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 14px
        rgba(15, 23, 42, .05);

}


.mapel-card h2 {

    margin:
        0 0 5px;

    font-size: 18px;

}


.mapel-card p {

    margin:
        0 0 18px;

    color: var(--muted);

    font-size: 13px;

}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 14px;

}


.form-group label {

    display: block;

    margin-bottom: 6px;

    font-size: 13px;

    font-weight: 600;

    color: #334155;

}


.form-group input,
.form-group select {

    width: 100%;

    padding:
        10px 11px;

    border:
        1px solid var(--border);

    border-radius: 8px;

    font-size: 14px;

    outline: none;

    background: white;

}


.form-group input:focus,
.form-group select:focus {

    border-color:
        var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);

}


.form-actions {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    margin-top: 18px;

}


.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding:
        9px 13px;

    border: 0;

    border-radius: 8px;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}


.btn-simpan {

    background:
        var(--primary);

    color: white;

}


.btn-batal {

    background:
        #64748b;

    color: white;

}


/* =========================================================
   PESAN
========================================================= */

.alert {

    margin-bottom: 16px;

    padding:
        11px 13px;

    border-radius: 9px;

    font-size: 13px;

}


.alert-success {

    background:
        #dcfce7;

    color:
        #166534;

}


.alert-error {

    background:
        #fee2e2;

    color:
        #991b1b;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse:
        collapse;

    min-width: 600px;

}


th,
td {

    padding:
        12px 13px;

    border-bottom:
        1px solid var(--border);

    text-align: left;

    font-size: 13px;

}


th {

    background:
        #f8fafc;

    color:
        #334155;

    font-weight: 700;

    white-space:
        nowrap;

}


td {

    color:
        #475569;

}


tbody tr:hover {

    background:
        #f8fafc;

}


tbody tr:last-child td {

    border-bottom:
        0;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    padding:
        5px 10px;

    border-radius:
        20px;

    font-size: 11px;

    font-weight: 700;

}


.status-aktif {

    background:
        #dcfce7;

    color:
        #166534;

}


.status-nonaktif {

    background:
        #fee2e2;

    color:
        #991b1b;

}


/* =========================================================
   AKSI
========================================================= */

.aksi {

    display: flex;

    gap: 5px;

    flex-wrap: wrap;

}


.btn-edit {

    background:
        #2563eb;

    color: white;

}


.btn-hapus {

    background:
        #dc2626;

    color: white;

}


/* =========================================================
   KOSONG
========================================================= */

.kosong {

    text-align: center;

    padding:
        35px 20px;

    color:
        var(--muted);

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 850px) {

    .mapel-grid {

        grid-template-columns:
            1fr;

    }

}

</style>


<main class="main">


    <!-- TOPBAR DESKTOP -->

    <header class="topbar">

        <div class="page-title">

            <h1>
                Mata Pelajaran
            </h1>

            <p>
                Kelola data mata pelajaran PIRI CBT
            </p>

        </div>


        <div class="top-user">

            <div class="top-user-icon">
                👤
            </div>

            <span>
                <?= htmlspecialchars(
                    $_SESSION["admin_nama"]
                    ?? "Administrator"
                ) ?>
            </span>

        </div>

    </header>


    <!-- HEADER MOBILE -->

    <header class="mobile-header">

        <button
            type="button"
            class="menu-button"
            onclick="bukaSidebar()"
        >
            ☰
        </button>

        <div class="mobile-title">
            Mata Pelajaran
        </div>

        <div style="width:40px;"></div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <?php if ($pesan !== ""): ?>

            <div
                class="alert alert-<?= $tipe_pesan === "success"
                    ? "success"
                    : "error"
                ?>"
            >

                <?= htmlspecialchars($pesan) ?>

            </div>

        <?php endif; ?>


        <div class="mapel-grid">


            <!-- =================================================
                 FORM
            ================================================= -->

            <div class="mapel-card">


                <?php if ($data_edit): ?>

                    <h2>
                        ✏️ Edit Mata Pelajaran
                    </h2>

                    <p>
                        Perbarui data mata pelajaran.
                    </p>

                    <form
                        method="POST"
                        action="mata-pelajaran.php"
                    >

                        <input
                            type="hidden"
                            name="aksi"
                            value="edit"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$data_edit["id"] ?>"
                        >


                        <div class="form-group">

                            <label>
                                Kode Mata Pelajaran
                            </label>

                            <input
                                type="text"
                                name="kode"
                                value="<?= htmlspecialchars(
                                    $data_edit["kode"]
                                ) ?>"
                                maxlength="30"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Nama Mata Pelajaran
                            </label>

                            <input
                                type="text"
                                name="nama"
                                value="<?= htmlspecialchars(
                                    $data_edit["nama"]
                                ) ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                            >

                                <option
                                    value="aktif"
                                    <?= $data_edit["status"] === "aktif"
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Aktif
                                </option>

                                <option
                                    value="nonaktif"
                                    <?= $data_edit["status"] === "nonaktif"
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Nonaktif
                                </option>

                            </select>

                        </div>


                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn btn-simpan"
                            >
                                💾 Simpan Perubahan
                            </button>


                            <a
                                href="mata-pelajaran.php"
                                class="btn btn-batal"
                            >
                                Batal
                            </a>

                        </div>

                    </form>


                <?php else: ?>


                    <h2>
                        ➕ Tambah Mata Pelajaran
                    </h2>

                    <p>
                        Tambahkan mata pelajaran baru.
                    </p>


                    <form
                        method="POST"
                        action="mata-pelajaran.php"
                    >

                        <input
                            type="hidden"
                            name="aksi"
                            value="tambah"
                        >


                        <div class="form-group">

                            <label>
                                Kode Mata Pelajaran
                            </label>

                            <input
                                type="text"
                                name="kode"
                                placeholder="Contoh: MTK"
                                maxlength="30"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Nama Mata Pelajaran
                            </label>

                            <input
                                type="text"
                                name="nama"
                                placeholder="Contoh: Matematika"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                            >

                                <option
                                    value="aktif"
                                    selected
                                >
                                    Aktif
                                </option>

                                <option
                                    value="nonaktif"
                                >
                                    Nonaktif
                                </option>

                            </select>

                        </div>


                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn btn-simpan"
                            >
                                ➕ Tambah Mata Pelajaran
                            </button>

                        </div>

                    </form>


                <?php endif; ?>


            </div>


            <!-- =================================================
                 DAFTAR
            ================================================= -->

            <div class="mapel-card">


                <h2>
                    📚 Daftar Mata Pelajaran
                </h2>

                <p>
                    Mata pelajaran yang tersedia di sistem.
                </p>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    No
                                </th>

                                <th>
                                    Kode
                                </th>

                                <th>
                                    Mata Pelajaran
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if ($result->num_rows === 0): ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="kosong"
                                >
                                    Belum ada mata pelajaran.
                                </td>

                            </tr>


                        <?php else: ?>


                            <?php

                            $no = 1;

                            while (
                                $row =
                                $result->fetch_assoc()
                            ):

                            ?>

                                <tr>

                                    <td>
                                        <?= $no++ ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row["kode"]
                                            ) ?>
                                        </strong>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row["nama"]
                                        ) ?>
                                    </td>


                                    <td>

                                        <span
                                            class="status status-<?= htmlspecialchars(
                                                $row["status"]
                                            ) ?>"
                                        >

                                            <?= $row["status"] === "aktif"
                                                ? "AKTIF"
                                                : "NONAKTIF"
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="aksi">

                                            <a
                                                href="mata-pelajaran.php?edit=<?= (int)$row["id"] ?>"
                                                class="btn btn-edit"
                                            >
                                                ✏️ Edit
                                            </a>


                                            <form
                                                method="POST"
                                                action="mata-pelajaran.php"
                                                style="margin:0;"
                                                onsubmit="
                                                    return confirm(
                                                        'Yakin ingin menghapus mata pelajaran ini?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$row["id"] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-hapus"
                                                >
                                                    🗑️ Hapus
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>


                            <?php endwhile; ?>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>


            </div>


        </div>


    </section>

</main>


<?php

require __DIR__ . "/includes/footer.php";

?>
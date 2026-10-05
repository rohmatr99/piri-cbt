<?php

require_once "includes/auth.php";
require_once "../config/database.php";

/* =========================================================
   HANYA SUPERADMIN
========================================================= */

if ($_SESSION["admin_role"] !== "superadmin") {

    http_response_code(403);

    die(
        "Akses ditolak. Halaman ini hanya dapat diakses oleh superadmin."
    );

}

$page_title = "User Admin";
$page_description = "Kelola akun guru dan hak akses mata pelajaran";

$admin_id = (int) $_SESSION["admin_id"];

$pesan = "";
$error = "";


/* =========================================================
   AMBIL SEMUA MATA PELAJARAN
========================================================= */

$resultMapel = $conn->query("
    SELECT id, nama
    FROM mata_pelajaran
    WHERE status = 'aktif'
    ORDER BY nama ASC
");

if (!$resultMapel) {
    die("Gagal mengambil mata pelajaran: " . $conn->error);
}

$daftarMapel = [];

while ($mapel = $resultMapel->fetch_assoc()) {
    $daftarMapel[] = $mapel;
}


/* =========================================================
   PROSES TAMBAH USER
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["aksi"] ?? "") === "tambah"
) {

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "admin";
    $status = $_POST["status"] ?? "aktif";

    $mapel_ids = $_POST["mapel_ids"] ?? [];

    if (!is_array($mapel_ids)) {
        $mapel_ids = [];
    }

    $mapel_ids = array_values(
        array_unique(
            array_filter(
                array_map("intval", $mapel_ids),
                function ($id) {
                    return $id > 0;
                }
            )
        )
    );


    if (
        $nama === "" ||
        $username === "" ||
        $password === ""
    ) {

        $error = "Nama, username, dan password wajib diisi.";

    } elseif (
        !in_array(
            $role,
            ["superadmin", "admin"],
            true
        )
    ) {

        $error = "Role tidak valid.";

    } elseif (
        !in_array(
            $status,
            ["aktif", "nonaktif"],
            true
        )
    ) {

        $error = "Status tidak valid.";

    } elseif (strlen($password) < 6) {

        $error = "Password minimal 6 karakter.";

    } elseif (
        $role === "admin" &&
        count($mapel_ids) === 0
    ) {

        $error = "Guru harus memiliki minimal 1 mata pelajaran.";

    } else {

        /* -------------------------------------------------
           CEK USERNAME
        ------------------------------------------------- */

        $cek = $conn->prepare(
            "SELECT id
             FROM admin_users
             WHERE username = ?
             LIMIT 1"
        );

        $cek->bind_param(
            "s",
            $username
        );

        $cek->execute();

        $hasil_cek = $cek->get_result();


        if ($hasil_cek->num_rows > 0) {

            $error = "Username tersebut sudah digunakan.";

        } else {

            /* -------------------------------------------------
               VALIDASI MAPEL
            ------------------------------------------------- */

            if (
                $role === "admin" &&
                count($mapel_ids) > 0
            ) {

                $placeholders = implode(
                    ",",
                    array_fill(
                        0,
                        count($mapel_ids),
                        "?"
                    )
                );

                $types = str_repeat(
                    "i",
                    count($mapel_ids)
                );

                $sqlMapel = "
                    SELECT COUNT(*) AS jumlah
                    FROM mata_pelajaran
                    WHERE status = 'aktif'
                    AND id IN ($placeholders)
                ";

                $cekMapel = $conn->prepare($sqlMapel);

                $params = [$types];

                foreach ($mapel_ids as $index => $mapel_id) {
                    $params[] = &$mapel_ids[$index];
                }

                call_user_func_array(
                    [$cekMapel, "bind_param"],
                    $params
                );

                $cekMapel->execute();

                $jumlahMapelValid =
                    (int) $cekMapel
                        ->get_result()
                        ->fetch_assoc()["jumlah"];

                if (
                    $jumlahMapelValid !== count($mapel_ids)
                ) {

                    $error =
                        "Terdapat mata pelajaran yang tidak valid.";

                }

            }


            if ($error === "") {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $conn->begin_transaction();

                try {

                    /* -----------------------------------------
                       INSERT USER
                    ----------------------------------------- */

                    $stmt = $conn->prepare(
                        "INSERT INTO admin_users
                        (
                            nama,
                            username,
                            password,
                            role,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "sssss",
                        $nama,
                        $username,
                        $password_hash,
                        $role,
                        $status
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "User admin gagal ditambahkan."
                        );
                    }

                    $new_admin_id =
                        (int) $conn->insert_id;


                    /* -----------------------------------------
                       SIMPAN MAPEL GURU
                    ----------------------------------------- */

                    if (
                        $role === "admin" &&
                        count($mapel_ids) > 0
                    ) {

                        $stmtMapel = $conn->prepare(
                            "INSERT INTO admin_mapel
                            (
                                admin_id,
                                mapel_id
                            )
                            VALUES (?, ?)"
                        );

                        foreach ($mapel_ids as $mapel_id) {

                            $stmtMapel->bind_param(
                                "ii",
                                $new_admin_id,
                                $mapel_id
                            );

                            if (!$stmtMapel->execute()) {
                                throw new Exception(
                                    "Mata pelajaran guru gagal disimpan."
                                );
                            }

                        }

                    }

                    $conn->commit();

                    $pesan =
                        "User admin berhasil ditambahkan.";

                } catch (Exception $e) {

                    $conn->rollback();

                    $error = $e->getMessage();

                }

            }

        }

    }

}


/* =========================================================
   PROSES EDIT USER
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["aksi"] ?? "") === "edit"
) {

    $id = (int) ($_POST["id"] ?? 0);

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "admin";
    $status = $_POST["status"] ?? "aktif";

    $mapel_ids = $_POST["mapel_ids"] ?? [];

    if (!is_array($mapel_ids)) {
        $mapel_ids = [];
    }

    $mapel_ids = array_values(
        array_unique(
            array_filter(
                array_map("intval", $mapel_ids),
                function ($id) {
                    return $id > 0;
                }
            )
        )
    );


    if ($id <= 0) {

        $error = "ID user tidak valid.";

    } elseif (
        $nama === "" ||
        $username === ""
    ) {

        $error = "Nama dan username wajib diisi.";

    } elseif (
        !in_array(
            $role,
            ["superadmin", "admin"],
            true
        )
    ) {

        $error = "Role tidak valid.";

    } elseif (
        !in_array(
            $status,
            ["aktif", "nonaktif"],
            true
        )
    ) {

        $error = "Status tidak valid.";

    } elseif (
        $role === "admin" &&
        count($mapel_ids) === 0
    ) {

        $error =
            "Guru harus memiliki minimal 1 mata pelajaran.";

    } else {

        /* -------------------------------------------------
           CEK USERNAME
        ------------------------------------------------- */

        $cek = $conn->prepare(
            "SELECT id
             FROM admin_users
             WHERE username = ?
             AND id <> ?
             LIMIT 1"
        );

        $cek->bind_param(
            "si",
            $username,
            $id
        );

        $cek->execute();

        $hasil_cek = $cek->get_result();


        if ($hasil_cek->num_rows > 0) {

            $error =
                "Username tersebut sudah digunakan.";

        } else {

            /* -------------------------------------------------
               CEK DIRI SENDIRI
            ------------------------------------------------- */

            if (
                $id === $admin_id &&
                $status !== "aktif"
            ) {

                $error =
                    "Superadmin yang sedang login tidak dapat dinonaktifkan.";

            } else {

                /* -------------------------------------------------
                   VALIDASI MAPEL
                ------------------------------------------------- */

                if (
                    $role === "admin" &&
                    count($mapel_ids) > 0
                ) {

                    $placeholders = implode(
                        ",",
                        array_fill(
                            0,
                            count($mapel_ids),
                            "?"
                        )
                    );

                    $types = str_repeat(
                        "i",
                        count($mapel_ids)
                    );

                    $sqlMapel = "
                        SELECT COUNT(*) AS jumlah
                        FROM mata_pelajaran
                        WHERE status = 'aktif'
                        AND id IN ($placeholders)
                    ";

                    $cekMapel =
                        $conn->prepare($sqlMapel);

                    $params = [$types];

                    foreach (
                        $mapel_ids
                        as $index => $mapel_id
                    ) {

                        $params[] =
                            &$mapel_ids[$index];

                    }

                    call_user_func_array(
                        [$cekMapel, "bind_param"],
                        $params
                    );

                    $cekMapel->execute();

                    $jumlahMapelValid =
                        (int) $cekMapel
                            ->get_result()
                            ->fetch_assoc()["jumlah"];

                    if (
                        $jumlahMapelValid !==
                        count($mapel_ids)
                    ) {

                        $error =
                            "Terdapat mata pelajaran yang tidak valid.";

                    }

                }


                if ($error === "") {

                    $conn->begin_transaction();

                    try {

                        /* -----------------------------------------
                           UPDATE USER
                        ----------------------------------------- */

                        if ($password !== "") {

                            if (strlen($password) < 6) {

                                throw new Exception(
                                    "Password minimal 6 karakter."
                                );

                            }

                            $password_hash =
                                password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );

                            $stmt = $conn->prepare(
                                "UPDATE admin_users
                                 SET
                                    nama = ?,
                                    username = ?,
                                    password = ?,
                                    role = ?,
                                    status = ?
                                 WHERE id = ?"
                            );

                            $stmt->bind_param(
                                "sssssi",
                                $nama,
                                $username,
                                $password_hash,
                                $role,
                                $status,
                                $id
                            );

                        } else {

                            $stmt = $conn->prepare(
                                "UPDATE admin_users
                                 SET
                                    nama = ?,
                                    username = ?,
                                    role = ?,
                                    status = ?
                                 WHERE id = ?"
                            );

                            $stmt->bind_param(
                                "ssssi",
                                $nama,
                                $username,
                                $role,
                                $status,
                                $id
                            );

                        }


                        if (!$stmt->execute()) {

                            throw new Exception(
                                "User admin gagal diperbarui."
                            );

                        }


                        /* -----------------------------------------
                           HAPUS MAPEL LAMA
                        ----------------------------------------- */

                        $hapusMapel =
                            $conn->prepare(
                                "DELETE FROM admin_mapel
                                 WHERE admin_id = ?"
                            );

                        $hapusMapel->bind_param(
                            "i",
                            $id
                        );

                        if (!$hapusMapel->execute()) {

                            throw new Exception(
                                "Data mata pelajaran lama gagal diperbarui."
                            );

                        }


                        /* -----------------------------------------
                           SIMPAN MAPEL BARU
                        ----------------------------------------- */

                        if (
                            $role === "admin" &&
                            count($mapel_ids) > 0
                        ) {

                            $stmtMapel =
                                $conn->prepare(
                                    "INSERT INTO admin_mapel
                                    (
                                        admin_id,
                                        mapel_id
                                    )
                                    VALUES (?, ?)"
                                );

                            foreach (
                                $mapel_ids
                                as $mapel_id
                            ) {

                                $stmtMapel->bind_param(
                                    "ii",
                                    $id,
                                    $mapel_id
                                );

                                if (
                                    !$stmtMapel->execute()
                                ) {

                                    throw new Exception(
                                        "Mata pelajaran guru gagal disimpan."
                                    );

                                }

                            }

                        }


                        $conn->commit();

                        $pesan =
                            "User admin berhasil diperbarui.";

                    } catch (Exception $e) {

                        $conn->rollback();

                        $error =
                            $e->getMessage();

                    }

                }

            }

        }

    }

}


/* =========================================================
   PROSES HAPUS USER
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["aksi"] ?? "") === "hapus"
) {

    $id = (int) ($_POST["id"] ?? 0);


    if ($id <= 0) {

        $error = "ID user tidak valid.";

    } elseif ($id === $admin_id) {

        $error =
            "User yang sedang login tidak dapat dihapus.";

    } else {

        $cek = $conn->prepare(
            "SELECT role, status
             FROM admin_users
             WHERE id = ?
             LIMIT 1"
        );

        $cek->bind_param(
            "i",
            $id
        );

        $cek->execute();

        $hasil_cek =
            $cek->get_result();


        if ($hasil_cek->num_rows === 0) {

            $error =
                "User admin tidak ditemukan.";

        } else {

            $data_user =
                $hasil_cek->fetch_assoc();


            /* ---------------------------------------------
               JANGAN HAPUS SUPERADMIN AKTIF TERAKHIR
            --------------------------------------------- */

            if (
                $data_user["role"] === "superadmin" &&
                $data_user["status"] === "aktif"
            ) {

                $cek_superadmin =
                    $conn->query(
                        "SELECT COUNT(*) AS jumlah
                         FROM admin_users
                         WHERE role = 'superadmin'
                         AND status = 'aktif'"
                    );

                $jumlah_superadmin =
                    (int) $cek_superadmin
                        ->fetch_assoc()["jumlah"];


                if ($jumlah_superadmin <= 1) {

                    $error =
                        "Superadmin aktif terakhir tidak dapat dihapus.";

                }

            }


            if ($error === "") {

                $stmt = $conn->prepare(
                    "DELETE FROM admin_users
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "i",
                    $id
                );


                if ($stmt->execute()) {

                    $pesan =
                        "User admin berhasil dihapus.";

                } else {

                    $error =
                        "User admin gagal dihapus.";

                }

            }

        }

    }

}


/* =========================================================
   AMBIL DATA USER ADMIN
========================================================= */

$result = $conn->query(
    "SELECT
        a.id,
        a.nama,
        a.username,
        a.role,
        a.status,
        a.created_at,

        GROUP_CONCAT(
            DISTINCT mp.nama
            ORDER BY mp.nama ASC
            SEPARATOR ', '
        ) AS mapel_nama

     FROM admin_users a

     LEFT JOIN admin_mapel am
        ON am.admin_id = a.id

     LEFT JOIN mata_pelajaran mp
        ON mp.id = am.mapel_id

     GROUP BY
        a.id,
        a.nama,
        a.username,
        a.role,
        a.status,
        a.created_at

     ORDER BY
        CASE
            WHEN a.role = 'superadmin'
            THEN 0
            ELSE 1
        END,
        a.nama ASC"
);


if (!$result) {
    die("Gagal mengambil data user admin: " . $conn->error);
}


/* =========================================================
   DATA EDIT
========================================================= */

$edit_id = (int) ($_GET["edit"] ?? 0);

$data_edit = null;

$mapel_edit = [];


if ($edit_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            id,
            nama,
            username,
            role,
            status
         FROM admin_users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $edit_id
    );

    $stmt->execute();

    $hasil_edit =
        $stmt->get_result();


    if ($hasil_edit->num_rows > 0) {

        $data_edit =
            $hasil_edit->fetch_assoc();


        /* ---------------------------------------------
           AMBIL MAPEL USER
        --------------------------------------------- */

        $stmtMapelEdit =
            $conn->prepare(
                "SELECT mapel_id
                 FROM admin_mapel
                 WHERE admin_id = ?
                 ORDER BY mapel_id ASC"
            );

        $stmtMapelEdit->bind_param(
            "i",
            $edit_id
        );

        $stmtMapelEdit->execute();

        $hasilMapelEdit =
            $stmtMapelEdit->get_result();


        while (
            $rowMapel =
            $hasilMapelEdit->fetch_assoc()
        ) {

            $mapel_edit[] =
                (int) $rowMapel["mapel_id"];

        }

    }

}


/* =========================================================
   HEADER
========================================================= */

require_once "includes/header.php";
require_once "includes/sidebar.php";

?>

<style>

/* =========================================================
   USER ADMIN
========================================================= */

.admin-wrapper {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
}

.card-header {
    margin-bottom: 20px;
}

.card-header h2 {
    margin: 0;
    font-size: 19px;
    color: var(--text);
}

.card-header p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.info-box {
    padding: 13px 15px;
    border-radius: 10px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    margin-bottom: 20px;
    line-height: 1.6;
    font-size: 13px;
}

.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.5;
}

.alert-success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 20px;
}

.form-group {
    min-width: 0;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.form-group label small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 400;
}

.form-group input,
.form-group select {
    width: 100%;
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #0f172a;
    font-family: inherit;
    font-size: 14px;
    outline: none;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.mapel-box {
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    padding: 12px;
    max-height: 220px;
    overflow-y: auto;
}

.mapel-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.mapel-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 10px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: .15s ease;
}

.mapel-item:hover {
    border-color: #93c5fd;
    background: #eff6ff;
}

.mapel-item input {
    width: 16px;
    height: 16px;
    margin: 0;
    flex-shrink: 0;
    accent-color: var(--primary);
}

.mapel-item span {
    font-size: 13px;
    color: #334155;
}

.mapel-help {
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.mapel-warning {
    margin-top: 8px;
    padding: 9px 10px;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 11px;
    display: none;
}

.button-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 9px 14px;
    border: 0;
    border-radius: 9px;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    transition: .18s ease;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-secondary:hover {
    background: #475569;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th,
td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: middle;
}

th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

td {
    color: #334155;
    font-size: 13px;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-superadmin {
    background: #ede9fe;
    color: #6d28d9;
}

.badge-admin {
    background: #dbeafe;
    color: #1d4ed8;
}

.badge-aktif {
    background: #dcfce7;
    color: #166534;
}

.badge-nonaktif {
    background: #fee2e2;
    color: #991b1b;
}

.mapel-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    min-width: 180px;
}

.mapel-tag {
    display: inline-block;
    padding: 4px 7px;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 600;
}

.semua-mapel {
    color: #7c3aed;
    font-size: 12px;
    font-weight: 700;
}

.tanpa-mapel {
    color: #94a3b8;
    font-size: 12px;
}

.aksi {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.aksi form {
    margin: 0;
}

@media (max-width: 760px) {

    .card {
        padding: 18px;
        border-radius: 14px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .form-group.full {
        grid-column: auto;
    }

    .mapel-list {
        grid-template-columns: 1fr;
    }

    .button-row {
        flex-direction: column;
        align-items: stretch;
    }

    .button-row .btn {
        width: 100%;
    }

}

@media (max-width: 480px) {

    .card {
        padding: 15px;
    }

    .card-header h2 {
        font-size: 17px;
    }

    .info-box {
        font-size: 12px;
    }

}

</style>


<div class="admin-wrapper">

    <?php if ($pesan !== ""): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($pesan) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="info-box">

        <strong>Hak akses:</strong>

        Superadmin memiliki akses ke semua mata pelajaran.

        Akun <strong>Admin/Guru</strong> hanya dapat mengakses
        ujian, soal, dan data terkait mata pelajaran yang diberikan
        kepadanya.

        Guru dapat memiliki satu atau beberapa mata pelajaran.

    </div>


    <!-- =====================================================
         FORM TAMBAH / EDIT
    ====================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                <?= $data_edit
                    ? "✏️ Edit User Admin"
                    : "➕ Tambah User Admin"
                ?>
            </h2>

            <p>
                Kelola akun administrator dan hak akses mata pelajaran.
            </p>

        </div>


        <form
            method="POST"
            action="user-admin.php"
        >

            <input
                type="hidden"
                name="aksi"
                value="<?= $data_edit ? "edit" : "tambah" ?>"
            >


            <?php if ($data_edit): ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $data_edit["id"] ?>"
                >

            <?php endif; ?>


            <div class="form-grid">


                <!-- NAMA -->

                <div class="form-group">

                    <label for="nama">
                        Nama
                    </label>

                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars(
                            $data_edit["nama"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- USERNAME -->

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        required
                        value="<?= htmlspecialchars(
                            $data_edit["username"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">

                        Password

                        <?php if ($data_edit): ?>

                            <small>
                                Kosongkan jika tidak ingin mengubah password.
                            </small>

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="6"
                        <?= $data_edit ? "" : "required" ?>
                    >

                </div>


                <!-- ROLE -->

                <div class="form-group">

                    <label for="role">
                        Role
                    </label>

                    <select
                        id="role"
                        name="role"
                        onchange="aturMapel()"
                    >

                        <option
                            value="admin"
                            <?= (
                                ($data_edit["role"] ?? "admin")
                                === "admin"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Admin / Guru
                        </option>

                        <option
                            value="superadmin"
                            <?= (
                                ($data_edit["role"] ?? "")
                                === "superadmin"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Superadmin
                        </option>

                    </select>

                </div>


                <!-- MAPEL -->

                <div
                    class="form-group full"
                    id="bagian-mapel"
                >

                    <label>
                        Mata Pelajaran Guru
                    </label>

                    <div class="mapel-box">

                        <div class="mapel-list">

                            <?php foreach (
                                $daftarMapel
                                as $mapel
                            ): ?>

                                <label class="mapel-item">

                                    <input
                                        type="checkbox"
                                        name="mapel_ids[]"
                                        value="<?= (int) $mapel["id"] ?>"
                                        <?= in_array(
                                            (int) $mapel["id"],
                                            $mapel_edit,
                                            true
                                        )
                                            ? "checked"
                                            : ""
                                        ?>
                                    >

                                    <span>
                                        <?= htmlspecialchars(
                                            $mapel["nama"]
                                        ) ?>
                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>


                        <?php if (count($daftarMapel) === 0): ?>

                            <div class="mapel-warning" style="display:block;">
                                Belum ada mata pelajaran aktif.
                                Tambahkan mata pelajaran terlebih dahulu.
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="mapel-help">
                        Pilih minimal 1 mata pelajaran untuk akun Guru.
                        Jika guru mengajar beberapa mapel, pilih semuanya.
                    </div>

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="aktif"
                            <?= (
                                ($data_edit["status"] ?? "aktif")
                                === "aktif"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Aktif
                        </option>

                        <option
                            value="nonaktif"
                            <?= (
                                ($data_edit["status"] ?? "")
                                === "nonaktif"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Nonaktif
                        </option>

                    </select>

                </div>


            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $data_edit
                        ? "💾 Simpan Perubahan"
                        : "➕ Tambah Guru"
                    ?>
                </button>


                <?php if ($data_edit): ?>

                    <a
                        href="user-admin.php"
                        class="btn btn-secondary"
                    >
                        Batal
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- =====================================================
         DAFTAR USER
    ====================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                👥 Daftar User Admin / Guru
            </h2>

            <p>
                Daftar akun dan mata pelajaran yang dapat diakses.
            </p>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Nama</th>

                        <th>Username</th>

                        <th>Role</th>

                        <th>Mata Pelajaran</th>

                        <th>Status</th>

                        <th>Dibuat</th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

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
                                        $row["nama"]
                                    ) ?>
                                </strong>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $row["username"]
                                ) ?>
                            </td>


                            <td>

                                <span
                                    class="badge
                                    <?= $row["role"] === "superadmin"
                                        ? "badge-superadmin"
                                        : "badge-admin"
                                    ?>"
                                >

                                    <?= $row["role"] === "superadmin"
                                        ? "Superadmin"
                                        : "Admin / Guru"
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php if (
                                    $row["role"] === "superadmin"
                                ): ?>

                                    <span class="semua-mapel">
                                        Semua Mapel
                                    </span>

                                <?php elseif (
                                    !empty($row["mapel_nama"])
                                ): ?>

                                    <div class="mapel-tags">

                                        <?php

                                        $mapelTampil =
                                            explode(
                                                ", ",
                                                $row["mapel_nama"]
                                            );

                                        foreach (
                                            $mapelTampil
                                            as $namaMapel
                                        ):

                                        ?>

                                            <span class="mapel-tag">
                                                <?= htmlspecialchars(
                                                    $namaMapel
                                                ) ?>
                                            </span>

                                        <?php endforeach; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="tanpa-mapel">
                                        Belum ada mapel
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    class="badge
                                    <?= $row["status"] === "aktif"
                                        ? "badge-aktif"
                                        : "badge-nonaktif"
                                    ?>"
                                >

                                    <?= $row["status"] === "aktif"
                                        ? "Aktif"
                                        : "Nonaktif"
                                    ?>

                                </span>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $row["created_at"]
                                ) ?>
                            </td>


                            <td>

                                <div class="aksi">

                                    <a
                                        href="user-admin.php?edit=<?= (int) $row["id"] ?>"
                                        class="btn btn-warning"
                                    >
                                        Edit
                                    </a>


                                    <?php if (
                                        (int) $row["id"]
                                        !==
                                        $admin_id
                                    ): ?>

                                        <form
                                            method="POST"
                                            action="user-admin.php"
                                            onsubmit="
                                                return confirm(
                                                    'Yakin ingin menghapus user admin ini?'
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
                                                value="<?= (int) $row["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>


<script>

function aturMapel() {

    const role =
        document.getElementById("role").value;

    const bagianMapel =
        document.getElementById("bagian-mapel");

    if (role === "superadmin") {

        bagianMapel.style.display = "none";

    } else {

        bagianMapel.style.display = "block";

    }

}


/* Jalankan saat halaman pertama kali dibuka */
aturMapel();

</script>


<?php require_once "includes/footer.php"; ?>
<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$admin_nama = $_SESSION["admin_nama"] ?? "Administrator";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin — PIRI CBT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --sidebar: #0f172a;
            --sidebar-soft: #1e293b;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #dc2626;
            --success: #16a34a;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family:
                Inter,
                "Segoe UI",
                Arial,
                sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        body {
            display: flex;
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background:
                linear-gradient(
                    180deg,
                    #0f172a 0%,
                    #111827 100%
                );
            color: white;
            padding: 22px 16px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow:
                4px 0 18px rgba(15, 23, 42, .12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 10px 24px;
            border-bottom:
                1px solid rgba(255,255,255,.08);
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow:
                0 8px 18px rgba(37,99,235,.25);
        }

        .brand-text strong {
            display: block;
            font-size: 17px;
            letter-spacing: .3px;
        }

        .brand-text span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 11px;
        }

        .menu-title {
            margin:
                24px 10px 10px;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            transition:
                background .18s ease,
                color .18s ease,
                transform .18s ease;
        }

        .nav a:hover {
            background: var(--sidebar-soft);
            color: white;
            transform: translateX(2px);
        }

        .nav a.active {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );
            color: white;
            box-shadow:
                0 8px 18px rgba(37,99,235,.22);
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 16px;
            border-top:
                1px solid rgba(255,255,255,.08);
        }

        .admin-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 10px;
            background:
                rgba(255,255,255,.05);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .admin-mini strong {
            display: block;
            font-size: 13px;
        }

        .admin-mini span {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 11px;
        }

        .logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 9px;
            background:
                rgba(220,38,38,.12);
            color: #fca5a5;
            text-decoration: none;
            font-size: 13px;
            transition: .18s ease;
        }

        .logout:hover {
            background: #dc2626;
            color: white;
        }

        /* =========================================================
           MAIN
        ========================================================= */

        .main {
            width: 100%;
            min-height: 100vh;
            margin-left: 260px;
        }

        .topbar {
            height: 72px;
            background: white;
            border-bottom:
                1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .page-title h1 {
            margin: 0;
            font-size: 20px;
        }

        .page-title p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .top-user {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 13px;
        }

        .top-user-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #eff6ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            padding: 28px 32px 40px;
            max-width: 1400px;
        }

        /* =========================================================
           WELCOME
        ========================================================= */

        .welcome {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(
                    135deg,
                    #1d4ed8 0%,
                    #2563eb 55%,
                    #4f46e5 100%
                );
            color: white;
            border-radius: 18px;
            padding: 28px 30px;
            box-shadow:
                0 12px 30px rgba(37,99,235,.18);
            margin-bottom: 24px;
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -70px;
            top: -100px;
            border-radius: 50%;
            background:
                rgba(255,255,255,.08);
        }

        .welcome h2 {
            position: relative;
            z-index: 1;
            margin: 0 0 8px;
            font-size: 25px;
        }

        .welcome p {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #dbeafe;
            font-size: 14px;
        }

        /* =========================================================
           SECTION
        ========================================================= */

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin:
                0 0 14px;
        }

        .section-heading h3 {
            margin: 0;
            font-size: 16px;
        }

        .section-heading span {
            color: var(--muted);
            font-size: 12px;
        }

        /* =========================================================
           MENU CARDS
        ========================================================= */

        .menu-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .menu-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            min-height: 138px;
            padding: 21px;
            background: var(--card);
            border:
                1px solid var(--border);
            border-radius: 16px;
            text-decoration: none;
            color: var(--text);
            box-shadow:
                0 4px 14px rgba(15,23,42,.05);
            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .menu-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
        }

        .menu-card:hover {
            transform: translateY(-3px);
            border-color: #bfdbfe;
            box-shadow:
                0 12px 26px rgba(15,23,42,.09);
        }

        .menu-icon {
            flex: 0 0 48px;
            width: 48px;
            height: 48px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: #eff6ff;
        }

        .menu-card h4 {
            margin: 2px 0 7px;
            font-size: 16px;
        }

        .menu-card p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .arrow {
            position: absolute;
            right: 18px;
            bottom: 17px;
            color: #94a3b8;
            font-size: 18px;
        }

        .green::before {
            background: #16a34a;
        }

        .green .menu-icon {
            background: #f0fdf4;
        }

        .purple::before {
            background: #7c3aed;
        }

        .purple .menu-icon {
            background: #f5f3ff;
        }

        .orange::before {
            background: #ea580c;
        }

        .orange .menu-icon {
            background: #fff7ed;
        }

        .red::before {
            background: #dc2626;
        }

        .red .menu-icon {
            background: #fef2f2;
        }

        /* =========================================================
           QUICK INFO
        ========================================================= */

        .info-panel {
            margin-top: 24px;
            background: white;
            border:
                1px solid var(--border);
            border-radius: 16px;
            padding: 20px 22px;
            box-shadow:
                0 4px 14px rgba(15,23,42,.04);
        }

        .info-panel h3 {
            margin: 0 0 7px;
            font-size: 15px;
        }

        .info-panel p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* =========================================================
           MOBILE
        ========================================================= */

        .mobile-header {
            display: none;
        }

        .overlay {
            display: none;
        }

        @media (max-width: 1050px) {

            .menu-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 760px) {

            body {
                display: block;
            }

            .sidebar {
                transform:
                    translateX(-100%);
                transition:
                    transform .22s ease;
            }

            .sidebar.open {
                transform:
                    translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                display: none;
            }

            .mobile-header {
                height: 62px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                background: white;
                border-bottom:
                    1px solid var(--border);
                position: sticky;
                top: 0;
                z-index: 800;
            }

            .mobile-title {
                font-weight: 700;
                font-size: 15px;
            }

            .menu-button {
                width: 40px;
                height: 40px;
                border: 0;
                border-radius: 10px;
                background: #eff6ff;
                color: var(--primary);
                font-size: 20px;
                cursor: pointer;
            }

            .overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                background:
                    rgba(15,23,42,.45);
                z-index: 950;
            }

            .content {
                padding:
                    20px 16px 30px;
            }

            .welcome {
                padding: 22px;
                border-radius: 15px;
            }

            .welcome h2 {
                font-size: 21px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .menu-card {
                min-height: 120px;
            }
        }
    </style>
</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-logo">
            📝
        </div>

        <div class="brand-text">
            <strong>PIRI CBT</strong>
            <span>ADMINISTRATOR PANEL</span>
        </div>

    </div>


    <div class="menu-title">
        Menu Utama
    </div>


    <nav class="nav">

        <a
            href="dashboard.php"
            class="active"
        >
            <span class="nav-icon">🏠</span>
            <span>Dashboard</span>
        </a>


        <a href="siswa.php">
            <span class="nav-icon">👨‍🎓</span>
            <span>Data Siswa</span>
        </a>


        <a href="ujian.php">
            <span class="nav-icon">📝</span>
            <span>Ujian</span>
        </a>


        <a href="bank-soal.php">
            <span class="nav-icon">📚</span>
            <span>Bank Soal</span>
        </a>


        <a href="hasil-ujian.php">
            <span class="nav-icon">📊</span>
            <span>Hasil Ujian</span>
        </a>


        <a href="pelanggaran.php">
            <span class="nav-icon">⚠️</span>
            <span>Pelanggaran Ujian</span>
        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="admin-mini">

            <div class="avatar">
                👤
            </div>

            <div>
                <strong>
                    <?= htmlspecialchars($admin_nama) ?>
                </strong>

                <span>
                    Administrator
                </span>
            </div>

        </div>


        <a
            href="logout.php"
            class="logout"
        >
            🚪 Keluar
        </a>

    </div>

</aside>


<div
    class="overlay"
    id="overlay"
    onclick="tutupSidebar()"
></div>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <header class="topbar">

        <div class="page-title">

            <h1>
                Dashboard Admin
            </h1>

            <p>
                Pusat pengelolaan sistem PIRI CBT
            </p>

        </div>


        <div class="top-user">

            <div class="top-user-icon">
                👤
            </div>

            <span>
                <?= htmlspecialchars($admin_nama) ?>
            </span>

        </div>

    </header>


    <header class="mobile-header">

        <button
            type="button"
            class="menu-button"
            onclick="bukaSidebar()"
        >
            ☰
        </button>

        <div class="mobile-title">
            PIRI CBT — Admin
        </div>

        <div style="width:40px;"></div>

    </header>


    <section class="content">

        <div class="welcome">

            <h2>
                Selamat datang, <?= htmlspecialchars($admin_nama) ?> 👋
            </h2>

            <p>
                Kelola siswa, ujian, bank soal, hasil, dan aktivitas ujian
                dari satu tempat.
            </p>

        </div>


        <div class="section-heading">

            <h3>
                Menu Pengelolaan
            </h3>

            <span>
                Pilih menu yang ingin digunakan
            </span>

        </div>


        <div class="menu-grid">

            <a
                href="siswa.php"
                class="menu-card green"
            >

                <div class="menu-icon">
                    👨‍🎓
                </div>

                <div>

                    <h4>
                        Data Siswa
                    </h4>

                    <p>
                        Kelola data siswa, tambah, edit, hapus,
                        pencarian, filter kelas, dan import data.
                    </p>

                </div>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="ujian.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    📝
                </div>

                <div>

                    <h4>
                        Ujian
                    </h4>

                    <p>
                        Kelola ujian, durasi, minimal waktu,
                        batas pelanggaran, token, dan status ujian.
                    </p>

                </div>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="bank-soal.php"
                class="menu-card purple"
            >

                <div class="menu-icon">
                    📚
                </div>

                <div>

                    <h4>
                        Bank Soal
                    </h4>

                    <p>
                        Kelola kumpulan soal dan gunakan soal
                        untuk berbagai ujian.
                    </p>

                </div>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="hasil-ujian.php"
                class="menu-card orange"
            >

                <div class="menu-icon">
                    📊
                </div>

                <div>

                    <h4>
                        Hasil Ujian
                    </h4>

                    <p>
                        Lihat nilai siswa, detail jawaban,
                        filter hasil, export, cetak, dan reset.
                    </p>

                </div>

                <span class="arrow">
                    →
                </span>

            </a>


            <a
                href="pelanggaran.php"
                class="menu-card red"
            >

                <div class="menu-icon">
                    ⚠️
                </div>

                <div>

                    <h4>
                        Pelanggaran Ujian
                    </h4>

                    <p>
                        Periksa aktivitas pelanggaran siswa
                        selama mengerjakan ujian.
                    </p>

                </div>

                <span class="arrow">
                    →
                </span>

            </a>

        </div>


        <div class="info-panel">

            <h3>
                ℹ️ Informasi
            </h3>

            <p>
                Gunakan menu di sebelah kiri untuk berpindah halaman.
                Pada perangkat HP, menu dapat dibuka melalui tombol ☰.
                Fitur monitoring ujian akan kita tambahkan pada tahap
                pengembangan berikutnya.
            </p>

        </div>

    </section>

</main>


<script>

function bukaSidebar() {

    document
        .getElementById("sidebar")
        .classList
        .add("open");

    document
        .getElementById("overlay")
        .classList
        .add("show");

}


function tutupSidebar() {

    document
        .getElementById("sidebar")
        .classList
        .remove("open");

    document
        .getElementById("overlay")
        .classList
        .remove("show");

}

</script>

</body>
</html>

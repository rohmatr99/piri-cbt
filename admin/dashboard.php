<?php

require __DIR__ . "/includes/auth.php";

$admin_nama = $_SESSION["admin_nama"] ?? "Administrator";

$page_title = "Dashboard Admin";
$page_description = "Pusat pengelolaan sistem PIRI CBT";

require __DIR__ . "/includes/header.php";

require __DIR__ . "/includes/sidebar.php";

?>


<style>

/* =========================================================
   DASHBOARD
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


/* SECTION */

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 14px;
}

.section-heading h3 {
    margin: 0;
    font-size: 16px;
}

.section-heading span {
    color: var(--muted);
    font-size: 12px;
}


/* MENU CARDS */

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


/* VARIASI WARNA */

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


/* INFORMASI */

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


/* RESPONSIVE */

@media (max-width: 1050px) {

    .menu-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 760px) {

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


<main class="main">


    <header class="topbar">

        <div class="page-title">

            <h1>
                <?= htmlspecialchars($page_title) ?>
            </h1>

            <p>
                <?= htmlspecialchars($page_description) ?>
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
                Selamat datang,
                <?= htmlspecialchars($admin_nama) ?>
                👋
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


            <a
                href="monitoring-ujian.php"
                class="menu-card red"
            >

                <div class="menu-icon">
                    📡
                </div>

                <div>

                    <h4>
                        Monitoring Ujian
                    </h4>

                    <p>
                        Pantau siswa yang sedang mengerjakan,
                        progress jawaban, waktu, dan pelanggaran
                        secara langsung.
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
                Gunakan Monitoring Ujian untuk memantau aktivitas siswa
                yang sedang mengerjakan ujian secara langsung.
            </p>

        </div>


    </section>


</main>


<?php

require __DIR__ . "/includes/footer.php";

?>
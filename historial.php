<?php
include('../bd.php');

// ---- Query principal: historial mensual por categoría ----
$sql = "SELECT 
    DATE_FORMAT(fecha_actualizacion, '%Y-%m') AS mes,
    categoria, 
    MAX(total_anterior) AS total_dia 
FROM estadisticas_historial 
GROUP BY mes, categoria 
ORDER BY mes ASC;";

$result = $conexion->query($sql);

$fechas = [];
$categorias = ['animes', 'Series', 'mangas', 'Peliculas'];
$datos = [];
$totales = [];

foreach ($categorias as $cat) {
    $datos[$cat] = [];
    $totales[$cat] = 0;
}

while ($row = $result->fetch_assoc()) {
    $mes       = $row['mes'];
    $categoria = $row['categoria'];
    $total     = (int)$row['total_dia'];

    if (!in_array($mes, $fechas)) {
        $fechas[] = $mes;
    }

    if (isset($datos[$categoria])) {
        $datos[$categoria][$mes] = $total;
        if ($total > $totales[$categoria]) {
            $totales[$categoria] = $total;
        }
    }
}

// Normalizar
foreach ($categorias as $cat) {
    $temp = [];
    foreach ($fechas as $fecha) {
        $temp[] = $datos[$cat][$fecha] ?? 0;
    }
    $datos[$cat] = $temp;
}

// Calcular tendencias (último mes vs penúltimo)
$tendencias = [];
foreach ($categorias as $cat) {
    $arr = $datos[$cat];
    $n   = count($arr);
    if ($n >= 2 && $arr[$n - 2] > 0) {
        $diff = $arr[$n - 1] - $arr[$n - 2];
        $pct  = round(($diff / $arr[$n - 2]) * 100, 1);
        $tendencias[$cat] = ['diff' => $diff, 'pct' => $pct];
    } else {
        $tendencias[$cat] = ['diff' => 0, 'pct' => 0];
    }
}

// Último mes disponible
$ultimoMes = end($fechas);
$totalesUltimoMes = [];
foreach ($categorias as $cat) {
    $arr = $datos[$cat];
    $totalesUltimoMes[$cat] = end($arr) ?: 0;
}
$totalGeneral = array_sum($totalesUltimoMes);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Syne:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        /* ─── RESET & BASE ─────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #080b14;
            --surface: #0d1120;
            --surface2: #111827;
            --border: rgba(255, 255, 255, .06);
            --border-hi: rgba(255, 255, 255, .12);

            --anime: #f97316;
            --series: #3b82f6;
            --manga: #a855f7;
            --pelicula: #ec4899;
            --accent: #22d3ee;

            --text: #e2e8f0;
            --muted: #64748b;
            --subtle: #94a3b8;

            --font-display: 'calibri', sans-serif;
            --font-body: 'Syne', sans-serif;

            --r: 12px;
            --r-lg: 18px;
            --glow-strength: 0 0 30px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 15px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ─── GRID NOISE BACKGROUND ────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(34, 211, 238, .03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(34, 211, 238, .03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ─── LAYOUT ────────────────────────────────────────── */
        .app {
            position: relative;
            z-index: 1;
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px 24px 60px;
        }

        /* ─── HEADER ────────────────────────────────────────── */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: linear-gradient(135deg, #22d3ee, #a855f7);
            display: grid;
            place-items: center;
            font-size: 18px;
            box-shadow: var(--glow-strength) rgba(34, 211, 238, .3);
        }

        .logo h1 {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .1em;
            background: linear-gradient(90deg, #e2e8f0, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo span {
            font-size: 11px;
            color: var(--muted);
            letter-spacing: .15em;
            text-transform: uppercase;
            display: block;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .badge-live {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent);
            font-family: var(--font-display);
        }

        .badge-live::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            animation: pulse-dot 2s ease infinite;
        }

        .header-date {
            font-size: 12px;
            color: var(--muted);
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .5;
                transform: scale(.8);
            }
        }

        /* ─── SECTION TITLE ─────────────────────────────────── */
        .section-label {
            font-family: var(--font-display);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── STAT CARDS ─────────────────────────────────────── */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 22px 24px;
            position: relative;
            overflow: hidden;
            cursor: default;
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-hi);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--card-color, #fff);
            opacity: .8;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--card-color, #fff) 0%, transparent 70%);
            opacity: .06;
        }

        .stat-card[data-cat="animes"] {
            --card-color: var(--anime);
        }

        .stat-card[data-cat="series"] {
            --card-color: var(--series);
        }

        .stat-card[data-cat="manga"] {
            --card-color: var(--manga);
        }

        .stat-card[data-cat="peliculas"] {
            --card-color: var(--pelicula);
        }

        .stat-card[data-cat="total"] {
            --card-color: var(--accent);
        }

        .stat-card:hover {
            box-shadow: var(--glow-strength) color-mix(in srgb, var(--card-color) 20%, transparent);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .card-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: color-mix(in srgb, var(--card-color) 15%, transparent);
            border: 1px solid color-mix(in srgb, var(--card-color) 30%, transparent);
            display: grid;
            place-items: center;
            font-size: 17px;
        }

        .card-badge {
            font-family: var(--font-display);
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: .08em;
        }

        .badge-up {
            background: rgba(34, 197, 94, .15);
            color: #4ade80;
        }

        .badge-down {
            background: rgba(239, 68, 68, .15);
            color: #f87171;
        }

        .badge-flat {
            background: rgba(148, 163, 184, .1);
            color: var(--muted);
        }

        .card-value {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -.02em;
            margin-bottom: 4px;
            line-height: 1;
        }

        .card-label {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: .05em;
        }

        .mini-sparkline {
            margin-top: 16px;
            height: 40px;
        }

        /* ─── CHARTS GRID ────────────────────────────────────── */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .charts-row-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: border-color .25s;
        }

        .chart-card:hover {
            border-color: var(--border-hi);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .chart-title {
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text);
        }

        .chart-subtitle {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        .chart-body {
            width: 100%;
        }

        .tab-group {
            display: flex;
            gap: 4px;
        }

        .tab-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 11px;
            font-family: var(--font-body);
            color: var(--muted);
            cursor: pointer;
            transition: .2s;
            letter-spacing: .05em;
        }

        .tab-btn.active {
            background: color-mix(in srgb, var(--accent) 15%, transparent);
            border-color: var(--accent);
            color: var(--accent);
        }

        .tab-btn:hover:not(.active) {
            border-color: var(--border-hi);
            color: var(--text);
        }

        /* ─── PROGRESS BARS ──────────────────────────────────── */
        .progress-section {
            margin-top: 20px;
        }

        .progress-item {
            margin-bottom: 16px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .progress-name {
            font-size: 12px;
            color: var(--subtle);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .progress-val {
            font-family: var(--font-display);
            font-size: 11px;
            color: var(--text);
        }

        .progress-track {
            height: 4px;
            background: rgba(255, 255, 255, .06);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 2px;
            background: var(--fill-color);
            box-shadow: 0 0 8px var(--fill-color);
            transition: width 1.2s cubic-bezier(.4, 0, .2, 1);
            width: 0%;
        }

        /* ─── RESPONSIVE ─────────────────────────────────────── */
        @media(max-width:900px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:600px) {
            .app {
                padding: 16px 14px 40px;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .cards-grid {
                grid-template-columns: 1fr 1fr;
            }

            .card-value {
                font-size: 26px;
            }
        }

        @media(max-width:400px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ─── ANIMATIONS ──────────────────────────────────────── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp .5s ease both;
        }

        .delay-1 {
            animation-delay: .08s;
        }

        .delay-2 {
            animation-delay: .16s;
        }

        .delay-3 {
            animation-delay: .24s;
        }

        .delay-4 {
            animation-delay: .32s;
        }

        .delay-5 {
            animation-delay: .40s;
        }

        .delay-6 {
            animation-delay: .48s;
        }

        /* ─── SCROLLBAR ───────────────────────────────────────── */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-hi);
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="app">

        <!-- HEADER -->
        <header class="fade-up">
            <div class="logo">
                <div class="logo-icon">📺</div>
                <div>
                    <h1>MediaVault</h1>
                    <span>Dashboard de progreso</span>
                </div>
            </div>
            <div class="header-right">
                <div class="badge-live">En vivo</div>
                <div class="header-date" id="header-date"></div>
            </div>
        </header>

        <!-- STAT CARDS -->
        <div class="section-label fade-up delay-1">Resumen del período</div>
        <div class="cards-grid">

            <?php
            $cardMeta = [
                'animes'   => ['icon' => '🎌', 'label' => 'Episodios Anime',  'cat' => 'animes'],
                'Series'   => ['icon' => '📺', 'label' => 'Episodios Series', 'cat' => 'series'],
                'mangas'   => ['icon' => '📖', 'label' => 'Capítulos Manga',  'cat' => 'manga'],
                'Peliculas' => ['icon' => '🎬', 'label' => 'Películas',        'cat' => 'peliculas'],
            ];
            $delays = ['delay-2', 'delay-3', 'delay-4', 'delay-5'];
            $i = 0;
            foreach ($categorias as $cat):
                $m   = $cardMeta[$cat];
                $val = $totalesUltimoMes[$cat];
                $t   = $tendencias[$cat];
                $pct = $t['pct'];
                $diff = $t['diff'];
                if ($pct > 0) {
                    $badgeClass = 'badge-up';
                    $arrow = '↑';
                } elseif ($pct < 0) {
                    $badgeClass = 'badge-down';
                    $arrow = '↓';
                } else {
                    $badgeClass = 'badge-flat';
                    $arrow = '→';
                }
            ?>
                <div class="stat-card fade-up <?= $delays[$i++] ?>" data-cat="<?= $m['cat'] ?>">
                    <div class="card-top">
                        <div class="card-icon"><?= $m['icon'] ?></div>
                        <div class="card-badge <?= $badgeClass ?>">
                            <?= $arrow ?> <?= abs($pct) ?>%
                        </div>
                    </div>
                    <div class="card-value"><?= number_format($val) ?></div>
                    <div class="card-label"><?= $m['label'] ?></div>
                    <div class="mini-sparkline" id="spark-<?= $m['cat'] ?>"></div>
                </div>
            <?php endforeach; ?>

            <div class="stat-card fade-up delay-6" data-cat="total">
                <div class="card-top">
                    <div class="card-icon">⚡</div>
                    <div class="card-badge badge-up">Total</div>
                </div>
                <div class="card-value"><?= number_format($totalGeneral) ?></div>
                <div class="card-label">Unidades consumidas</div>
                <div class="mini-sparkline" id="spark-total"></div>
            </div>
        </div>

        <!-- MAIN CHART + PIE -->
        <div class="section-label fade-up">Evolución temporal</div>
        <div class="charts-row">

            <div class="chart-card fade-up delay-1">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">Progreso mensual</div>
                        <div class="chart-subtitle">Todas las categorías — escala comparativa</div>
                    </div>
                    <div class="tab-group">
                        <button class="tab-btn active" onclick="switchChart('line',this)">Línea</button>
                        <button class="tab-btn" onclick="switchChart('bar',this)">Barras</button>
                    </div>
                </div>
                <div class="chart-body" id="main-chart" style="height:320px;"></div>
            </div>

            <div class="chart-card fade-up delay-2">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">Distribución</div>
                        <div class="chart-subtitle">Último mes registrado</div>
                    </div>
                </div>
                <div class="chart-body" id="pie-chart" style="height:320px;"></div>

                <div class="progress-section">
                    <?php
                    $catProg = [
                        ['animes', 'Anime', 'var(--anime)'],
                        ['Series', 'Series', 'var(--series)'],
                        ['mangas', 'Manga', 'var(--manga)'],
                        ['Peliculas', 'Películas', 'var(--pelicula)'],
                    ];
                    foreach ($catProg as [$key, $name, $color]):
                        $pct = $totalGeneral > 0 ? round($totalesUltimoMes[$key] / $totalGeneral * 100, 1) : 0;
                    ?>
                        <div class="progress-item">
                            <div class="progress-header">
                                <div class="progress-name">
                                    <span class="progress-dot" style="background:<?= $color ?>;box-shadow:0 0 6px <?= $color ?>"></span>
                                    <?= $name ?>
                                </div>
                                <div class="progress-val"><?= $pct ?>%</div>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="--fill-color:<?= $color ?>;" data-width="<?= $pct ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 4 MINI CHARTS -->
        <div class="section-label fade-up">Detalle por categoría</div>
        <div class="charts-row-3">
            <?php
            $miniCharts = [
                ['chart-animes',  'Anime',     'var(--anime)',   'animes',    '🎌'],
                ['chart-series',  'Series',    'var(--series)',  'Series',    '📺'],
                ['chart-manga',   'Manga',     'var(--manga)',   'mangas',    '📖'],
                ['chart-peliculas', 'Películas', 'var(--pelicula)', 'Peliculas', '🎬'],
            ];
            foreach ($miniCharts as [$id, $title, $color, $key, $icon]):
                $lastVal = end($datos[$key]);
                $trend = $tendencias[$key];
            ?>
                <div class="chart-card fade-up">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><?= $icon ?> <?= $title ?></div>
                            <div class="chart-subtitle">
                                Máx: <?= number_format($totales[$key]) ?> &nbsp;|&nbsp;
                                Último: <?= number_format($lastVal) ?>
                            </div>
                        </div>
                        <?php if ($trend['pct'] != 0): ?>
                            <span style="font-family:var(--font-display);font-size:11px;color:<?= $trend['pct'] > 0 ? '#4ade80' : '#f87171' ?>">
                                <?= $trend['pct'] > 0 ? '↑' : '↓' ?> <?= abs($trend['pct']) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="chart-body" id="<?= $id ?>" style="height:200px;"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- AREA CHART acumulado -->
        <div class="section-label fade-up">Vista global</div>
        <div class="chart-card fade-up" style="margin-bottom:24px;">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Consumo total acumulado</div>
                    <div class="chart-subtitle">Suma de todas las categorías por mes</div>
                </div>
            </div>
            <div class="chart-body" id="area-total" style="height:220px;"></div>
        </div>

    </div><!-- /app -->

    <script>
        // ─── DATA FROM PHP ──────────────────────────────────────
        const fechas = <?php echo json_encode($fechas); ?>;
        const datos = {
            animes: <?php echo json_encode($datos['animes']); ?>,
            series: <?php echo json_encode($datos['Series']); ?>,
            mangas: <?php echo json_encode($datos['mangas']); ?>,
            peliculas: <?php echo json_encode($datos['Peliculas']); ?>
        };
        const colores = {
            animes: '#f97316',
            series: '#3b82f6',
            mangas: '#a855f7',
            peliculas: '#ec4899'
        };
        const totalPorMes = fechas.map((_, i) =>
            (datos.animes[i] || 0) + (datos.series[i] || 0) + (datos.mangas[i] || 0) + (datos.peliculas[i] || 0)
        );

        // ─── ECHARTS THEME DEFAULTS ─────────────────────────────
        const BASE = {
            backgroundColor: 'transparent',
            textStyle: {
                fontFamily: "'Syne',sans-serif",
                color: '#94a3b8'
            }
        };

        function axisStyle() {
            return {
                axisLine: {
                    lineStyle: {
                        color: 'rgba(255,255,255,.08)'
                    }
                },
                axisTick: {
                    show: false
                },
                splitLine: {
                    lineStyle: {
                        color: 'rgba(255,255,255,.04)',
                        type: 'dashed'
                    }
                },
                axisLabel: {
                    color: '#64748b',
                    fontSize: 10
                }
            };
        }

        // ─── HEADER DATE ────────────────────────────────────────
        const now = new Date();
        document.getElementById('header-date').textContent =
            now.toLocaleDateString('es-CL', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

        // ─── MAIN LINE/BAR CHART ────────────────────────────────
        let mainChart = echarts.init(document.getElementById('main-chart'));
        let currentType = 'line';

        function buildMainOption(type) {
            const cats = ['animes', 'series', 'mangas', 'peliculas'];
            const labels = {
                animes: 'Anime',
                series: 'Series',
                mangas: 'Manga',
                peliculas: 'Películas'
            };
            return {
                ...BASE,
                legend: {
                    data: cats.map(c => labels[c]),
                    bottom: 0,
                    textStyle: {
                        color: '#94a3b8',
                        fontSize: 11
                    },
                    icon: 'circle',
                    itemWidth: 8,
                    itemHeight: 8
                },
                tooltip: {
                    trigger: 'axis',
                    backgroundColor: 'rgba(13,17,32,.95)',
                    borderColor: 'rgba(255,255,255,.08)',
                    textStyle: {
                        color: '#e2e8f0',
                        fontFamily: "'Syne',sans-serif"
                    },
                    axisPointer: {
                        lineStyle: {
                            color: 'rgba(34,211,238,.3)'
                        }
                    }
                },
                grid: {
                    left: '2%',
                    right: '2%',
                    bottom: '15%',
                    top: '4%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    data: fechas,
                    ...axisStyle(),
                    axisLabel: {
                        ...axisStyle().axisLabel,
                        rotate: fechas.length > 8 ? 30 : 0
                    }
                },
                yAxis: {
                    type: 'value',
                    ...axisStyle()
                },
                series: cats.map(cat => ({
                    name: labels[cat],
                    type: type,
                    data: datos[cat],
                    smooth: true,
                    color: colores[cat],
                    areaStyle: type === 'line' ? {
                        opacity: .08
                    } : undefined,
                    lineStyle: type === 'line' ? {
                        width: 2.5
                    } : undefined,
                    itemStyle: {
                        borderRadius: type === 'bar' ? [3, 3, 0, 0] : 0
                    },
                    emphasis: {
                        focus: 'series'
                    }
                }))
            };
        }

        mainChart.setOption(buildMainOption('line'));

        function switchChart(type, btn) {
            currentType = type;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            mainChart.setOption(buildMainOption(type), true);
        }

        // ─── PIE CHART ──────────────────────────────────────────
        const pieChart = echarts.init(document.getElementById('pie-chart'));
        const pieVals = [{
                value: <?= $totalesUltimoMes['animes'] ?>,
                name: 'Anime',
                itemStyle: {
                    color: '#f97316'
                }
            },
            {
                value: <?= $totalesUltimoMes['Series'] ?>,
                name: 'Series',
                itemStyle: {
                    color: '#3b82f6'
                }
            },
            {
                value: <?= $totalesUltimoMes['mangas'] ?>,
                name: 'Manga',
                itemStyle: {
                    color: '#a855f7'
                }
            },
            {
                value: <?= $totalesUltimoMes['Peliculas'] ?>,
                name: 'Películas',
                itemStyle: {
                    color: '#ec4899'
                }
            }
        ];
        pieChart.setOption({
            ...BASE,
            tooltip: {
                trigger: 'item',
                backgroundColor: 'rgba(13,17,32,.95)',
                borderColor: 'rgba(255,255,255,.08)',
                textStyle: {
                    color: '#e2e8f0',
                    fontFamily: "'Syne',sans-serif"
                },
                formatter: '{b}: {c} ({d}%)'
            },
            series: [{
                type: 'pie',
                radius: ['40%', '72%'],
                center: ['50%', '50%'],
                data: pieVals,
                label: {
                    show: false
                },
                emphasis: {
                    itemStyle: {
                        shadowBlur: 15,
                        shadowColor: 'rgba(0,0,0,.5)'
                    },
                    label: {
                        show: true,
                        color: '#e2e8f0',
                        fontFamily: "'Orbitron',monospace",
                        fontSize: 13
                    }
                },
                itemStyle: {
                    borderRadius: 6,
                    borderColor: 'rgba(8,11,20,.8)',
                    borderWidth: 2
                }
            }]
        });

        // ─── MINI CHARTS ────────────────────────────────────────
        function crearMiniChart(idElemento, dataSeries, color) {
            const el = document.getElementById(idElemento);
            if (!el) return;
            const c = echarts.init(el);
            c.setOption({
                ...BASE,
                grid: {
                    left: 0,
                    right: 0,
                    top: '6%',
                    bottom: '12%',
                    containLabel: true
                },
                tooltip: {
                    trigger: 'axis',
                    backgroundColor: 'rgba(13,17,32,.95)',
                    borderColor: 'rgba(255,255,255,.08)',
                    textStyle: {
                        color: '#e2e8f0',
                        fontFamily: "'Syne',sans-serif"
                    }
                },
                xAxis: {
                    type: 'category',
                    data: fechas,
                    axisLine: {
                        lineStyle: {
                            color: 'rgba(255,255,255,.06)'
                        }
                    },
                    axisTick: {
                        show: false
                    },
                    axisLabel: {
                        color: '#475569',
                        fontSize: 9,
                        rotate: fechas.length > 8 ? 30 : 0
                    }
                },
                yAxis: {
                    type: 'value',
                    splitLine: {
                        lineStyle: {
                            color: 'rgba(255,255,255,.04)',
                            type: 'dashed'
                        }
                    },
                    axisLabel: {
                        color: '#475569',
                        fontSize: 9
                    },
                    axisLine: {
                        show: false
                    },
                    axisTick: {
                        show: false
                    }
                },
                series: [{
                    type: 'line',
                    smooth: true,
                    data: dataSeries,
                    color: color,
                    lineStyle: {
                        width: 2.5
                    },
                    areaStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 0,
                            y2: 1,
                            colorStops: [{
                                offset: 0,
                                color: color + '55'
                            }, {
                                offset: 1,
                                color: color + '00'
                            }]
                        }
                    },
                    symbol: 'circle',
                    symbolSize: 5,
                    itemStyle: {
                        color: color
                    }
                }]
            });
            return c;
        }

        const miniInstances = [
            crearMiniChart('chart-animes', datos.animes, '#f97316'),
            crearMiniChart('chart-series', datos.series, '#3b82f6'),
            crearMiniChart('chart-manga', datos.mangas, '#a855f7'),
            crearMiniChart('chart-peliculas', datos.peliculas, '#ec4899'),
        ];

        // ─── AREA TOTAL ─────────────────────────────────────────
        const areaTotal = echarts.init(document.getElementById('area-total'));
        areaTotal.setOption({
            ...BASE,
            tooltip: {
                trigger: 'axis',
                backgroundColor: 'rgba(13,17,32,.95)',
                borderColor: 'rgba(255,255,255,.08)',
                textStyle: {
                    color: '#e2e8f0',
                    fontFamily: "'Syne',sans-serif"
                }
            },
            grid: {
                left: '2%',
                right: '2%',
                top: '8%',
                bottom: '12%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: fechas,
                ...axisStyle(),
                axisLabel: {
                    ...axisStyle().axisLabel,
                    rotate: fechas.length > 8 ? 30 : 0
                }
            },
            yAxis: {
                type: 'value',
                ...axisStyle()
            },
            series: [{
                type: 'line',
                smooth: true,
                data: totalPorMes,
                color: '#22d3ee',
                lineStyle: {
                    width: 3
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [{
                            offset: 0,
                            color: '#22d3ee33'
                        }, {
                            offset: 1,
                            color: '#22d3ee00'
                        }]
                    }
                },
                symbol: 'circle',
                symbolSize: 6,
                itemStyle: {
                    color: '#22d3ee',
                    borderColor: '#080b14',
                    borderWidth: 2
                }
            }]
        });

        // ─── SPARKLINES IN CARDS ────────────────────────────────
        function crearSparkline(id, data, color) {
            const el = document.getElementById(id);
            if (!el) return;
            const c = echarts.init(el);
            c.setOption({
                backgroundColor: 'transparent',
                grid: {
                    left: 0,
                    right: 0,
                    top: 0,
                    bottom: 0
                },
                xAxis: {
                    type: 'category',
                    data: fechas,
                    show: false
                },
                yAxis: {
                    type: 'value',
                    show: false
                },
                series: [{
                    type: 'line',
                    smooth: true,
                    data: data,
                    color: color,
                    lineStyle: {
                        width: 2
                    },
                    areaStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 0,
                            y2: 1,
                            colorStops: [{
                                offset: 0,
                                color: color + '40'
                            }, {
                                offset: 1,
                                color: color + '00'
                            }]
                        }
                    },
                    symbol: 'none'
                }]
            });
            return c;
        }

        const sparkInstances = [
            crearSparkline('spark-animes', datos.animes, '#f97316'),
            crearSparkline('spark-series', datos.series, '#3b82f6'),
            crearSparkline('spark-manga', datos.mangas, '#a855f7'),
            crearSparkline('spark-peliculas', datos.peliculas, '#ec4899'),
            crearSparkline('spark-total', totalPorMes, '#22d3ee'),
        ];

        // ─── PROGRESS BAR ANIMATION ─────────────────────────────
        setTimeout(() => {
            document.querySelectorAll('.progress-fill').forEach(el => {
                el.style.width = el.dataset.width + '%';
            });
        }, 600);

        // ─── RESIZE ─────────────────────────────────────────────
        const allCharts = [mainChart, pieChart, areaTotal, ...miniInstances, ...sparkInstances].filter(Boolean);
        window.addEventListener('resize', () => allCharts.forEach(c => c.resize()));
    </script>
</body>

</html>
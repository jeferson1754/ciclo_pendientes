<?php
include('../bd.php');

$consulta = "
SELECT
   -- SERIES: bloques pendientes
    (SELECT 
        SUM(
            CASE 
                WHEN (Total - Vistos) > 0 
                THEN CEIL((Total - Vistos) / 5)
                ELSE 0
            END
        ) AS bloques_series
    FROM series
    WHERE Estado IN ('Pendiente', 'Viendo')
    ) AS series,

    -- MANGAS: hitos pendientes
    (SELECT SUM(CEIL(Faltantes / 50))
     FROM manga
     WHERE Faltantes > 0
    ) AS mangas,

    -- WEBTOONS: hitos pendientes
    (SELECT SUM(CEIL(Faltantes / 50))
     FROM webtoon
     WHERE Faltantes > 0
    ) AS webtoons,

    -- PELÍCULAS: unidades
    (SELECT COUNT(*)
     FROM peliculas
     WHERE Estado IN ('Pendiente','Viendo')
    ) AS peliculas,

    -- ANIMES: temporadas pendientes
    (SELECT COUNT(*)
     FROM anime
     WHERE Estado IN ('Pendiente','Viendo')
    ) AS animes,

    -- MODULO ACTUAL
    CASE
        WHEN EXISTS (SELECT 1 FROM anime WHERE Estado = 'Viendo') THEN 'Animes'
        WHEN EXISTS (SELECT 1 FROM series WHERE Estado = 'Viendo') THEN 'Series'
        WHEN EXISTS (SELECT 1 FROM manga WHERE Estado = 'Viendo') THEN 'Mangas'
        WHEN EXISTS (SELECT 1 FROM webtoon WHERE Estado = 'Viendo') THEN 'Mangas'
        WHEN EXISTS (SELECT 1 FROM peliculas WHERE Estado = 'Viendo') THEN 'Películas'
        ELSE 'Ninguno'
    END AS modulo_actual;
";

function obtenerUltimoTotal($conexion, $categoria, $valor_actual)
{
    $categoria = mysqli_real_escape_string($conexion, $categoria);

    $sql = "
        SELECT total_anterior 
        FROM estadisticas_historial 
        WHERE categoria = '$categoria' AND total_anterior NOT IN ($valor_actual, 0)
        ORDER BY fecha_actualizacion DESC 
        LIMIT 1
    ";

    $result = mysqli_query($conexion, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_row($result)[0];
    }

    return null; // No hay historial aún
}

function obtenerIconoCambio($actual, $anterior)
{
    // Si no hay historial previo
    if ($anterior === null) {
        return '';
    }

    $diferencia = $actual - $anterior;

    // Sin cambio
    if ($diferencia === 0) {
        return "<span style='color:#999; font-size:0.75rem; margin-left:5px;'>—</span>";
    }

    // Progreso (disminuye pendientes)
    if ($diferencia < 0) {
        return "<span style='color:#2ecc71; font-size:0.75rem; font-weight:bold; margin-left:5px;'>
                    <i class='fas fa-arrow-down'></i> " . abs($diferencia) . "
                </span>";
    }

    // Retroceso (aumentan pendientes)
    return "<span style='color:#e74c3c; font-size:0.75rem; font-weight:bold; margin-left:5px;'>
                <i class='fas fa-arrow-up'></i> $diferencia
            </span>";
}




$resultado = mysqli_query($conexion, $consulta);
$datos = mysqli_fetch_assoc($resultado);

// Asignar valores
$series = $datos['series'];
$mangas = $datos['mangas'];
$webtoons = $datos['webtoons'];
$peliculas = $datos['peliculas'];
$animes = $datos['animes'];

$viendo = $datos['modulo_actual'];

$series_anterior     = obtenerUltimoTotal($conexion, 'Series', $series);
$mangas_anterior     = obtenerUltimoTotal($conexion, 'Mangas', $mangas);
$peliculas_anterior  = obtenerUltimoTotal($conexion, 'Películas', $peliculas);
$animes_anterior     = obtenerUltimoTotal($conexion, 'Animes', $animes);

$series_restante    = is_null($series_anterior)    ? 0 : $series - $series_anterior;
$mangas_restante    = is_null($mangas_anterior)    ? 0 : $mangas - $mangas_anterior;
$peliculas_restante = is_null($peliculas_anterior) ? 0 : $peliculas - $peliculas_anterior;
$animes_restante    = is_null($animes_anterior)    ? 0 : $animes - $animes_anterior;

$icono_series = obtenerIconoCambio($series, $series_anterior);
$icono_mangas = obtenerIconoCambio($mangas, $mangas_anterior);
$icono_peliculas = obtenerIconoCambio($peliculas, $peliculas_anterior);
$icono_animes = obtenerIconoCambio($animes, $animes_anterior);


mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciclo de Pendientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --success-color: #4cc9f0;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            color: var(--dark-color);
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
            width: 100%;
        }

        .dashboard-header h1 {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }

        .circle-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            height: 500px;
            margin: 0 auto;
        }

        .circle-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .circle-center {
            background: var(--primary-color);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
            z-index: 10;
        }

        .circle-center .total {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .circle-center .label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .item {
            position: absolute;
            text-align: center;
            width: 120px;
            padding: 1rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 5;
        }

        .item:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 20;
        }

        .item .value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .item .label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .arrow {
            position: absolute;
            font-size: 24px;
            transform-origin: center center;
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        @keyframes pulse {
            0% {
                opacity: 0.7;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.1);
            }

            100% {
                opacity: 0.7;
                transform: scale(1);
            }
        }

        .legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
            max-width: 600px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .responsive-table {
            display: none;
            width: 100%;
            max-width: 600px;
            margin-top: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .circle-container {
                display: none;
            }

            .responsive-table {
                display: block;
            }
        }

        .progress-container {
            width: 100%;
            max-width: 600px;
            margin-top: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .progress-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .actual::after {
            content: "";
            position: absolute;
            top: 3px;
            right: 5px;
            width: 15px;
            height: 15px;
            background-color: limegreen;
            border-radius: 50%;
            border: 2px solid white;
        }

        .color-dot {
            top: -10px;
            font-size: 30px;
            margin-right: -5px;
            position: relative;
            color: limegreen;
        }
    </style>
</head>

<body>
    <div class="dashboard-header">
        <h1>Mi Ciclo de Pendientes</h1>
        <p>Visualiza tus series, películas, mangas y animes pendientes en un círculo interactivo</p>
    </div>

    <div class="circle-container">
        <div class="circle-bg"></div>

        <?php
        // Combinar valores
        $totalMangas = $mangas + $webtoons;
        $totalAnimes = $animes;
        $totalPendientes = $series + $totalMangas + $peliculas + $totalAnimes;

        // Construcción del array
        $pendientes = [];
        if ($series > 0) {
            $pendientes[] = [
                'texto'    => 'Series',
                'label'    => 'Series (bloques)',
                'valor'    => $series,
                'anterior' => $series_anterior,
                'icono'    => obtenerIconoCambio($series, $series_anterior),
                'color'    => '#4361ee',
                'icon'     => 'fa-tv',
                'link'     => '../Series'
            ];
        }

        if ($totalMangas > 0) {
            $pendientes[] = [
                'texto'    => 'Mangas',
                'label'    => 'Mangas (hitos)',
                'valor'    => $totalMangas,
                'anterior' => $mangas_anterior,
                'icono'    => obtenerIconoCambio($totalMangas, $mangas_anterior),
                'color'    => '#7209b7',
                'icon'     => 'fa-book-open',
                'link'     => '../Manga'
            ];
        }

        if ($peliculas > 0) {
            $pendientes[] = [
                'texto'    => 'Películas',
                'label'    => 'Películas',
                'valor'    => $peliculas,
                'anterior' => $peliculas_anterior,
                'icono'    => obtenerIconoCambio($peliculas, $peliculas_anterior),
                'color'    => '#f72585',
                'icon'     => 'fa-film',
                'link'     => '../Anime/peliculas/'
            ];
        }

        if ($totalAnimes > 0) {
            $pendientes[] = [
                'texto'    => 'Animes',
                'label'    => 'Animes',
                'valor'    => $totalAnimes,
                'anterior' => $animes_anterior,
                'icono'    => obtenerIconoCambio($totalAnimes, $animes_anterior),
                'color'    => '#f8961e',
                'icon'     => 'fa-dragon',
                'link'     => '../Anime/Pendientes/'
            ];
        }


        $total = count($pendientes);
        $radius = 180;
        $centerX = 250;
        $centerY = 250;

        $actualKey = array_keys($pendientes, max($pendientes))[0]; // ← esto lo puedes cambiar por tu lógica

        // Mostrar el centro con el total
        echo "<div class='circle-center'>";
        echo "<div class='total'>$totalPendientes</div>";
        echo "<div class='label'>PENDIENTES</div>";
        echo "</div>";

        // Mostrar los ítems con enlaces
        foreach ($pendientes as $index => $item) {
            $angle = (2 * pi() / $total) * $index;
            $x = $centerX + $radius * cos($angle) - 60;
            $y = $centerY + $radius * sin($angle) - 60;

            $isActual = ($item['texto'] == $viendo);
            /*
            echo "<a href='{$item['link']}' class='item' style='left: {$x}px; top: {$y}px; border-top: 4px solid {$item['color']}; text-decoration:none'>";
            echo "<div class='value" . ($isActual ? ' actual' : '') . "' style='color: {$item['color']}'>{$item['valor']}</div>";
            echo "<div class='label'><i class='fas {$item['icon']}' style='margin-right: 5px; color: {$item['color']}'></i>{$item['label']}</div>";

            echo "</a>";
            */

            echo "<a href='{$item['link']}' 
          class='item' 
          style='
              left: {$x}px; 
              top: {$y}px; 
              border-top: 4px solid {$item['color']}; 
              text-decoration:none;
          '>";

            echo "  <div class='value" . ($isActual ? ' actual' : '') . "' 
              style='
                  color: {$item['color']}; 
                  display: flex; 
                  align-items: center; 
                  justify-content: center; 
                  gap: 6px;
              '>
            <span>{$item['valor']}</span>
            {$item['icono']}
        </div>";

            echo "  <div class='label' style='margin-top:4px;'>
            <i class='fas {$item['icon']}' 
               style='margin-right: 6px; color: {$item['color']}'></i>
            {$item['label']}
        </div>";

            echo "</a>";
        }




        // Mostrar flechas
        for ($i = 0; $i < $total; $i++) {
            $nextIndex = ($i + 1) % $total;
            $angleMid = (2 * pi() / $total) * ($i + 0.5); // Punto medio exacto

            // Radio alternado (70px para pares, 50px para impares)
            $arrowRadius = ($i % 2 == 0) ? $radius - 50 : $radius - 50;

            $x = $centerX + $arrowRadius * cos($angleMid) - 15;
            $y = $centerY + $arrowRadius * sin($angleMid) - 15;

            echo "<i class='fas fa-arrow-right arrow' style='left: {$x}px; top: {$y}px; transform: rotate(" . (rad2deg($angleMid) + 90) . "deg); color: {$pendientes[$i]['color']};'></i>";
        }
        ?>
    </div>

    <div class="legend">
        <?php foreach ($pendientes as $item): ?>
            <div class="legend-item">
                <div class="legend-color" style="background: <?= $item['color'] ?>"></div>
                <span><?= $item['label'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="responsive-table">
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Porcentaje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $item):

                    $isActual = ($item['label'] == $viendo); ?>
                    <tr>
                        <td>
                            <a href="<?= $item['link'] ?>" style="color:black;text-decoration:none">
                                <i class="fas <?= $item['icon'] ?>"
                                    style="color: <?= $item['color'] ?>; margin-right: 8px;"></i>
                                <?= $item['label'] ?>
                            </a>
                        </td>

                        <td style="white-space: nowrap;">
                            <strong><?= $item['valor'] ?></strong>

                            <?php if (isset($item['icono'])): ?>
                                <?= $item['icono'] ?>
                            <?php endif; ?>

                            <?php if ($isActual): ?>
                                <span class="color-dot" title="Categoría activa">&bull;</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class=" progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= ($item['valor'] / $totalPendientes * 100) ?>%; background-color: <?= $item['color'] ?>"></div>
                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="progress-container">
        <h5 class="progress-title">Distribución de Pendientes</h5>
        <div class="progress" style="height: 30px; border-radius: 10px; position: relative;">
            <?php
            $previousWidth = 0;
            foreach ($pendientes as $item):
                $width = ($item['valor'] / $totalPendientes * 100);
                $showPercentage = $width >= 10; // Solo mostrar porcentaje si el segmento es suficientemente ancho
            ?>
                <div class="progress-bar" role="progressbar" style="width: <?= $width ?>%; background-color: <?= $item['color'] ?>"
                    aria-valuenow="<?= $item['valor'] ?>" aria-valuemin="0" aria-valuemax="<?= $totalPendientes ?>"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $item['label'] ?>: <?= $item['valor'] ?> (<?= round($width, 1) ?>%)">
                    <?php if ($showPercentage): ?>
                        <span style="position: absolute; color: white; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                            <?= round($width, 1) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <?php foreach ($pendientes as $item): ?>
                <small style="color: <?= $item['color'] ?>; font-weight: 500;">
                    <i class="fas <?= $item['icon'] ?>"></i> <?= round(($item['valor'] / $totalPendientes * 100), 1) ?>%
                </small>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activar tooltips de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Animación para los elementos del círculo
            const items = document.querySelectorAll('.item');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
    </script>
</body>

</html>
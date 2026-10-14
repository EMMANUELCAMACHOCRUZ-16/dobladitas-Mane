<?php
// Se conecta a la base de datos.
require_once __DIR__ . '/conexion.php';

$precioPorPieza = 5;
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreIngresado = $_POST['nombre'] ?? '';
    $nombre = is_string($nombreIngresado) ? trim($nombreIngresado) : '';
    $tipoCliente = $_POST['tipo_cliente'] ?? '';
    $semestreIngresado = $_POST['semestre'] ?? '';
    $cantidadesRecibidas = $_POST['cantidades'] ?? [];
    $semestre = null;
    $cantidades = [];

    if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
        $mensaje = 'Escribe tu nombre (máximo 100 caracteres).';
    } elseif (!in_array($tipoCliente, ['Alumno', 'Maestro'], true)) {
        $mensaje = 'Selecciona si eres alumno(a) o maestro(a).';
    } elseif (
        $tipoCliente === 'Alumno'
        && (!is_string($semestreIngresado)
            || !ctype_digit($semestreIngresado)
            || (int)$semestreIngresado < 1
            || (int)$semestreIngresado > 12)
    ) {
        $mensaje = 'Selecciona un semestre válido.';
    } elseif (!is_array($cantidadesRecibidas)) {
        $mensaje = 'Revisa las cantidades de tu pedido.';
    } else {
        if ($tipoCliente === 'Alumno') {
            $semestre = (int)$semestreIngresado;
        }

        foreach ($cantidadesRecibidas as $idGuisado => $cantidadIngresada) {
            if (
                !is_string($cantidadIngresada)
                || !ctype_digit((string)$idGuisado)
                || !ctype_digit($cantidadIngresada)
            ) {
                $mensaje = 'Revisa las cantidades de tu pedido.';
                break;
            }

            $idGuisado = (int)$idGuisado;
            $cantidad = (int)$cantidadIngresada;
            if ($cantidad < 0 || $cantidad > 99) {
                $mensaje = 'Cada guisado debe tener una cantidad entre 0 y 99.';
                break;
            }
            if ($cantidad > 0) {
                $cantidades[$idGuisado] = $cantidad;
            }
        }

        if ($mensaje === '' && count($cantidades) === 0) {
            $mensaje = 'Agrega al menos una dobladita a tu pedido.';
        }
    }

    if ($mensaje === '') {
        try {
            $resultadoHorario = $conexion->query(
                'SELECT id_horario FROM horarios_servicio WHERE activo = 1 ORDER BY id_horario LIMIT 1'
            );
            $horario = $resultadoHorario->fetch_assoc();

            if (!$horario) {
                $mensaje = 'Por el momento no hay un horario de servicio disponible.';
            } else {
                $idsDisponibles = array_map(
                    'intval',
                    array_column(
                        $conexion->query('SELECT id_guisado FROM guisados')->fetch_all(MYSQLI_ASSOC),
                        'id_guisado'
                    )
                );

                foreach (array_keys($cantidades) as $idGuisado) {
                    if (!in_array($idGuisado, $idsDisponibles, true)) {
                        $mensaje = 'Uno de los guisados seleccionados no está disponible.';
                        break;
                    }
                }
            }

            if ($mensaje === '') {
                $totalPiezas = array_sum($cantidades);
                $totalPrecio = $totalPiezas * $precioPorPieza;
                $conexion->begin_transaction();

                try {
                    // Registra al cliente, el pedido y su detalle en una sola transacción.
                    $insertarCliente = $conexion->prepare(
                        'INSERT INTO clientes (nombre, tipo_cliente, semestre) VALUES (?, ?, ?)'
                    );
                    $insertarCliente->bind_param('ssi', $nombre, $tipoCliente, $semestre);
                    $insertarCliente->execute();
                    $idCliente = $conexion->insert_id;

                    $insertarPedido = $conexion->prepare(
                        'INSERT INTO pedidos (id_cliente, id_horario, total_piezas, total_precio) VALUES (?, ?, ?, ?)'
                    );
                    $idHorario = (int)$horario['id_horario'];
                    $insertarPedido->bind_param('iiid', $idCliente, $idHorario, $totalPiezas, $totalPrecio);
                    $insertarPedido->execute();
                    $idPedido = $conexion->insert_id;

                    $insertarDetalle = $conexion->prepare(
                        'INSERT INTO detalle_pedido (id_pedido, id_guisado, cantidad) VALUES (?, ?, ?)'
                    );
                    foreach ($cantidades as $idGuisado => $cantidad) {
                        $idGuisado = (int)$idGuisado;
                        $insertarDetalle->bind_param('iii', $idPedido, $idGuisado, $cantidad);
                        $insertarDetalle->execute();
                    }

                    $conexion->commit();
                    header('Location: index.php?pedido=' . $idPedido . '#pedido');
                    exit;
                } catch (Throwable $error) {
                    $conexion->rollback();
                    throw $error;
                }
            }
        } catch (Throwable $error) {
            error_log('Error al guardar pedido: ' . $error->getMessage());
            $mensaje = 'No se pudo guardar el pedido. Verifica la conexión a la base de datos e inténtalo de nuevo.';
        }
    }

    $tipoMensaje = 'danger';
}

if (isset($_GET['pedido']) && ctype_digit((string)$_GET['pedido'])) {
    $idPedidoConfirmado = (int)$_GET['pedido'];
    $consultaPedido = $conexion->prepare('SELECT id_pedido FROM pedidos WHERE id_pedido = ?');
    $consultaPedido->bind_param('i', $idPedidoConfirmado);
    $consultaPedido->execute();
    if ($consultaPedido->get_result()->num_rows === 1) {
        $mensaje = '¡Pedido #' . $idPedidoConfirmado . ' registrado! Te esperamos en el edificio C, salón C6.';
        $tipoMensaje = 'success';
    }
}

// Consulta los guisados y el horario activo para mostrarlos en la página.
$resultadoGuisados = $conexion->query(
    'SELECT id_guisado, nombre_guisado FROM guisados ORDER BY id_guisado'
);
$guisados = $resultadoGuisados->fetch_all(MYSQLI_ASSOC);
$resultadoHorario = $conexion->query(
    'SELECT hora_inicio, hora_fin FROM horarios_servicio WHERE activo = 1 ORDER BY id_horario LIMIT 1'
);
$horario = $resultadoHorario->fetch_assoc();
$cantidadesIniciales = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['cantidades'] ?? null)) {
    foreach ($_POST['cantidades'] as $idInicial => $cantidadInicial) {
        if (
            ctype_digit((string)$idInicial)
            && is_string($cantidadInicial)
            && ctype_digit($cantidadInicial)
        ) {
            $cantidadesIniciales[(int)$idInicial] = min(99, (int)$cantidadInicial);
        }
    }
}
$nombreParaMostrar = is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '';
$tipoClienteParaMostrar = is_string($_POST['tipo_cliente'] ?? null) ? $_POST['tipo_cliente'] : '';
$semestreParaMostrar = is_string($_POST['semestre'] ?? null) ? $_POST['semestre'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fffaf2">
    <title>Dobladitas Mané | Haz tu pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js" defer></script>
    <script src="app.js" defer></script>
</head>
<body>
<div id="app">
    <nav class="navbar navbar-expand-lg site-nav">
        <div class="container">
            <a class="navbar-brand brand" href="index.php" aria-label="Dobladitas Mané, inicio">
                <span class="brand-mark" aria-hidden="true">D</span>
                <span>dobladitas <strong>mané</strong></span>
            </a>
            <a class="nav-order-link" href="#pedido">
                Mi pedido <span class="nav-count">{{ totalPiezas }}</span>
            </a>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="container hero-content">
                <div class="hero-copy">
                    <p class="eyebrow"><span></span> HECHAS CON CARIÑO, COMO EN CASA</p>
                    <h1>Un antojito<br>que <em>alegra</em> el día.</h1>
                    <p class="hero-description">Elige tus guisados favoritos y disfruta unas ricas y deliciosas dobladitas en el CBTis.</p>
                    <a class="btn btn-hero" href="#menu">Armar mi pedido <span aria-hidden="true">↓</span></a>
                    <div class="hero-note"><span aria-hidden="true">✳</span> Solo <strong>$5 MXN</strong> cada una <strong>la orden es de 4 piezas</strong></div>
                </div>
                <div class="hero-art" aria-hidden="true">
                    <div class="sun-shape"></div>
                    <div class="plate">
                        <div class="fold fold-one"></div>
                        <div class="fold fold-two"></div>
                        <div class="fold fold-three"></div>
                        <span class="herb herb-one">✳</span>
                        <span class="herb herb-two">✳</span>
                    </div>
                    <span class="art-caption">ricas y baratas</span>
                    <span class="art-sparkle sparkle-one">✳</span>
                    <span class="art-sparkle sparkle-two">✦</span>
                </div>
            </div>
        </section>

        <section id="menu" class="order-section">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">EL MENÚ</p>
                        <h2>¿De qué se te antojan?</h2>
                        <p class="section-subtitle">Agrega tus favoritas. Puedes pedir más de un guisado.</p>
                    </div>
                    <div class="price-pill"><span aria-hidden="true">✳</span> $5 <small>por pieza</small></div>
                </div>

                <?php if ($mensaje !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <?php if ($horario): ?>
                    <div class="service-banner mb-4">
                        <span aria-hidden="true">📍</span>
                        <span>Reparto en el <strong>edificio C, salón C6</strong> del CBTis.
                            horario abierto para realizar pedidos de <?= htmlspecialchars(substr($horario['hora_inicio'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            a <?= htmlspecialchars(substr($horario['hora_fin'], 0, 5), ENT_QUOTES, 'UTF-8') ?>.</span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" role="status">Por el momento no hay un horario de servicio disponible.</div>
                <?php endif; ?>

                <form method="POST" action="index.php#pedido" @submit="actualizarCantidades">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <?php if (count($guisados) > 0): ?>
                                <div class="menu-grid">
                                    <?php foreach ($guisados as $indice => $guisado): ?>
                                        <?php
                                        $idGuisado = (int)$guisado['id_guisado'];
                                        $cantidadInicial = (int)($cantidadesIniciales[$idGuisado] ?? 0);
                                        ?>
                                        <article class="menu-card">
                                            <div class="menu-card-top">
                                                <span class="menu-number"><?= sprintf('%02d', $indice + 1) ?></span>
                                                <span class="dish-icon" aria-hidden="true"><?= ['✿', '◉', '✦', '❋', '☼', '❧', '✳'][$indice % 7] ?></span>
                                            </div>
                                            <h3><?= htmlspecialchars($guisado['nombre_guisado'], ENT_QUOTES, 'UTF-8') ?></h3>
                                            <div class="menu-card-bottom">
                                                <span class="dish-price">$5 <small>/ pieza</small></span>
                                                <div class="quantity-control" aria-label="Seleccionar cantidad">
                                                    <button type="button" @click="cambiarCantidad(<?= $idGuisado ?>, -1)" aria-label="Quitar una pieza">−</button>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="99"
                                                        name="cantidades[<?= $idGuisado ?>]"
                                                        aria-label="Cantidad de <?= htmlspecialchars($guisado['nombre_guisado'], ENT_QUOTES, 'UTF-8') ?>"
                                                        value="<?= $cantidadInicial ?>"
                                                        v-model.number="cantidades[<?= $idGuisado ?>]"
                                                        @input="limitarCantidad(<?= $idGuisado ?>)"
                                                    >
                                                    <button type="button" @click="cambiarCantidad(<?= $idGuisado ?>, 1)" aria-label="Agregar una pieza">+</button>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-light border" role="status">No hay guisados disponibles por el momento.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-5" id="pedido">
                            <aside class="order-card">
                                <div class="order-card-heading">
                                    <div>
                                        <p class="eyebrow">UN ÚLTIMO PASO</p>
                                        <h2>Tu pedido</h2>
                                    </div>
                                    <span class="basket-icon" aria-hidden="true">✳</span>
                                </div>

                                <div v-if="totalPiezas === 0" class="empty-order">
                                    <span aria-hidden="true">🥟</span>
                                    <p>Aún no agregas dobladitas.<br>¡Elige tus guisados favoritos!</p>
                                </div>
                                <ul v-else class="order-items">
                                    <li v-for="item in selectedItems" :key="item.id">
                                        <span>{{ item.nombre }} <small>× {{ item.cantidad }}</small></span>
                                        <strong>{{ money(item.cantidad * precioPorPieza) }}</strong>
                                    </li>
                                </ul>

                                <div class="order-total">
                                    <span>Total <small>({{ totalPiezas }} {{ totalPiezas === 1 ? 'pieza' : 'piezas' }})</small></span>
                                    <strong>{{ money(totalPiezas * precioPorPieza) }}</strong>
                                </div>

                                <div class="mb-3">
                                    <label for="nombre" class="form-label">¿A nombre de quién?</label>
                                    <input id="nombre" name="nombre" class="form-control" type="text" maxlength="100" autocomplete="name" placeholder="Escribe tu nombre" value="<?= htmlspecialchars($nombreParaMostrar, ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="tipo-cliente" class="form-label">Soy</label>
                                    <select id="tipo-cliente" name="tipo_cliente" class="form-select" required @change="cambiarTipoCliente">
                                        <option value="" disabled <?= $tipoClienteParaMostrar === '' ? 'selected' : '' ?>>Selecciona una opción</option>
                                        <option value="Alumno" <?= $tipoClienteParaMostrar === 'Alumno' ? 'selected' : '' ?>>Alumno(a)</option>
                                        <option value="Maestro" <?= $tipoClienteParaMostrar === 'Maestro' ? 'selected' : '' ?>>Maestro(a)</option>
                                    </select>
                                </div>
                                <div class="mb-3" v-show="esAlumno">
                                    <label for="semestre" class="form-label">Semestre</label>
                                    <select id="semestre" name="semestre" class="form-select" :required="esAlumno">
                                        <option value="" disabled <?= $semestreParaMostrar === '' ? 'selected' : '' ?>>Selecciona tu semestre</option>
                                        <?php for ($sem = 1; $sem <= 12; $sem++): ?>
                                            <option value="<?= $sem ?>" <?= $semestreParaMostrar === (string)$sem ? 'selected' : '' ?>><?= $sem ?>° semestre</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <button class="btn btn-submit w-100" type="submit" :disabled="totalPiezas === 0 || <?= $horario ? 'false' : 'true' ?>">
                                    Confirmar pedido <span aria-hidden="true">→</span>
                                </button>
                                <p class="privacy-note">Al confirmar, te esperamos para entregar tu pedido en el salón C6 del edificio C.</p>
                            </aside>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="contact-section">
            <div class="container contact-content">
                <div>
                    <p class="eyebrow">¿TIENES ALGUNA DUDA?</p>
                    <h2>Estoy para ayudarte.</h2>
                    <p>Para aclaraciones sobre tu pedido, mándame un mensaje o llámame.</p>
                </div>
                <div class="contact-actions">
                    <a class="contact-link" href="https://wa.me/524274122420" target="_blank" rel="noopener noreferrer">
                        <span aria-hidden="true">✉</span>
                        <span><small>Escríbeme por WhatsApp</small><strong>427 412 2420</strong></span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a class="contact-link" href="tel:+524274122420">
                        <span aria-hidden="true">☏</span>
                        <span><small>Llámame</small><strong>427 412 2420</strong></span>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container d-flex flex-column flex-sm-row justify-content-between gap-2">
            <span>© <?= date('Y') ?> Dobladitas Mané</span>
            <span>Reparto en edificio C, salón C6 · CBTis</span>
        </div>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.dobladitasConfig = {
        guisados: <?= json_encode(array_map(static function ($guisado) {
            return [
                'id' => (int)$guisado['id_guisado'],
                'nombre' => $guisado['nombre_guisado']
            ];
        }, $guisados), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        cantidades: <?= json_encode(array_map('intval', $cantidadesIniciales), JSON_FORCE_OBJECT) ?>,
        tipoCliente: <?= json_encode($_POST['tipo_cliente'] ?? '') ?>
    };
</script>
</body>
</html>
<?php
// Libera los resultados y cierra la conexión.
$resultadoGuisados->free();
$conexion->close();
?>

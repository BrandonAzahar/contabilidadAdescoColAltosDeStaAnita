<?php
require_once 'config.php';

// Function to get available months/years for filtering
function getAvailableMonthsYears() {
    $conn = getConnection();
    $sql = "SELECT DISTINCT YEAR(date) as year, MONTH(date) as month FROM accounting_entries ORDER BY year DESC, month DESC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_POST) {
    $conn = getConnection();
    
    if (isset($_POST['add_entry'])) {
        // Add new accounting entry
        $date = $_POST['date'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        
        $sql = "INSERT INTO accounting_entries (date, description, entry_type, amount) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$date, $description, $type, $amount]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_entry'])) {
        // Update existing entry
        $id = $_POST['id'];
        $date = $_POST['date'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        
        $sql = "UPDATE accounting_entries SET date=?, description=?, entry_type=?, amount=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$date, $description, $type, $amount, $id]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_entry'])) {
        // Delete entry
        $id = $_POST['id'];
        
        $sql = "DELETE FROM accounting_entries WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle filtering
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Build query based on filters
$where_clause = "";
$params = [];
if ($month > 0 && $year > 0) {
    $where_clause = "WHERE YEAR(date) = ? AND MONTH(date) = ?";
    $params = [$year, $month];
} elseif ($year > 0) {
    $where_clause = "WHERE YEAR(date) = ?";
    $params = [$year];
}

// Get all entries with filtering
$conn = getConnection();
$sql = "SELECT * FROM accounting_entries " . $where_clause . " ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals with filtering
$total_entradas = 0;
$total_salidas = 0;
foreach ($entries as $entry) {
    if ($entry['entry_type'] == 'entrada') {
        $total_entradas += $entry['amount'];
    } else {
        $total_salidas += $entry['amount'];
    }
}
$saldo = $total_entradas - $total_salidas;

// Calculate totals for all records (without filter) to show overall balance
$sql_all = "SELECT * FROM accounting_entries ORDER BY date DESC";
$stmt_all = $conn->query($sql_all);
$all_entries = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$total_entradas_all = 0;
$total_salidas_all = 0;
foreach ($all_entries as $entry) {
    if ($entry['entry_type'] == 'entrada') {
        $total_entradas_all += $entry['amount'];
    } else {
        $total_salidas_all += $entry['amount'];
    }
}
$saldo_all = $total_entradas_all - $total_salidas_all;

// For editing, get the entry to edit if ID is provided in URL
$edit_entry = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM accounting_entries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $edit_entry = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get available months/years for the filter dropdown
$available_months_years = getAvailableMonthsYears();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADESCO - Contabilidad</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 20px;
        }
        .header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 1rem;
        }
        .balance-card {
            background: linear-gradient(135deg, #198754, #157347);
            color: white;
        }
        .entrada {
            color: #198754;
            font-weight: bold;
        }
        .salida {
            color: #dc3545;
            font-weight: bold;
        }
        .table th {
            background-color: #e9ecef;
        }
        .action-btn {
            margin: 0 2px;
            padding: 2px 6px;
        }
        .filter-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-group-responsive {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .form-row-mobile {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 576px) {
            .form-row-mobile {
                flex-direction: column;
            }
            .d-md-flex {
                display: flex !important;
                flex-wrap: wrap;
            }
            .text-md-end {
                text-align: left !important;
            }
            .mt-md-0 {
                margin-top: 0.5rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="h3"><i class="bi bi-currency-dollar"></i> ADESCO - Sistema de Contabilidad</h1>
                    <p class="lead small">Gestión de entradas, salidas y saldo</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="mobile-header mb-3">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtrar por Mes/Año</h5>
            </div>
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-3">
                    <label for="month" class="form-label">Mes</label>
                    <select name="month" id="month" class="form-select">
                        <option value="0">Todos los meses</option>
                        <option value="1" <?php echo ($month == 1) ? 'selected' : ''; ?>>Enero</option>
                        <option value="2" <?php echo ($month == 2) ? 'selected' : ''; ?>>Febrero</option>
                        <option value="3" <?php echo ($month == 3) ? 'selected' : ''; ?>>Marzo</option>
                        <option value="4" <?php echo ($month == 4) ? 'selected' : ''; ?>>Abril</option>
                        <option value="5" <?php echo ($month == 5) ? 'selected' : ''; ?>>Mayo</option>
                        <option value="6" <?php echo ($month == 6) ? 'selected' : ''; ?>>Junio</option>
                        <option value="7" <?php echo ($month == 7) ? 'selected' : ''; ?>>Julio</option>
                        <option value="8" <?php echo ($month == 8) ? 'selected' : ''; ?>>Agosto</option>
                        <option value="9" <?php echo ($month == 9) ? 'selected' : ''; ?>>Septiembre</option>
                        <option value="10" <?php echo ($month == 10) ? 'selected' : ''; ?>>Octubre</option>
                        <option value="11" <?php echo ($month == 11) ? 'selected' : ''; ?>>Noviembre</option>
                        <option value="12" <?php echo ($month == 12) ? 'selected' : ''; ?>>Diciembre</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label for="year" class="form-label">Año</label>
                    <select name="year" id="year" class="form-select">
                        <option value="0">Todos los años</option>
                        <?php
                        $years = [];
                        foreach ($available_months_years as $item) {
                            if (!in_array($item['year'], $years)) {
                                $years[] = $item['year'];
                            }
                        }
                        rsort($years);
                        foreach ($years as $y) {
                            echo '<option value="' . $y . '"';
                            if ($year == $y) echo ' selected';
                            echo '>' . $y . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-6 d-flex align-items-end flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="index.php" class="btn btn-secondary flex-fill">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                    <a href="generate_pdf_simple.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>"
                       class="btn btn-danger flex-fill" target="_blank">
                        <i class="bi bi-file-pdf"></i> PDF
                    </a>
                </div>
            </form>
        </div>

        <!-- Balance Summary -->
        <div class="balance-grid">
            <div class="card balance-card text-center">
                <div class="card-body">
                    <h5 class="card-title">Saldo Total <?php echo ($month > 0 && $year > 0) ? '(' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ')' : ''; ?></h5>
                    <h3 class="h2"><?php echo number_format($saldo, 2); ?> $</h3>
                    <?php if (($month > 0 && $year > 0)): ?>
                        <small class="text-light d-block">Saldo general: <?php echo number_format($saldo_all, 2); ?> $</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Entradas (Ingresos) <?php echo ($month > 0 && $year > 0) ? '(' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ')' : ''; ?></h5>
                    <h3 class="h3 entrada">+<?php echo number_format($total_entradas, 2); ?> $</h3>
                    <?php if (($month > 0 && $year > 0)): ?>
                        <small class="text-muted d-block">Total general: +<?php echo number_format($total_entradas_all, 2); ?> $</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Salidas (Gastos) <?php echo ($month > 0 && $year > 0) ? '(' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ')' : ''; ?></h5>
                    <h3 class="h3 salida">-<?php echo number_format($total_salidas, 2); ?> $</h3>
                    <?php if (($month > 0 && $year > 0)): ?>
                        <small class="text-muted d-block">Total general: -<?php echo number_format($total_salidas_all, 2); ?> $</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form to add or update entries -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="h5 mb-0">
                    <?php echo $edit_entry ? '<i class="bi bi-pencil"></i> Editar Entrada' : '<i class="bi bi-plus-circle"></i> Agregar Nueva Entrada'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo $edit_entry ? $edit_entry['id'] : ''; ?>">

                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label for="date" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="date" name="date"
                                   value="<?php echo $edit_entry ? $edit_entry['date'] : date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-12 col-md-6 col-lg-5">
                            <label for="description" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   placeholder="Descripción de la entrada o salida"
                                   value="<?php echo $edit_entry ? htmlspecialchars($edit_entry['description']) : ''; ?>" required>
                        </div>

                        <div class="col-12 col-md-6 col-lg-2">
                            <label for="type" class="form-label">Tipo</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="entrada" <?php echo ($edit_entry && $edit_entry['entry_type'] == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                                <option value="salida" <?php echo ($edit_entry && $edit_entry['entry_type'] == 'salida') ? 'selected' : ''; ?>>Salida</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-2">
                            <label for="amount" class="form-label">Monto ($)</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                                   placeholder="0.00" min="0"
                                   value="<?php echo $edit_entry ? $edit_entry['amount'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="mt-3 d-grid d-md-block">
                        <?php if ($edit_entry): ?>
                            <button type="submit" name="update_entry" class="btn btn-warning me-2 mb-1">
                                <i class="bi bi-save"></i> Actualizar
                            </button>
                            <a href="index.php<?php echo ($month > 0 || $year > 0) ? '?month=' . $month . '&year=' . $year : ''; ?>" class="btn btn-secondary mb-1">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_entry" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> Agregar Entrada
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Entries Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><i class="bi bi-table"></i> Registro de Entradas y Salidas</h3>
                <span class="badge bg-secondary"><?php echo count($entries); ?> registros<?php echo (($month > 0 || $year > 0) ? ' filtrados' : ''); ?></span>
            </div>
            <div class="card-body">
                <?php if (count($entries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['id']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['date']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                        <td>
                                            <?php if ($entry['entry_type'] == 'entrada'): ?>
                                                <span class="badge bg-success entrada">Entrada</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger salida">Salida</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($entry['entry_type'] == 'entrada'): ?>
                                                <span class="entrada">+<?php echo number_format($entry['amount'], 2); ?> $</span>
                                            <?php else: ?>
                                                <span class="salida">-<?php echo number_format($entry['amount'], 2); ?> $</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <a href="?edit=<?php echo $entry['id']; ?><?php echo ($month > 0 || $year > 0) ? '&month=' . $month . '&year=' . $year : ''; ?>"
                                               class="btn btn-sm btn-outline-primary action-btn">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta entrada?');">
                                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                                <button type="submit" name="delete_entry" class="btn btn-sm btn-outline-danger action-btn">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="text-muted mt-3">No hay registros de entradas o salidas</h4>
                        <p class="text-muted">Agregue su primera entrada para comenzar a gestionar la contabilidad de la ADESCO</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Navigation to Water Payment System -->
        <div class="card mt-4">
            <div class="card-body">
                <h4 class="card-title text-center mb-3">Sistema de Pagos de Agua</h4>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="members.php" class="btn btn-outline-primary flex-fill">
                        <i class="bi bi-people"></i> Socios
                    </a>
                    <a href="water_payments.php" class="btn btn-outline-info flex-fill">
                        <i class="bi bi-water"></i> Pagos
                    </a>
                    <a href="payment_summary.php" class="btn btn-outline-success flex-fill">
                        <i class="bi bi-bar-chart"></i> Resumen
                    </a>
                </div>
                <p class="mt-3 text-muted text-center mb-0">Gestiona los pagos mensuales de agua por cada socio</p>
            </div>
        </div>

        <footer class="mt-5 mb-4 text-center text-muted">
            <p>Sistema de Contabilidad para ADESCO - <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
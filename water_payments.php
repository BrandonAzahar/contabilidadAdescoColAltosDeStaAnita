<?php
require_once 'config.php';

// Handle form submissions for water payments
if ($_POST) {
    $conn = getConnection();
    
    if (isset($_POST['add_payment'])) {
        // Add new water payment
        $member_id = $_POST['member_id'];
        $payment_month = $_POST['payment_month'];
        $payment_year = $_POST['payment_year'];
        $amount = $_POST['amount'];
        $status = $_POST['status'];

        // Check if payment already exists for this member, month, and year
        $check_sql = "SELECT id FROM water_payments WHERE member_id = ? AND payment_month = ? AND payment_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$member_id, $payment_month, $payment_year]);

        if ($check_stmt->rowCount() == 0) {
            $payment_date = $status == 'paid' ? date('Y-m-d') : null;

            $sql = "INSERT INTO water_payments (member_id, payment_month, payment_year, amount, payment_date, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$member_id, $payment_month, $payment_year, $amount, $payment_date, $status]);

            // If payment was added as paid, also create accounting entry
            if ($status == 'paid') {
                $payment_month_name = date('F', mktime(0, 0, 0, $payment_month, 1));

                $sql = "INSERT INTO accounting_entries (date, description, entry_type, amount) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $payment_date,
                    "Pago de agua - {$payment_month_name} {$payment_year} (Socio: {$member_id})",
                    'entrada',
                    $amount
                ]);
            }
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?member_id=" . $member_id);
        exit();
    }
    
    if (isset($_POST['update_payment'])) {
        // Update existing payment
        $id = $_POST['id'];
        $payment_date = $_POST['payment_date'];
        $status = $_POST['status'];

        // Get previous status to check if payment was just made
        $sql = "SELECT status, amount, payment_month, payment_year FROM water_payments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $old_payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update payment date if status is paid and date is not set
        if ($status == 'paid' && empty($payment_date)) {
            $payment_date = date('Y-m-d');
        } elseif ($status == 'pending') {
            $payment_date = null;
        }

        $sql = "UPDATE water_payments SET payment_date=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$payment_date, $status, $id]);

        // If payment was just marked as paid and wasn't already paid, create accounting entry
        if ($old_payment['status'] != 'paid' && $status == 'paid') {
            $payment_month_name = date('F', mktime(0, 0, 0, $old_payment['payment_month'], 1));

            $sql = "INSERT INTO accounting_entries (date, description, entry_type, amount) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $payment_date,
                "Pago de agua - {$payment_month_name} {$old_payment['payment_year']} (Socio: {$member_id})",
                'entrada',
                $old_payment['amount']
            ]);
        }

        // Get member_id for redirect
        $sql = "SELECT member_id FROM water_payments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $member_id = $result['member_id'];

        header("Location: " . $_SERVER['PHP_SELF'] . "?member_id=" . $member_id);
        exit();
    }
    
    if (isset($_POST['delete_payment'])) {
        // Delete payment
        $id = $_POST['id'];
        
        // Get member_id for redirect
        $sql = "SELECT member_id FROM water_payments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $member_id = $result['member_id'];
        
        $sql = "DELETE FROM water_payments WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?member_id=" . $member_id);
        exit();
    }
}

// Get member ID from URL parameter
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$all_members = false;
if ($member_id == 0) {
    $all_members = true;
}

// Get all members for dropdown
$conn = getConnection();
$sql = "SELECT * FROM members ORDER BY name";
$stmt = $conn->query($sql);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get water payments
if ($all_members) {
    $sql = "SELECT wp.*, m.name as member_name 
            FROM water_payments wp 
            JOIN members m ON wp.member_id = m.id 
            ORDER BY m.name, wp.payment_year DESC, wp.payment_month DESC";
} else {
    $sql = "SELECT wp.*, m.name as member_name 
            FROM water_payments wp 
            JOIN members m ON wp.member_id = m.id 
            WHERE wp.member_id = ? 
            ORDER BY wp.payment_year DESC, wp.payment_month DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$member_id]);
}
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific member info if viewing single member
$member_info = null;
if ($member_id > 0) {
    $sql = "SELECT * FROM members WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$member_id]);
    $member_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate payment summary for this member
    $sql = "SELECT 
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(amount) as total_amount
            FROM water_payments WHERE member_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$member_id]);
    $payment_summary = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get available months/years for the form
$sql = "SELECT DISTINCT payment_month, payment_year FROM water_payments ORDER BY payment_year DESC, payment_month DESC";
$stmt = $conn->query($sql);
$available_periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For editing, get the payment to edit if ID is provided in URL
$edit_payment = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM water_payments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $edit_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get member info for the payment being edited
    $sql = "SELECT * FROM members WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$edit_payment['member_id']]);
    $member_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $all_members ? 'ADESCO - Pagos de Agua por Socio' : 'ADESCO - Pagos de Agua de ' . ($member_info ? $member_info['name'] : ''); ?>
    </title>
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
        .paid {
            color: #198754;
            font-weight: bold;
        }
        .pending {
            color: #ffc107;
            font-weight: bold;
        }
        .overdue {
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
        .summary-box {
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 576px) {
            .d-md-flex {
                display: flex !important;
                flex-wrap: wrap;
            }
            .align-items-end {
                align-items: center !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="h3"><i class="bi bi-water"></i> ADESCO - Control de Pagos de Agua</h1>
                    <p class="lead small">
                        <?php echo $all_members ? 'Gestión de pagos de agua por socio' : 'Pagos de agua de ' . htmlspecialchars($member_info['name']); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($member_info && !$all_members): ?>
        <!-- Payment Summary for Single Member -->
        <div class="balance-grid">
            <div class="card summary-box">
                <div class="card-body">
                    <h5 class="card-title">Total Pagado</h5>
                    <h3 class="h3 card-text paid">$<?php echo number_format($payment_summary['total_paid'], 2); ?></h3>
                </div>
            </div>
            <div class="card summary-box">
                <div class="card-body">
                    <h5 class="card-title">Pendiente de Pago</h5>
                    <h3 class="h3 card-text pending">$<?php echo number_format($payment_summary['total_pending'], 2); ?></h3>
                </div>
            </div>
            <div class="card summary-box">
                <div class="card-body">
                    <h5 class="card-title">Total Adeudado</h5>
                    <h3 class="h3 card-text">$<?php echo number_format($payment_summary['total_amount'], 2); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form to add or update water payments -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="h5 mb-0">
                    <?php echo $edit_payment ? '<i class="bi bi-pencil"></i> Editar Pago de Agua' : '<i class="bi bi-plus-circle"></i> Agregar Pago de Agua'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="mb-3 row g-2">
                    <div class="col-12 col-md-6">
                        <label for="member_select" class="form-label">Seleccionar Socio</label>
                        <select name="member_id" id="member_select" class="form-select" onchange="this.form.submit()">
                            <option value="0" <?php echo ($all_members || $member_id == 0) ? 'selected' : ''; ?>>Ver Todos los Socios</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo ($member_id == $member['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <a href="water_payments.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-people"></i> Ver Todos los Pagos
                        </a>
                    </div>
                </form>

                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo $edit_payment ? $edit_payment['id'] : ''; ?>">

                    <?php if (!$edit_payment): ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="member_id" class="form-label">Socio</label>
                            <select class="form-select" id="member_id" name="member_id" required>
                                <option value="">Seleccionar socio</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>"
                                        <?php echo ($member_id == $member['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="payment_month" class="form-label">Mes</label>
                            <select class="form-select" id="payment_month" name="payment_month" required>
                                <option value="">Mes</option>
                                <option value="1" <?php echo (date('n') == 1) ? 'selected' : ''; ?>>Enero</option>
                                <option value="2" <?php echo (date('n') == 2) ? 'selected' : ''; ?>>Febrero</option>
                                <option value="3" <?php echo (date('n') == 3) ? 'selected' : ''; ?>>Marzo</option>
                                <option value="4" <?php echo (date('n') == 4) ? 'selected' : ''; ?>>Abril</option>
                                <option value="5" <?php echo (date('n') == 5) ? 'selected' : ''; ?>>Mayo</option>
                                <option value="6" <?php echo (date('n') == 6) ? 'selected' : ''; ?>>Junio</option>
                                <option value="7" <?php echo (date('n') == 7) ? 'selected' : ''; ?>>Julio</option>
                                <option value="8" <?php echo (date('n') == 8) ? 'selected' : ''; ?>>Agosto</option>
                                <option value="9" <?php echo (date('n') == 9) ? 'selected' : ''; ?>>Septiembre</option>
                                <option value="10" <?php echo (date('n') == 10) ? 'selected' : ''; ?>>Octubre</option>
                                <option value="11" <?php echo (date('n') == 11) ? 'selected' : ''; ?>>Noviembre</option>
                                <option value="12" <?php echo (date('n') == 12) ? 'selected' : ''; ?>>Diciembre</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="payment_year" class="form-label">Año</label>
                            <select class="form-select" id="payment_year" name="payment_year" required>
                                <option value="">Año</option>
                                <?php
                                $current_year = date('Y');
                                for ($i = $current_year - 2; $i <= $current_year + 1; $i++) {
                                    echo '<option value="' . $i . '" ' . (($i == $current_year) ? 'selected' : '') . '>' . $i . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="amount" class="form-label">Monto ($)</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                                   placeholder="0.00" min="0" value="25.00" required>
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" selected>Pendiente</option>
                                <option value="paid">Pagado</option>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo ($edit_payment['status'] == 'pending') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="paid" <?php echo ($edit_payment['status'] == 'paid') ? 'selected' : ''; ?>>Pagado</option>
                                <option value="overdue" <?php echo ($edit_payment['status'] == 'overdue') ? 'selected' : ''; ?>>Atrasado</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="payment_date" class="form-label">Fecha de Pago</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date"
                                   value="<?php echo $edit_payment['payment_date']; ?>">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="payment_info" class="form-label">Información del Pago</label>
                            <input type="text" class="form-control" id="payment_info" value="<?php
                                echo htmlspecialchars($member_info['name']) . ' - ' .
                                date('F', mktime(0, 0, 0, $edit_payment['payment_month'], 1)) . ' ' . $edit_payment['payment_year'] .
                                ' ($' . $edit_payment['amount'] . ')';
                            ?>" readonly>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3 d-grid d-md-block">
                        <?php if ($edit_payment): ?>
                            <button type="submit" name="update_payment" class="btn btn-warning me-2 mb-1">
                                <i class="bi bi-save"></i> Actualizar
                            </button>
                            <a href="water_payments.php?member_id=<?php echo $member_id; ?>" class="btn btn-secondary mb-1">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_payment" class="btn btn-primary me-2 mb-1">
                                <i class="bi bi-plus-lg"></i> Agregar Pago
                            </button>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Inicio
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><i class="bi bi-table"></i>
                    <?php echo $all_members ? 'Pagos de Agua por Todos los Socios' : 'Historial de Pagos de ' . htmlspecialchars($member_info['name']); ?>
                </h3>
                <span class="badge bg-secondary"><?php echo count($payments); ?> pagos</span>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Socio</th>
                                    <th>Mes/Año</th>
                                    <th>Monto</th>
                                    <th>Fecha Pago</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['member_name']); ?></td>
                                        <td><?php echo date('F Y', mktime(0, 0, 0, $payment['payment_month'], 1, $payment['payment_year'])); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo $payment['payment_date'] ? $payment['payment_date'] : '-'; ?></td>
                                        <td>
                                            <?php if ($payment['status'] == 'paid'): ?>
                                                <span class="badge bg-success paid">Pagado</span>
                                            <?php elseif ($payment['status'] == 'pending'): ?>
                                                <span class="badge bg-warning pending">Pendiente</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger overdue">Atrasado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <a href="?edit=<?php echo $payment['id']; ?><?php echo $member_id ? '&member_id=' . $member_id : ''; ?>"
                                               class="btn btn-sm btn-outline-primary action-btn">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('¿Estás seguro de que deseas eliminar este pago?');">
                                                <input type="hidden" name="id" value="<?php echo $payment['id']; ?>">
                                                <button type="submit" name="delete_payment" class="btn btn-sm btn-outline-danger action-btn">
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
                        <i class="bi bi-water" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="text-muted mt-3">
                            <?php echo $all_members ? 'No hay pagos de agua registrados' : 'No hay pagos de agua registrados para ' . htmlspecialchars($member_info['name']); ?>
                        </h4>
                        <p class="text-muted">
                            <?php echo $all_members ?
                                'Agregue pagos de agua para comenzar a gestionarlos por socio' :
                                'Agregue pagos de agua para este socio'; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- PDF Export for Individual Members -->
        <?php if ($member_info && !$all_members): ?>
        <div class="card mt-4">
            <div class="card-body text-center">
                <a href="member_payments_pdf.php?member_id=<?php echo $member_id; ?>" target="_blank" class="btn btn-outline-danger">
                    <i class="bi bi-file-pdf"></i> Exportar Reporte PDF de <?php echo htmlspecialchars($member_info['name']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation and PDF Export -->
        <div class="card mt-4">
            <div class="card-body text-center">
                <a href="index.php" class="btn btn-outline-secondary me-2 mb-2">
                    <i class="bi bi-arrow-left"></i> Volver al Inicio
                </a>
                <a href="members.php" class="btn btn-outline-primary me-2 mb-2">
                    <i class="bi bi-people"></i> Gestión de Socios
                </a>
                <a href="payment_summary.php" class="btn btn-outline-success me-2 mb-2">
                    <i class="bi bi-bar-chart"></i> Resumen de Pagos
                </a>
                <?php if ($all_members): ?>
                <a href="payment_summary_pdf.php" target="_blank" class="btn btn-outline-danger mb-2">
                    <i class="bi bi-file-pdf"></i> Exportar PDF General
                </a>
                <?php endif; ?>
            </div>
        </div>

        <footer class="mt-5 mb-4 text-center text-muted">
            <p>Sistema de Control de Pagos de Agua para ADESCO - <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
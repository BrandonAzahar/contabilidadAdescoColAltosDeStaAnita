<?php
require_once 'config.php';

// Get all members with their payment summaries
$conn = getConnection();

// Query to get members with payment summaries
$sql = "SELECT 
            m.id,
            m.name,
            m.email,
            m.phone,
            m.status,
            COALESCE(SUM(CASE WHEN wp.status = 'paid' THEN wp.amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN wp.status = 'pending' THEN wp.amount ELSE 0 END), 0) AS total_pending,
            COALESCE(COUNT(wp.id), 0) AS total_payments
        FROM members m
        LEFT JOIN water_payments wp ON m.id = wp.member_id
        GROUP BY m.id, m.name, m.email, m.phone, m.status
        ORDER BY m.name";

$stmt = $conn->query($sql);
$members_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall totals
$total_system_paid = 0;
$total_system_pending = 0;
foreach($members_summary as $member) {
    $total_system_paid += $member['total_paid'];
    $total_system_pending += $member['total_pending'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADESCO - Resumen Pagos de Agua</title>
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
        .table th {
            background-color: #e9ecef;
        }
        .action-btn {
            margin: 0 2px;
            padding: 2px 6px;
        }
        .status-active {
            color: #198754;
            font-weight: bold;
        }
        .status-inactive {
            color: #6c757d;
            font-weight: bold;
        }
        .progress {
            height: 20px;
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
            .justify-content-between {
                justify-content: center !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="h3"><i class="bi bi-bar-chart"></i> ADESCO - Resumen de Pagos de Agua</h1>
                    <p class="lead small">Estado de pagos de agua por socio</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Overall System Summary -->
        <div class="balance-grid">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Pagado</h5>
                    <h3 class="h3 paid">$<?php echo number_format($total_system_paid, 2); ?></h3>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Pendiente</h5>
                    <h3 class="h3 pending">$<?php echo number_format($total_system_pending, 2); ?></h3>
                </div>
            </div>
            <div class="card balance-card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total General</h5>
                    <h3 class="h3">$<?php echo number_format($total_system_paid + $total_system_pending, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Members Payment Summary Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><i class="bi bi-people"></i> Estado de Pagos por Socio</h3>
                <span class="badge bg-secondary"><?php echo count($members_summary); ?> socios</span>
            </div>
            <div class="card-body">
                <?php if (count($members_summary) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Socio</th>
                                    <th>Estado</th>
                                    <th>Total Pagado</th>
                                    <th>Total Pendiente</th>
                                    <th>Total Adeudado</th>
                                    <th>Progreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members_summary as $member):
                                    $total_owed = $member['total_paid'] + $member['total_pending'];
                                    $progress_percentage = $total_owed > 0 ? ($member['total_paid'] / $total_owed) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td>
                                            <span class="<?php echo $member['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $member['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="paid">$<?php echo number_format($member['total_paid'], 2); ?></td>
                                        <td class="pending">$<?php echo number_format($member['total_pending'], 2); ?></td>
                                        <td>$<?php echo number_format($total_owed, 2); ?></td>
                                        <td>
                                            <div class="progress" title="Progreso de pago: <?php echo number_format($progress_percentage, 1); ?>%">
                                                <div class="progress-bar
                                                    <?php
                                                        if ($progress_percentage == 100) echo 'bg-success';
                                                        elseif ($progress_percentage > 50) echo 'bg-info';
                                                        else echo 'bg-warning';
                                                    ?>"
                                                     role="progressbar"
                                                     style="width: <?php echo $progress_percentage; ?>%">
                                                    <?php echo number_format($progress_percentage, 0); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <a href="water_payments.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary action-btn" title="Ver/Editar pagos">
                                                <i class="bi bi-list"></i>
                                            </a>
                                            <a href="members.php?edit=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-info action-btn" title="Editar socio">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="text-muted mt-3">No hay socios registrados</h4>
                        <p class="text-muted">Agregue socios para comenzar a gestionar sus pagos de agua</p>
                        <a href="members.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Agregar Socio
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Navigation and PDF Export -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="index.php" class="btn btn-outline-secondary me-2 mb-2">
                    <i class="bi bi-arrow-left"></i> Volver al Inicio
                </a>
                <a href="members.php" class="btn btn-outline-primary me-2 mb-2">
                    <i class="bi bi-people"></i> Gestión de Socios
                </a>
                <a href="water_payments.php" class="btn btn-outline-info me-2 mb-2">
                    <i class="bi bi-water"></i> Control de Pagos
                </a>
                <a href="payment_summary_pdf.php" target="_blank" class="btn btn-outline-danger mb-2">
                    <i class="bi bi-file-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
        
        <footer class="mt-5 mb-4 text-center text-muted">
            <p>Sistema de Resumen de Pagos de Agua para ADESCO - <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
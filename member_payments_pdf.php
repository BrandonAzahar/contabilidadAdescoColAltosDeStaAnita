<?php
require_once 'config.php';

// Get member ID from URL parameter
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($member_id <= 0) {
    die("ID de socio no válido");
}

// Get member information
$conn = getConnection();
$sql = "SELECT * FROM members WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Socio no encontrado");
}

// Get payment history for this member
$sql = "SELECT * FROM water_payments WHERE member_id = ? ORDER BY payment_year DESC, payment_month DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$member_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate payment summary for this member
$sql = "SELECT 
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
            SUM(amount) as total_amount
        FROM water_payments WHERE member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$member_id]);
$payment_summary = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte - Pagos de Agua: <?php echo htmlspecialchars($member['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .member-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .summary-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .paid {
            color: #28a745;
            font-weight: bold;
        }
        .pending {
            color: #ffc107;
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }
        .table th, .table td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
        }
        .status-paid {
            color: #28a745;
            font-weight: bold;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-overdue {
            color: #dc3545;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8em;
            color: #666;
        }
        @media (max-width: 600px) {
            body {
                margin: 10px;
            }
            .table {
                font-size: 10px;
            }
            .table th, .table td {
                padding: 4px;
            }
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte - Pagos de Agua</h1>
        <h2>Socio: <?php echo htmlspecialchars($member['name']); ?></h2>
        <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <div class="member-info">
        <strong>Información del Socio:</strong><br>
        Nombre: <?php echo htmlspecialchars($member['name']); ?><br>
        Email: <?php echo htmlspecialchars($member['email']); ?><br>
        Teléfono: <?php echo htmlspecialchars($member['phone']); ?><br>
        Dirección: <?php echo htmlspecialchars($member['address']); ?><br>
        Estado: <?php echo $member['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>        
    </div>
    
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Total Pagado</div>
            <div class="paid">$<?php echo number_format($payment_summary['total_paid'], 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total Pendiente</div>
            <div class="pending">$<?php echo number_format($payment_summary['total_pending'], 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total Adeudado</div>
            <div>$<?php echo number_format($payment_summary['total_amount'], 2); ?></div>
        </div>
    </div>
    
    <h3>Historial de Pagos</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Mes/Año</th>
                <th>Monto</th>
                <th>Fecha Pago</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?php echo date('F Y', mktime(0, 0, 0, $payment['payment_month'], 1, $payment['payment_year'])); ?></td>
                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                <td><?php echo $payment['payment_date'] ? $payment['payment_date'] : '-'; ?></td>
                <td>
                    <?php 
                    switch ($payment['status']) {
                        case 'paid':
                            echo '<span class="status-paid">Pagado</span>';
                            break;
                        case 'pending':
                            echo '<span class="status-pending">Pendiente</span>';
                            break;
                        case 'overdue':
                            echo '<span class="status-overdue">Atrasado</span>';
                            break;
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($payments) == 0): ?>
            <tr>
                <td colspan="4" style="text-align: center;">No hay pagos registrados para este socio</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Reporte generado por el Sistema de Gestión de ADESCO</p>
        <p>Página <span class="page-number"></span></p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" class="btn btn-primary">Imprimir o Guardar como PDF</button>
        <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
    </div>

    <script>
        // Add page numbers
        document.addEventListener('DOMContentLoaded', function() {
            const pageNumbers = document.querySelectorAll('.page-number');
            pageNumbers.forEach(function(el) {
                el.textContent = document.querySelector('.page-number').closest('body').children.length;
            });
        });
    </script>
</body>
</html>
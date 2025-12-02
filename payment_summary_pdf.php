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
    <title>Reporte - Resumen de Pagos de Agua</title>
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
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #6c757d;
            font-weight: bold;
        }
        .progress-container {
            width: 100%;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            height: 15px;
        }
        .progress-bar {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
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
        <h1>Reporte - Resumen de Pagos de Agua</h1>
        <h2>ADESCO</h2>
        <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Total Pagado</div>
            <div class="paid">$<?php echo number_format($total_system_paid, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total Pendiente</div>
            <div class="pending">$<?php echo number_format($total_system_pending, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total General</div>
            <div>$<?php echo number_format($total_system_paid + $total_system_pending, 2); ?></div>
        </div>
    </div>
    
    <h3>Detalles por Socio</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Socio</th>
                <th>Estado</th>
                <th>Total Pagado</th>
                <th>Total Pendiente</th>
                <th>Total Adeudado</th>
                <th>Progreso</th>
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
                    <div class="progress-container">
                        <div class="progress-bar" 
                             style="width: <?php echo $progress_percentage; ?>%;
                                    background-color: 
                                    <?php 
                                        if ($progress_percentage == 100) echo '#28a745';      // green
                                        elseif ($progress_percentage > 50) echo '#17a2b8';   // blue
                                        else echo '#ffc107';                                // yellow
                                    ?>;">
                            <?php echo number_format($progress_percentage, 0); ?>%
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($members_summary) == 0): ?>
            <tr>
                <td colspan="6" style="text-align: center;">No hay socios registrados</td>
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
Sistema Contable ADESCO – Instrucciones de Configuración 🛠️

✅ Requisitos previos
XAMPP con Apache 🌐 y MySQL 🗄️ instalados y en ejecución
PHP ⚙️ y MySQL 🗄️ agregados a las variables de entorno PATH del sistema
📋 Pasos de configuración
📁 Coloca la carpeta adesco_accounting en el directorio htdocs de XAMPP
Ubicación habitual: C:\xampp\htdocs\
🔽 Inicia el Panel de Control de XAMPP
Haz clic en Iniciar para los servicios:
Apache 🌐
MySQL 🗄️
🗃️ Crea la base de datos
Opción 1: Usar phpMyAdmin 🖥️
Ve a: http://localhost/phpmyadmin
Crea una nueva base de datos llamada adesco_accounting
Importa el archivo create_database.sql 📤
Opción 2: Usar la línea de comandos de MySQL 💻

Abre el Símbolo del sistema como Administrador
Ejecuta:
bash
1
Ingresa tu contraseña de MySQL cuando se te pida (o pulsa Enter si no tienes contraseña) 🔑
🚀 Accede a la aplicación
Abre tu navegador 🌍
Ve a: http://localhost/adesco_accounting/
🚨 Solución de problemas
❌ Error de conexión? → Asegúrate de que MySQL 🗄️ esté en ejecución en XAMPP
📄 Página no carga? → Verifica que Apache 🌐 esté activo
🔄 Problemas con la base de datos? → Revisa el nombre de la BD y las credenciales en config.php ⚙️
✨ Características principales
👀 Ver y gestionar asientos contables (entradas 💰 y salidas 💸)
📊 Cálculo automático del saldo actual
➕ Agregar, ✏️ editar y 🗑️ eliminar asientos
📱 Interfaz responsiva con Bootstrap 🎨

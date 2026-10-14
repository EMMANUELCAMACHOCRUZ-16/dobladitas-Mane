# Dobladitas Mané

## Iniciar en XAMPP

1. Copia la carpeta `dobladitasMane` dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. En phpMyAdmin, crea la base `db_dobladitasmane` e importa `db_dobladitasmane.sql`.
4. Abre `http://localhost/dobladitasMane/`.

La conexión usa por defecto `localhost`, el usuario `root` sin contraseña y la base `db_dobladitasmane`. Si tu instalación usa otros datos, configura las variables de entorno `DB_HOST`, `DB_USER`, `DB_PASSWORD` y `DB_NAME`.

El precio se calcula en el servidor a **$5 MXN por pieza**. El pedido y sus guisados se guardan en `pedidos` y `detalle_pedido`; los datos del cliente se guardan en `clientes`.

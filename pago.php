-- Insertar las empresas de las cuentas existentes en la tabla pivote
INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre)
SELECT id, empresa FROM cuentas_guardadas 
WHERE id NOT IN (SELECT DISTINCT cuenta_id FROM cuentas_guardadas_empresas);
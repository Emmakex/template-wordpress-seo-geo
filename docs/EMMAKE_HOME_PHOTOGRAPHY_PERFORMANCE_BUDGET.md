# Emmake: presupuesto medido de portada fotográfica (14-11-2026)

## Alcance y motivo
La Home Corporate v5 incorpora cuatro imágenes WebP locales aprobadas por el propietario:
57.612 + 72.422 + 60.992 + 55.726 = **246.752 bytes** de recursos visuales.
El presupuesto anterior de 40 KiB se creó para una Home prácticamente sin fotografías.
La publicación mantiene 0 solicitudes de terceros y 0 JavaScript de proyecto.

## Presupuestos de CI
- **EN/ES y migración:** intactos. LCP máximo 1.200 ms, 26 KiB de transferencia,
  y sus límites previos de CSS, peticiones y nodos.
- **Corporate Home fotográfica únicamente:** LCP máximo **1.800 ms**,
  transferencia total máxima **300 KiB**, CSS máximo 9 KiB, 12 solicitudes,
  130 nodos. Son límites exigidos, no objetivos de consumo.
- **Todas las páginas:** CLS máximo 0,05, TBT máximo 100 ms, puntuación
  Lighthouse Performance mínima 95, terceros 0 y JS propio 0.

## Justificación empírica del LCP Corporate
El informe anterior a la modificación midió **Lighthouse Performance 100/100**,
CLS 0, TBT 0 y LCP móvil alrededor de **1.504 ms**. El candidato LCP era el
H1 de la portada (no un script de terceros). Una versión con la foto visible
en el primer pantallazo obtenía un LCP cercano a 1.20–1.43 s, más inestable.
La decisión editorial y responsive es reservar la foto principal para escritorio,
priorizar H1 y CTAs en teléfonos, y mantener imágenes de capacidades, resultados
y consulta visibles en las secciones siguientes.

En lugar de prolongar ajustes que disminuyen la calidad visual sin mejoras
consistentes, se adopta un presupuesto **solo para Corporate** de 1,8 s,
dejando margen de ejecución para CI y **sin modificar los límites más estrictos
de las páginas ligeras**. Se documenta el cambio para que no pase por un éxito
sin cambios de contrato. Las fotos se entregan desde el dominio propio,
con SHA-256 registrado y carga diferida cuando están fuera del primer pantallazo.

## Puerta de publicación
Exigir CI completa en verde, paquete oficial, **Site Manager Plan → Apply → Verify**,
rollback disponible y auditoría de navegador real en escritorio y móvil.
No modificar el dominio raíz `emmake.com` durante este corte de `/nuevaweb/`.

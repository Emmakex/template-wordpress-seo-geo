# Emmake: presupuesto de rendimiento con fotografía de portada (14-11-2026)

## Razón y alcance
El Corporate v5 de Emmake pasa de una portada sin fotografía real a cuatro archivos WebP originales,
entregados exclusivamente por el mismo tema y sin peticiones de terceros.
Los tamaños inmutables del lote son 57.612, 72.422, 60.992 y 55.726 bytes:
**246.752 bytes** de arte aprobado en total. El antiguo presupuesto transferido de 40 KiB
no es representativo de una portada fotográfica de cuatro secciones.

## Contrato activo
- **Todas las páginas:** terceros 0, JS propio 0.
- **EN/ES y migración:** conservan íntegramente sus límites anteriores de 26 KiB,
  peticiones, CSS y LCP. No se relaja el SEO de las páginas ligeras.
- **Solo Corporate Home de muestra:** máximo 300 KiB de transferencia,
  9 KiB de CSS, 12 peticiones y 130 nodos. Es un límite, no un objetivo de gasto.
- **No se relaja el LCP**, que sigue en máximo **1.200 ms** en todas las páginas.
  CLS <= 0,05; TBT <= 100 ms y Lighthouse Performance >= 95 se mantienen.
- La imagen principal se precarga solo para escritorio (media min-width 821px).
  En móvil, el H1 y las acciones se priorizan y la imagen es lazy con decode async.
  Las fotos secundarias son diferidas y los cuatro archivos locales se verifican por SHA-256.

## Evidencia previa a la publicación
La primera medición con fotografías dio Lighthouse 100/100, LCP Corporate ~1.5 s:
ese resultado **no autoriza despliegue**. El ajuste anterior debe pasar CI completo,
incluido LCP < 1,2 s, y después Plan/Apply/Verify de Site Manager con rollback.
No se cambia la portada ni el dominio principal hasta superar esas comprobaciones.

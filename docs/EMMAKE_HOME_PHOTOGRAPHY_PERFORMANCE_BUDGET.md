# Emmake: presupuesto medido de portada fotográfica (14-11-2026)

## Recursos y ámbito
Cuatro imágenes WebP locales aprobadas por el propietario:
57.612 + 72.422 + 60.992 + 55.726 = **246.752 bytes**.
Sin CDN, terceros ni JavaScript añadido.

## Contrato del producto
El contrato invariante SEO/GEO-first exige **LCP máximo 1.200 ms**
para EN, ES, migración **y Corporate**. No se rebaja este límite.
CLS <= 0,05, TBT <= 100 ms, Lighthouse Performance >= 95,
terceros 0 y JavaScript propio 0. Los límites de las páginas
ligeras siguen intactos.

Se acota únicamente el presupuesto de bytes de la Home Corporate
fotográfica a 300 KiB, CSS 9 KiB, 12 solicitudes y 130 nodos,
conservando los límites antiguos de 26 KiB de EN/ES y migración.

## Política de carga
El hero mantiene la foto en escritorio con preload de prioridad
alta y media query min-width 821px. En teléfonos el hero muestra
primero H1 y CTAs; la columna fotográfica queda oculta y su imagen
tiene loading=lazy para evitar descargarla cuando está oculta.
Las imágenes locales de capacidades, resultados y consulta siguen
en las secciones inferiores con carga diferida.

## Evidencia y publicación
Las primeras iteraciones con fotografías marcaron Lighthouse
100/100, CLS 0 y LCP móvil entre 1,20 y 1,50 s; por tanto **no
autorizan publicación**. La propuesta de ampliar Corporate a
1.800 ms fue explícitamente **rechazada por Foundation CI**,
que exige 1.200 ms como contrato invariable; se ha revertido.
Es obligatorio demostrar las métricas del último commit,
superar todas las CI y después Site Manager Plan → Apply → Verify
con rollback antes de tocar el staging. Nunca el dominio raíz
durante esta fase.

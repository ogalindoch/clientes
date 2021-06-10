# EUROGLAS clientes
## Parte del servidor REST de EUROGLAS

Accede y actualiza la información de los clientes en la BD del RING.

 Parte del servidor REST de EUROGLAS

## URLs

| Metodo | URL | Descripción   |
|---|---|---|
| GET | /clientes | Lista de Clientes |
| GET | /clientes/[a:empresa]/ | Lista de clientes de una empresa |
| GET | /clientes/[a:empresa]/[:numCre] | Lista cliente |
| POST | /clientes | Importa Clientes |
| POST | /clientes/movimientos | Importa movimientos de  Clientes |
| | | |
| GET | /contactos | Lista de contactos |
| GET | /contactos/[a:empresa]/ | Lista de contactos de una empresa |
| GET | /contactos/[a:empresa]/[:numCte] | Lista de contactos de un cliente |
| POST | /contactos | Importa contactos |
| | | |

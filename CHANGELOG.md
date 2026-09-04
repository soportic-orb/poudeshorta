# Historial de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/) i el
versionatge és [semàntic](https://semver.org/lang/ca/).

Quan publiqueu una versió nova, actualitzeu aquest fitxer **i el fitxer
`VERSION`**: el Panell de Gestió mostra el contingut de `VERSION` a
Actualitzacions i a la barra lateral.

---

## [1.1.0] — 2026-09-04

### Afegit

- Botó **Diagnosticar la connexió** a Actualitzacions, que comprova un per un
  el repositori, el token, l'extensió `zip`, el mètode d'actualització, els
  permisos d'escriptura, l'espai lliure, l'accés real a GitHub i l'existència
  de la branca.
- Botó **Generar un passi de prova** a la configuració del wallet, per validar
  el certificat d'Apple sense haver de vendre cap entrada.
- Conversió automàtica dels certificats `.p12` en pujar-los, amb suport per als
  que exporta el Keychain del Mac amb algorismes antics.
- Guia pas a pas d'Apple Wallet i de Google Wallet a `DEPLOY.md`.

### Corregit

- **Apple Wallet**: la signatura del passi s'extreia malament del missatge
  S/MIME que retorna OpenSSL, de manera que al `.pkpass` hi anaven 20 bytes en
  comptes de la signatura PKCS#7. Cap passi hauria funcionat.
- **Actualitzacions OTA**: el nom de la branca s'inseria codificat dins de
  l'URL, i les branques amb barra (`equip/funcio`) feien que GitHub respongués
  amb un 422.
- **Actualitzacions OTA**: en un desplegament sense `.git` no es registrava la
  revisió instal·lada i el panell deia sempre que hi havia una actualització
  disponible.
- Els errors de GitHub es distingeixen: token caducat, accés denegat, límit de
  consultes exhaurit, branca inexistent i caigudes del servei.
- El domini que es mostra a les entrades, els correus i el peu del web es
  deriva de `base_url` en comptes d'estar escrit al codi.
- Les inscripcions gratuïtes no rebien el correu amb les entrades.

---

## [1.0.0] — 2026-09-04

Primera versió de la plataforma.

### Afegit

- Web públic amb la informació de l'esdeveniment i els tipus d'inscripció.
- Pagament amb targeta a través de Stripe Checkout, amb webhook signat.
- Pantalla de compra correcta amb confeti i botons per descarregar el PDF,
  afegir el passi al wallet i enviar l'entrada per correu.
- Consulta i anul·lació d'entrades introduint el correu electrònic.
- Entrada en PDF amb codi QR, pensada per imprimir i per llegir al mòbil.
- Panell de Gestió: resum, tipus d'inscripció, llistat d'inscripcions amb
  filtres i exportació a PDF i CSV, anul·lacions amb devolució, comunicats per
  correu, control d'accés amb QR i configuració completa.
- Actualitzacions OTA des de GitHub amb còpia de seguretat i restauració.
- Instal·lador web.

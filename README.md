# Inscripcions · Sopar de germanor del carrer Pou de s'Horta

Plataforma web d'inscripcions i venda d'entrades per a esdeveniments populars,
feta en **PHP 8.1+ i MySQL** i pensada per allotjar-se en un VPS compartit amb
**CloudPanel** al domini `poudeshorta.online`.

Els colors de la interfície, del PDF de l'entrada i dels correus estan extrets
del cartell de l'esdeveniment i es poden canviar des del Panell de Gestió.

---

## Què fa

### Web públic (sense comptes d'usuari)

- Pàgina de l'esdeveniment amb la informació, el cartell i els atractius, amb
  la tipografia i els colors del cartell i imatge de fons opcional.
- Tipus d'inscripció amb preu, descripció i detall del que inclou cadascun.
- Selecció de places, dades dels assistents i camps addicionals configurables
  (al·lèrgies, talla, taula…).
- **Pagament amb targeta a través de Stripe Checkout**: les dades de la targeta
  no passen mai pel servidor.
- Pantalla de compra correcta amb **icona de festa i confeti**, i botons per:
  - descarregar l'entrada en **PDF**,
  - afegir-la a l'**Apple Wallet** o al **Google Wallet** (opcional),
  - **enviar-la per correu** amb el PDF adjunt.
- **Les meves entrades**: la persona introdueix el seu correu i rep un enllaç
  temporal per veure, descarregar o **anul·lar** les inscripcions, sempre dins
  dels paràmetres definits al panell.

### Panell de Gestió

- Resum amb estadístiques, gràfic de vendes i llista de comprovació de la
  configuració pendent.
- **Tipus d'inscripció**: nom, descripció, què inclou, preu, aforament, límits
  per comanda, finestra de venda i camps addicionals del formulari.
- **Llistat d'inscripcions** amb filtres per data, tipus, estat del pagament,
  estat de l'entrada, control d'accés i cerca lliure (correu, nom, referència,
  codi o telèfon). Exportable a **PDF** i a **CSV**.
- Fitxa de cada inscripció: anul·lació total o parcial amb devolució a Stripe,
  reenviament de les entrades i notes internes.
- **Comunicats per correu** als inscrits, amb marcadors personalitzats,
  previsualització, enviament de prova i enviament per lots.
- **Configuració**: dades de l'esdeveniment, colors i imatges, claus de Stripe i
  webhook, servidor SMTP i plantilles de correu, política d'anul·lacions i
  passis de wallet.
- **Control d'accés** el dia de l'esdeveniment, escanejant el QR amb el mòbil o
  escrivint el codi.
- **Actualitzacions OTA** des de GitHub, amb còpia de seguretat prèvia,
  migracions automàtiques i restauració si alguna cosa falla.
- Usuaris del panell amb rols i registre d'activitat.

---

## Requisits

- PHP 8.1 o superior amb `pdo_mysql`, `curl`, `gd`, `mbstring`, `openssl` i `zip`
- MySQL 5.7+ o MariaDB 10.3+
- Un compte de Stripe
- Un compte de correu SMTP (per exemple, del mateix domini a CloudPanel)

Les dependències de PHP ja venen incloses a `vendor/`, de manera que **no cal
Composer al servidor**.

---

## Instal·lació ràpida

1. Pugeu el projecte al servidor i apunteu l'arrel del lloc a la carpeta `public/`.
2. Doneu permisos d'escriptura a `config/`, `storage/` i `public/uploads/`.
3. Obriu `https://poudeshorta.online` amb el navegador: apareixerà l'instal·lador.
4. Introduïu les dades de la base de dades i creeu l'usuari administrador.
5. Entreu al Panell de Gestió i completeu la llista de comprovació del resum.

Les instruccions detallades per a CloudPanel són a **[DEPLOY.md](DEPLOY.md)**.

---

## Estructura del projecte

```
bin/            Tasques programades (cron.php) i servidor de desenvolupament
config/         Configuració local (config.php, no es puja al repositori)
migrations/     Fitxers .sql que creen i actualitzen l'esquema
public/         Arrel web: controlador frontal, CSS, JS, tipografies i pujades
src/
  Core/         Encaminador, base de dades, sessió, seguretat, ajudants
  Services/     Stripe, PDF, QR, correu, wallets, actualitzacions
  Controllers/  Web públic (Web/) i Panell de Gestió (Admin/)
  Views/        Plantilles del web, del panell i dels correus
storage/        Registres, memòria cau, còpies de seguretat i certificats
vendor/         Dependències (FPDF, PHPMailer, BaconQrCode)
```

---

## Desenvolupament local

```bash
php -S 127.0.0.1:8000 -t public bin/serve.php
```

Aquest encaminador imita el comportament d'Nginx a CloudPanel: serveix els
fitxers existents i envia la resta de peticions al controlador frontal.

---

## Manteniment

Programeu la tasca cron cada 5 minuts perquè s'enviïn els comunicats, s'alliberin
les reserves caducades i es comprovin les actualitzacions:

```
*/5 * * * * /usr/bin/php /ruta/al/projecte/bin/cron.php >> /ruta/al/projecte/storage/logs/cron.log 2>&1
```

Si el servidor no permet cron, el Panell de Gestió (Estat del sistema) us dona
una URL protegida amb testimoni que fa la mateixa feina.

---

## Llicència

MIT.

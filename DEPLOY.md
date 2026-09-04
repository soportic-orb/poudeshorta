# Desplegament a CloudPanel · poudeshorta.online

Guia pas a pas per posar la plataforma en marxa en un VPS compartit amb
CloudPanel. Compteu-hi uns 30 minuts la primera vegada.

---

## 1. Crear el lloc a CloudPanel

1. **Sites → Add Site → Create a PHP Site**
2. Ompliu:
   - *Domain Name*: `poudeshorta.online`
   - *Site Title*: Inscripcions Pou de s'Horta
   - *PHP Version*: **8.2** o superior
   - *Site User*: per exemple `poudeshorta`
3. Un cop creat, entreu al lloc → **Settings** i canvieu:
   - *Root Directory*: `/home/poudeshorta/htdocs/poudeshorta.online/public`

> **Important**: l'arrel ha d'apuntar a `public/`. Si apunta al directori del
> projecte, quedarien exposats la configuració i els registres.

---

## 2. Certificat SSL

**Sites → poudeshorta.online → SSL/TLS → Actions → New Let's Encrypt Certificate**

Afegiu-hi `poudeshorta.online` i `www.poudeshorta.online`. Stripe exigeix HTTPS per al
webhook, i les entrades i els correus també han d'anar per HTTPS.

---

## 3. Base de dades

**Databases → Add Database**

- *Database Name*: `poudeshorta`
- *Database User Name*: `poudeshorta`
- *Password*: genereu-ne una de llarga i deseu-la

Anoteu les dades: us les demanarà l'instal·lador.

---

## 4. Pujar el codi

### Opció A · clonar el repositori (recomanada: permet actualitzacions per git)

Connecteu-vos per SSH amb l'usuari del lloc:

```bash
cd /home/poudeshorta/htdocs/poudeshorta.online
rm -rf ./*                 # el directori ha de quedar buit
git clone https://github.com/soportic-orb/poudeshorta.git .
```

### Opció B · pujar-ho per SFTP

Descarregueu el ZIP del repositori, descomprimiu-lo i pugeu-ne el contingut
(no la carpeta que l'embolcalla) a `/home/poudeshorta/htdocs/poudeshorta.online`.

En tots dos casos, la carpeta `vendor/` ja ve inclosa: **no cal executar
Composer al servidor**.

---

## 5. Permisos

```bash
cd /home/poudeshorta/htdocs/poudeshorta.online
chmod -R 775 config storage public/uploads
chown -R poudeshorta:poudeshorta .
```

---

## 6. Instal·lador web

Obriu `https://poudeshorta.online` amb el navegador. Us apareixerà l'instal·lador:

1. Comprova els requisits del servidor (extensions i permisos).
2. Demana les dades de la base de dades del pas 3.
3. Crea l'usuari administrador del panell.
4. Crea les taules i dos tipus d'inscripció d'exemple.

En acabar, l'instal·lador queda inhabilitat automàticament (existeix
`config/config.php`).

---

## 7. Configurar Stripe

### Claus

Al tauler de Stripe → **Developers → API keys**. Copieu-les al panell, a
**Configuració → Pagaments**. Comenceu sempre en **mode de proves**.

### Webhook (imprescindible)

**Developers → Webhooks → Add endpoint**

- *Endpoint URL*: `https://poudeshorta.online/webhook/stripe`
- Esdeveniments a escoltar:
  - `checkout.session.completed`
  - `checkout.session.expired`
  - `checkout.session.async_payment_succeeded`
  - `checkout.session.async_payment_failed`
  - `charge.refunded`

Copieu el *Signing secret* (`whsec_…`) al camp corresponent del panell.

> Sense el webhook, si algú tanca el navegador just després de pagar, la
> inscripció podria quedar pendent i no rebria les entrades.

### Prova

Amb el mode de proves actiu, feu una compra completa amb la targeta
`4242 4242 4242 4242`, qualsevol data futura i qualsevol CVC. Comproveu que:

- arriba a la pantalla de compra correcta amb el confeti,
- rebeu el correu amb el PDF adjunt,
- la inscripció apareix al llistat del panell.

Quan tot funcioni, poseu les claus reals i canvieu el mode a **live**.

---

## 8. Correu (SMTP)

A CloudPanel podeu fer servir un compte del mateix domini o un servei extern.
Configureu-ho a **Configuració → Correu** i feu servir el botó
**Enviar el correu de prova** per verificar-ho.

Valors habituals:

| Camp      | Valor                     |
|-----------|---------------------------|
| Servidor  | `smtp.elvostreproveidor.cat` |
| Port      | 587                       |
| Xifratge  | STARTTLS                  |
| Usuari    | `info@poudeshorta.online`    |

Per millorar l'entregabilitat, configureu els registres **SPF**, **DKIM** i
**DMARC** del domini al vostre proveïdor de DNS.

---

## 9. Tasques programades

**Sites → poudeshorta.online → Cron Jobs → Add Cron Job**

```
*/5 * * * * /usr/bin/php8.2 /home/poudeshorta/htdocs/poudeshorta.online/bin/cron.php >> /home/poudeshorta/htdocs/poudeshorta.online/storage/logs/cron.log 2>&1
```

S'encarrega d'enviar la cua de comunicats, alliberar les reserves caducades,
netejar fitxers temporals i comprovar si hi ha actualitzacions.

Si no podeu fer servir cron, a **Estat del sistema** hi trobareu una URL amb
testimoni que fa la mateixa feina i que podeu cridar des d'un servei extern.

---

## 10. Configurar l'esdeveniment

Al Panell de Gestió, seguiu la llista de comprovació del resum:

1. **Configuració → Esdeveniment**: nom, data, lloc, descripció i atractius.
2. **Configuració → Aparença**: colors i cartell (els colors per defecte ja són
   els del cartell del sopar).
3. **Tipus d'inscripció**: preus, aforament i què inclou cada inscripció.
4. **Configuració → Anul·lacions**: termini, devolucions i despeses de gestió.
5. Quan tot estigui a punt, activeu **Inscripcions obertes**.

---

## 11. Passis de wallet (opcional)

### Apple Wallet

Necessiteu un compte de desenvolupador d'Apple (99 €/any):

1. Creeu un **Pass Type ID** al portal de desenvolupadors.
2. Genereu-ne el certificat i exporteu-lo com a `.p12`.
3. Descarregueu el certificat intermedi **WWDR** d'Apple.
4. Pugeu-ho tot a **Configuració → Wallet** amb el Team ID.

### Google Wallet

1. Demaneu accés a la **Google Wallet Console** i anoteu l'*Issuer ID*.
2. Creeu un compte de servei a Google Cloud i descarregueu-ne el JSON.
3. Autoritzeu l'adreça del compte de servei a la Wallet Console.
4. Pugeu el JSON i l'Issuer ID a **Configuració → Wallet**.

Si no els configureu, els botons no apareixen i les entrades continuen
funcionant amb el PDF i el codi QR.

---

## 12. Actualitzacions OTA

A **Actualitzacions** podeu comprovar si hi ha una versió nova al repositori de
GitHub i aplicar-la des del navegador. El procés:

1. Fa una còpia de seguretat completa (se'n conserven les 5 últimes).
2. Activa el mode manteniment.
3. Descarrega i substitueix els fitxers del codi.
4. Executa les migracions de base de dades pendents.
5. Desactiva el manteniment.

Mai es toquen `config/config.php`, `storage/` ni `public/uploads/`. Si alguna
cosa falla, es restaura automàticament la còpia anterior.

Si el desplegament és un clon de git, s'utilitza `git fetch` + `git reset`; si
no, es descarrega el paquet ZIP del repositori. Per a un repositori privat,
indiqueu un token d'accés de GitHub amb permís de lectura.

---

## Canviar el domini més endavant

El domini no està escrit enlloc del codi: tot (correus, codis QR de les
entrades, peus de pàgina i webhook) surt de `base_url`, a `config/config.php`.
Si algun dia canvia:

1. Editeu `config/config.php` i poseu-hi el domini nou a `base_url`, sense
   barra final. Si el deixeu buit, el sistema el dedueix de cada petició.
2. Genereu el certificat SSL del domini nou a CloudPanel.
3. Canvieu l'URL del webhook al tauler de Stripe.
4. Reviseu l'adreça del remitent a **Configuració → Correu** i els registres
   SPF, DKIM i DMARC del domini nou.
5. Si ja s'havien venut entrades, **manteniu el domini antic apuntant al nou**
   amb una redirecció permanent: els codis QR que ja circulen contenen l'adreça
   antiga i han de continuar funcionant.

---

## Resolució de problemes

| Símptoma | Què cal mirar |
|---|---|
| Error 500 en blanc | `storage/logs/app-AAAA-MM.log` i **Estat del sistema** |
| Els pagaments queden pendents | El webhook de Stripe i el seu *signing secret* |
| No arriben els correus | El botó de prova a **Configuració → Correu**; reviseu SPF i DKIM |
| El PDF surt sense codi QR | Cal l'extensió `gd` de PHP |
| L'actualització OTA falla | Permisos d'escriptura del projecte i l'extensió `zip` |
| Els comunicats no s'envien | Comproveu que la tasca cron s'executa (Estat del sistema) |

---

## Còpies de seguretat

A més de les que fa l'actualitzador, us recomanem programar una còpia de la
base de dades des de CloudPanel (**Backups**) i conservar-ne còpies fora del
servidor, sobretot els dies previs a l'esdeveniment.

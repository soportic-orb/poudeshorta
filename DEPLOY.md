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
2. **Configuració → Aparença**: colors, imatge de fons de la capçalera i
   cartell (els colors per defecte ja són els del cartell del sopar). Per al
   fons de la capçalera va bé una fotografia horitzontal de 1920 × 1080 px o
   més, sense text; el control «Intensitat del vel de color» decideix quant es
   tapa perquè el títol es llegeixi.

   Les tipografies (Caveat Brush per als titulars i Nunito per al text) estan
   allotjades al mateix servidor, a `public/assets/fonts/`: el web no fa cap
   crida a servidors externs per carregar-les i no cal configurar res.
3. **Tipus d'inscripció**: preus, aforament i què inclou cada inscripció.
4. **Configuració → Anul·lacions**: termini, devolucions i despeses de gestió.
5. Quan tot estigui a punt, activeu **Inscripcions obertes**.

---

## 11. Passis de wallet (opcional)

Els passis són una comoditat: l'entrada queda desada a l'aplicació Wallet del
mòbil. **No són imprescindibles**: sense configurar-los, el PDF amb el codi QR
funciona igual i els botons simplement no apareixen.

### 11.1 Apple Wallet, pas a pas

Compteu-hi una hora i **99 €/any** del programa de desenvolupadors d'Apple.

#### Pas 1 · Donar-se d'alta a l'Apple Developer Program

1. Aneu a `developer.apple.com/programs` i premeu **Enroll**.
2. Trieu el tipus de compte:
   - **Individual**: a nom d'una persona, s'aprova en poques hores. És
     l'opció pràctica per a una comissió de festes.
   - **Organization**: exigeix entitat jurídica i número D-U-N-S, i pot trigar
     setmanes.
3. Pagueu la quota. Es renova automàticament cada any.

#### Pas 2 · Crear el «Pass Type ID»

1. Entreu a `developer.apple.com/account` → **Certificates, Identifiers &
   Profiles** → **Identifiers** → botó **+**.
2. Trieu **Pass Type IDs** i premeu Continue.
3. Ompliu:
   - *Description*: `Entrada Sopar Pou de s'Horta`
   - *Identifier*: `pass.online.poudeshorta.entrada`

   L'identificador **ha de començar per `pass.`**; la resta és el vostre domini
   a l'inrevés. Anoteu-lo: l'haureu de posar al panell exactament igual.
4. Register.

#### Pas 3 · Generar la sol·licitud de certificat (CSR)

Us recomanem fer-ho amb `openssl`, que funciona en qualsevol ordinador i genera
directament els fitxers que necessita el servidor:

```bash
openssl genrsa -out pass.key 2048
openssl req -new -key pass.key -out pass.csr \
  -subj "/emailAddress=info@poudeshorta.online/CN=Pou de s'Horta/C=ES"
```

Guardeu bé `pass.key`: és la clau privada i no es pot recuperar.

> Si ho feu des d'un Mac amb l'**Accés a Claus** (Assistent de certificats →
> Sol·licitar un certificat...), la clau privada queda dins del clauer i us
> caldrà exportar-la després com a `.p12`. Funciona igual, però el pas 6 té una
> nota important sobre aquest format.

#### Pas 4 · Emetre el certificat

1. Al Pass Type ID que heu creat, premeu **Configure** (o **Create
   Certificate**).
2. Pugeu el fitxer `pass.csr` i premeu Continue.
3. Descarregueu el certificat: obtindreu `pass.cer`.
4. Convertiu-lo a PEM:

```bash
openssl x509 -inform DER -in pass.cer -out pass-cert.pem
```

#### Pas 5 · Descarregar el certificat WWDR d'Apple

És el certificat intermedi que encadena el vostre amb l'arrel d'Apple.

1. Aneu a `apple.com/certificateauthority`.
2. Descarregueu **Worldwide Developer Relations - G4** (`AppleWWDRCAG4.cer`).

No cal convertir-lo: el panell accepta el format `.cer` i el converteix sol.

#### Pas 6 · Pujar-ho tot al Panell de Gestió

A **Configuració → Wallet**:

| Camp | Valor |
|---|---|
| Activar els passis | marcat |
| Pass Type ID | `pass.online.poudeshorta.entrada` |
| Team ID | els 10 caràcters de *Membership* a developer.apple.com/account |
| Nom de l'organització | Comissió de Festes del carrer Pou de s'Horta |
| Certificat del pass | `pass-cert.pem` |
| Clau privada | `pass.key` |
| Certificat WWDR | `AppleWWDRCAG4.cer` |
| Contrasenya del certificat | buida |

Premeu **Desar la configuració dels passis**.

> **Si veniu del Mac amb un `.p12`**: pugeu-lo al camp «Certificat del pass»,
> escriviu la contrasenya d'exportació i deseu. El sistema el converteix
> automàticament i esborra el `.p12` i la contrasenya.
>
> L'Accés a Claus del Mac exporta els `.p12` amb algorismes antics (RC2-40 i
> 3DES) que molts servidors amb OpenSSL 3 no llegeixen. Si el panell us diu que
> no l'ha pogut obrir, torneu-lo a exportar així des del Mac i pugeu el nou:
>
> ```bash
> openssl pkcs12 -in original.p12 -nodes -legacy -out temporal.pem
> openssl pkcs12 -export -in temporal.pem -out nou.p12 \
>   -keypbe AES-256-CBC -certpbe AES-256-CBC -macalg sha256
> rm temporal.pem
> ```

#### Pas 7 · Comprovar-ho

A la mateixa pàgina, al final, premeu **Generar un passi de prova**. Ha de dir
*«Passi de prova generat correctament»*. Si dona error, el missatge us indica
quin dels tres fitxers falla.

#### Pas 8 · Provar-ho en un iPhone de veritat

Amb Stripe en mode de proves, feu una compra, obriu la pantalla de confirmació
des d'un iPhone i premeu **Afegir a l'Apple Wallet**.

Si l'iPhone diu que no pot afegir el passi però la prova del pas 7 passava, la
causa gairebé sempre és que el **Pass Type ID o el Team ID del panell no
coincideixen exactament** amb els del certificat. Reviseu-los caràcter a
caràcter.

#### Renovació

El certificat del pass **caduca al cap d'un any**. Quan caduqui, els passis ja
descarregats continuen al mòbil, però no se'n poden signar de nous i la prova
del pas 7 començarà a fallar. Apunteu-vos la data i repetiu els passos 3 a 7.

### 11.2 Google Wallet, pas a pas

No té cost, però l'alta d'emissor l'ha d'aprovar Google i pot trigar uns dies.
Aquesta guia dona per fet que ja teniu el compte aprovat.

#### Pas 1 · Anotar l'Issuer ID

A la **Google Wallet Console** (`pay.google.com/business/console`), a
**Google Wallet API → Configuració**, hi trobareu l'**Issuer ID**: un número
llarg, del tipus `3388000000012345678`. Anoteu-lo.

#### Pas 2 · Crear el compte de servei

El compte de servei és qui signarà els passis en nom vostre.

1. Aneu al **Google Cloud Console** (`console.cloud.google.com`) amb el mateix
   compte i trieu (o creeu) un projecte.
2. **APIs i serveis → Biblioteca**, cerqueu **Google Wallet API** i premeu
   **Habilita**.
3. **IAM i administració → Comptes de servei → Crea un compte de servei**.
   Poseu-hi un nom com `wallet-poudeshorta`. No cal assignar-li cap rol del
   projecte.
4. Obriu el compte de servei acabat de crear → pestanya **Claus** →
   **Afegeix una clau → Crea una clau nova → JSON**. Es descarregarà un fitxer.

   Aquest fitxer és una credencial: tracteu-lo com una contrasenya.

#### Pas 3 · Autoritzar el compte de servei a la Wallet Console

Aquest pas és el que més es passa per alt, i sense ell tot falla amb un 403.

1. Torneu a la **Google Wallet Console** → **Users**.
2. **Invite a user** i poseu-hi l'adreça del compte de servei, que és el camp
   `client_email` del JSON i acaba en `.iam.gserviceaccount.com`.
3. Doneu-li el rol **Developer** (o Admin).

#### Pas 4 · Configurar-ho al Panell de Gestió

A **Configuració → Wallet**:

| Camp | Valor |
|---|---|
| Activar els passis | marcat |
| Issuer ID | el número del pas 1 |
| Identificador de la classe | un nom curt sense espais, per exemple `sopar_2026` |
| Fitxer JSON del compte de servei | el fitxer del pas 2 |

Premeu **Desar la configuració dels passis**.

#### Pas 5 · Crear la classe de l'esdeveniment

A sota de tot hi ha el botó **Crear la classe al Google Wallet**. Premeu-lo.

La classe conté el que és igual per a totes les entrades: nom de
l'esdeveniment, data, lloc i colors. **Aquest pas no és opcional**: Google
trunca els enllaços «Save to Wallet» que superen els 1800 caràcters, i si les
dades de l'esdeveniment han de viatjar dins de cada enllaç, se superen. Amb la
classe creada, l'enllaç només porta les dades de la persona i queda per sota
del límit amb marge.

Si canvieu el nom, la data o el lloc de l'esdeveniment, torneu a prémer el
botó perquè la classe s'actualitzi.

#### Pas 6 · Comprovar-ho

A **Comprovar la configuració dels passis**, premeu **Comprovar la
configuració**. Us dirà si l'enllaç es genera, si està ben signat i quants
caràcters ocupa dels 1800 permesos.

#### Pas 7 · Provar-ho en un Android de veritat

Amb Stripe en mode de proves, feu una compra, obriu la pantalla de confirmació
des d'un mòbil Android i premeu **Afegir al Google Wallet**.

#### Si alguna cosa falla

| Missatge | Què vol dir |
|---|---|
| El compte de servei no té permís sobre aquest emissor | Falta el pas 3: autoritzar-lo a la Wallet Console |
| Google no ha acceptat el compte de servei | El JSON no és correcte o falta habilitar la Google Wallet API (pas 2.2) |
| Google no troba l'emissor indicat | L'Issuer ID no és correcte |
| L'enllaç és massa llarg | Falta el pas 5: crear la classe |

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
no, es descarrega el paquet ZIP del repositori.

### Configuració

A **Actualitzacions → Origen de les actualitzacions**:

| Camp | Què hi va |
|---|---|
| Repositori | `soportic-orb/poudeshorta` |
| Branca | **el nom exacte de la branca que voleu seguir** |
| Canal | *Últim canvi de la branca* o *Només versions publicades* |
| Token d'accés | obligatori si el repositori és privat |

> **El camp «Branca» ve amb `main` per defecte.** Si el codi encara viu en una
> branca de treball i `main` no existeix, l'actualització fallarà dient que no
> troba la branca. Poseu-hi el nom exacte, o fusioneu la branca a `main` i
> deixeu-hi `main`.

### Quin canal triar

| Canal | Com decideix si hi ha novetats | Quan va bé |
|---|---|---|
| **Últim canvi de la branca** | Compara la revisió (el commit) instal·lada amb l'última de la branca | Mentre es fan canvis sovint |
| **Només versions publicades** | Compara el fitxer `VERSION` amb l'última *release* de GitHub | Per a la plataforma ja en marxa |

Amb el canal de branca, **el número de versió només canvia quan algú edita el
fitxer `VERSION`**: el que identifica el codi que teniu és la revisió, que el
panell mostra just a sota. Si voleu que el número de versió sigui el criteri,
trieu el canal de versions publicades i, a cada entrega:

1. Actualitzeu `VERSION` i `CHANGELOG.md`.
2. Creeu una *release* a GitHub amb l'etiqueta corresponent (per exemple
   `v1.1.0`). Un tag sol no n'hi ha prou: ha de ser una release publicada.

### Token d'accés per a repositoris privats

Si el repositori és privat cal un token, perquè **GitHub respon 404 (no pas
403) als repositoris privats quan qui pregunta no hi té accés**: sense token
sembla que el repositori no existeixi.

1. A GitHub: **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens → Generate new token**.
2. *Repository access*: només `soportic-orb/poudeshorta`.
3. *Permissions → Repository permissions → Contents*: **Read-only**.
4. Copieu el token i enganxeu-lo al camp «Token d'accés» del panell.

### Si l'actualització no funciona

Premeu **Diagnosticar la connexió**. Comprova un per un el repositori, el
token, l'extensió `zip`, el mètode d'actualització, els permisos d'escriptura,
l'espai lliure, l'accés real a GitHub i l'existència de la branca, i us marca
en vermell el que falla.

| El diagnòstic diu | Què cal fer |
|---|---|
| No troba el repositori (404) | Reviseu-ne el nom; si és privat, configureu el token |
| Accés denegat (403) | El token no té permís de lectura sobre aquest repositori |
| Token rebutjat (401) | El token ha caducat: genereu-ne un de nou |
| Límit de consultes exhaurit | Espereu a l'hora que indica, o configureu un token |
| La branca no existeix | Poseu el nom exacte de la branca (vegeu l'avís de dalt) |
| No es pot contactar amb GitHub | El servidor no té sortida cap a `api.github.com:443` |
| Sense permisos d'escriptura | `chmod -R u+w` al directori del projecte |

> **Nota**: «Comprovar si hi ha novetats» consulta l'API de GitHub, però
> «Actualitzar ara» **no la fa servir** quan el desplegament és un clon de git:
> treballa directament amb `git fetch` i `git reset`. Si la comprovació falla
> però l'actualització funciona, la causa és a l'API (token, límit de consultes
> o nom de la branca), no al desplegament.

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
| El passi d'Apple no s'afegeix al mòbil | Que el Pass Type ID i el Team ID coincideixin amb el certificat (apartat 11.1, pas 8) |
| No es pot obrir el `.p12` d'Apple | L'exportació del Mac fa servir algorismes antics: reexporteu-lo (apartat 11.1, pas 6) |
| Els comunicats no s'envien | Comproveu que la tasca cron s'executa (Estat del sistema) |

---

## Còpies de seguretat

A més de les que fa l'actualitzador, us recomanem programar una còpia de la
base de dades des de CloudPanel (**Backups**) i conservar-ne còpies fora del
servidor, sobretot els dies previs a l'esdeveniment.

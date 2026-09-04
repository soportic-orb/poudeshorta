# Historial de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/) i el
versionatge és [semàntic](https://semver.org/lang/ca/).

Quan publiqueu una versió nova, actualitzeu aquest fitxer **i el fitxer
`VERSION`**: el Panell de Gestió mostra el contingut de `VERSION` a
Actualitzacions i a la barra lateral.

---

## [1.11.1] — 2026-09-04

### Canviat

- **Les banderoles de dalt van ara damunt de la fotografia de la capçalera.**
  Abans anaven en una tira pròpia amb fons de color, i la fotografia començava
  per sota. Ara la fotografia arriba fins a la línia groga que separa el menú,
  i les banderoles hi pengen a sobre.
- A les pàgines sense fotografia de capçalera, les banderoles continuen com
  fins ara, amb el seu fons: sense ell, les banderoles clares no es veurien.

---

## [1.11.0] — 2026-09-04

### Corregit

- **No es podien anul·lar la resta d'entrades un cop se n'havia anul·lat una.**
  En anul·lar-ne una amb devolució, la inscripció passa a l'estat «retornada
  parcialment», i la comprovació només acceptava l'estat «pagada»: la web
  responia «Només es poden anul·lar inscripcions pagades» i bloquejava les
  entrades que encara eren bones. Ara es poden anul·lar mentre quedi termini,
  n'hi hagi hagut cap d'anul·lada abans o no.

### Canviat

- Al detall de la inscripció, la banda de l'esquerra de cada entrada és
  **verda quan l'entrada és bona** i **vermella quan està anul·lada**.
- Les entrades anul·lades porten una **banda diagonal a la cantonada superior
  dreta** amb el text «Anul·lada», en comptes d'indicar-ho amb text al costat
  del preu.
- **El PDF de l'entrada porta el logotip a l'esquerra de la capçalera.** Es
  reescala sol dins de l'espai que hi ha, sense deformar-lo, i es compon sobre
  el color de la capçalera perquè els logotips amb fons transparent es vegin
  bé. Si no n'hi ha cap de configurat, la capçalera queda com abans.

---

## [1.10.0] — 2026-09-04

### Afegit

- **Selecció múltiple i esborrat al llistat d'inscripcions.** Cada entrada té
  una casella, hi ha una casella per marcar-les totes de cop i, quan n'hi ha
  cap de marcada, apareix una barra amb el compte i el botó d'esborrar.
  - Abans d'esborrar res es mostra **una pantalla de confirmació** amb la
    llista del que desapareixerà, quantes inscripcions queden afectades i què
    implica. No hi ha esborrat d'un sol clic.
  - Si l'esborrat afecta entrades cobrades, s'avisa que **no es retorna cap
    diner** i s'indica que per això hi ha «Anul·lar», que sí que tramita la
    devolució a Stripe.
  - També avisa si alguna de les entrades **ja s'havia validat** al control
    d'accés.
  - Quan una inscripció es queda sense cap entrada, s'esborra també ella.
  - Només hi poden arribar els comptes amb rol **propietari o administrador**;
    la resta no veuen ni les caselles, i la petició es rebutja encara que
    s'intenti a mà.
  - Queda registrat a l'auditoria amb els codis de les entrades esborrades.
  - En tornar al llistat es mantenen els filtres que hi havia posats.

### Corregit

- **La cerca del llistat d'inscripcions donava error 500.** La consulta
  repetia el mateix marcador per a les set columnes on cerca, i les consultes
  preparades no ho admeten. Afectava també les exportacions a PDF i CSV quan
  es feien amb una cerca activa.

---

## [1.9.0] — 2026-09-04

### Canviat

- **Els botons dels wallets ara hi afegeixen totes les entrades de la comanda.**
  Fins ara només s'hi afegia la primera: qui comprava quatre entrades només en
  desava una al mòbil.
  - **Apple Wallet**: si la comanda té més d'una entrada s'envia un paquet
    `.pkpasses` i el mòbil ofereix afegir-les totes de cop. Amb una sola entrada
    es continua enviant el `.pkpass` de sempre.
  - **Google Wallet**: amb una entrada l'enllaç es basta tot sol, com fins ara.
    A partir de dues no hi cabrien totes dins de l'enllaç, i per això les
    entrades es desen abans al compte de Google i l'enllaç només n'ha de portar
    l'identificador.
- **Botons oficials «Add to Apple Wallet» i «Add to Google Wallet»**, amb el
  fons negre i les icones de cada marca, en lloc dels botons genèrics amb
  emoji.
- A «Les meves entrades», cada entrada té al costat la icona dels wallets per
  afegir-hi **només aquella entrada**.

### Corregit

- **El passi d'Apple no mostrava el logotip configurat a la web.** Ara la
  capçalera del passi porta el logotip de Configuració → Aparença, reescalat a
  la mida que demana Apple i sense deformar-lo. Abans sempre hi sortia una
  marca dibuixada amb els colors de la plataforma.
- Els passis inclouen també les imatges `@3x`, perquè es vegin nítides a les
  pantalles dels mòbils actuals.

---

## [1.8.0] — 2026-09-04

### Afegit

- **Escàner de codis QR amb la càmera al control d'accés.** Des del mòbil (o
  qualsevol dispositiu amb càmera) ja no cal escriure el codi a mà: hi ha un
  botó «Escanejar amb la càmera» que obre la càmera del darrere dins la mateixa
  pàgina i valida les entrades una rere l'altra, sense recarregar res.
  - Llegeix indistintament el QR del PDF imprès, el de la pantalla del mòbil i
    el dels passis d'Apple Wallet i Google Wallet: tots porten el mateix codi.
  - Fa servir l'API `BarcodeDetector` del navegador quan hi és (Chrome a
    Android), que no descarrega res. Quan no hi és (Safari a l'iPhone) carrega
    jsQR, que va allotjat al mateix servidor i només es baixa en obrir la
    càmera.
  - Avisa amb un so i una vibració curta si l'entrada és correcta, i amb un so
    greu i una vibració doble si no ho és.
  - No repeteix la mateixa entrada mentre s'enfoca, i el resultat es col·loca
    sol a la pantalla perquè es vegin alhora el missatge i la càmera.
  - La càmera es tanca sola en canviar de pestanya i en prémer «Tancar la
    càmera»; el llum de la càmera s'apaga de debò.
  - Si el navegador no pot obrir la càmera (per exemple sense HTTPS), el botó
    no surt i s'explica que cal escriure el codi a mà.

### Corregit

- **L'atribut `hidden` no amagava els botons ni els avisos.** Com que `.btn` i
  `.alert` defineixen `display`, guanyaven a la regla del navegador i els
  elements marcats com a amagats es veien igualment.
- Al mòbil, el camp del codi ja no s'enfoca sol en obrir el control d'accés: el
  teclat tapava mitja pantalla.

---

## [1.7.2] — 2026-09-04

### Corregit

- **La creació de la classe del Google Wallet fallava** amb «Default value for
  LocalizedString cannot be empty» quan no s'havia omplert el lloc de
  l'esdeveniment: s'enviava l'adreça del recinte buida i Google la rebutja.
  Ara el bloc del lloc només s'envia si se sap, i cap text localitzat pot anar
  buit.
- Si falta el nom de l'esdeveniment o l'organitzador, s'avisa abans de trucar a
  Google i s'indica on omplir-ho.

---

## [1.7.1] — 2026-09-04

### Canviat

- Si la classe del Google Wallet ja existeix però el compte de servei no la pot
  actualitzar, es dona per registrada igualment i s'avisa del motiu: per
  escurçar els enllaços n'hi ha prou que la classe existeixi. Abans quedava
  bloquejat i els enllaços seguien sent massa llargs.
- Els errors en consultar la classe es reporten en comptes de continuar com si
  res.

---

## [1.7.0] — 2026-09-04

### Corregit

- **Els passis del Google Wallet no s'haurien desat.** L'enllaç portava les
  dades de l'esdeveniment a dins i sortien 2123 caràcters, quan Google trunca
  els enllaços de més de 1800 i llavors no es desa res, sense avisar.
- El missatge d'estat de l'Apple Wallet deia «Configurat» encara que els
  fitxers dels certificats haguessin desaparegut del disc.

### Afegit

- Botó **Crear la classe al Google Wallet**: crea al compte de Google les
  dades comunes a totes les entrades, de manera que l'enllaç de cada entrada
  passa de 2123 a 1306 caràcters i queda amb marge sota el límit.
- La comprovació de configuració dels passis ara prova també el Google Wallet
  i informa de la mida de l'enllaç.
- Guia pas a pas del Google Wallet a `DEPLOY.md`, amb els errors habituals.

---

## [1.6.0] — 2026-09-04

### Afegit

- Missatge propi a la pantalla de pagament, al quadre «Resum», sota l'import
  total i just abans de les condicions. S'escriu i s'activa des de
  Configuració → Pagaments. Admet diverses línies i converteix les adreces
  web en enllaços; l'HTML que s'hi escrigui s'escapa i no s'executa.

### Canviat

- La conversió de text pla a HTML passa a `Str::toHtmlParagraphs()`, que ara
  comparteixen el missatge del pagament i els correus. Comprovat que els
  correus generen exactament el mateix HTML que abans.

---

## [1.5.0] — 2026-09-04

### Afegit

- Sota el total de la pantalla de pagament s'indica, a títol informatiu, quina
  part de l'import correspon a les despeses de la passarel·la, deixant clar
  que no és cap càrrec addicional. El percentatge i l'import fix es
  configuren a Pagaments (per defecte 1,5% + 0,25 €, la tarifa de Stripe per a
  targetes europees) i la nota es pot amagar.

  A Stripe se li continua enviant l'import total: la comissió no es descompta
  de res, només s'explica.

---

## [1.4.1] — 2026-09-04

### Corregit

- **El pagament amb targeta no arrencava.** La sessió de Stripe demanava la
  passarel·la en català i Stripe no ofereix aquest idioma, de manera que
  rebutjava totes les peticions. Ara l'idioma és configurable
  (Pagaments → Idioma de la passarel·la), per defecte automàtic segons el
  navegador de qui paga, i sempre es valida contra la llista d'idiomes que
  Stripe admet: un valor incorrecte no pot tornar a bloquejar cap pagament.
- La caducitat de la sessió de pagament es demanava de 30 minuts justos, el
  mínim que Stripe accepta; el temps que triga la petició a arribar la deixava
  per sota i la podia rebutjar. Ara se'n demanen 35.
- Les comandes per sota de l'import mínim de Stripe (0,50 € en euros) es
  detecten abans d'anar a la passarel·la i s'explica el motiu, en comptes de
  rebre un error de Stripe.

### Canviat

- El nom dels tipus d'inscripció es veu molt més gran a la portada. Hi havia
  dues regles d'estil per al mateix element i manava la petita.
- S'ha tret el camp «Concepte a l'extracte bancari» de la configuració de
  pagaments: no s'enviava enlloc.

---

## [1.4.0] — 2026-09-04

### Canviat

- **Tipografia del cartell a tot el web públic**: Caveat Brush, de pinzell, per
  als titulars, i Nunito per al text. Totes dues estan allotjades al mateix
  servidor (`public/assets/fonts/`), de manera que el web no fa cap crida a
  servidors externs. Llicència SIL OFL 1.1 en tots dos casos.
- Els titulars s'han redimensionat: la lletra de pinzell té l'ull més petit i
  necessita més cos i menys interlineat.
- La pàgina de dades de la inscripció fa servir la mateixa capçalera de color
  que la resta de pàgines interiors.

### Afegit

- **Banderoles** sota la barra de navegació, com les de la part alta del
  cartell, dibuixades amb els colors configurats al panell.

### Notes

- Els correus i les entrades en PDF continuen amb les tipografies de sempre:
  els gestors de correu no carreguen lletres externes de manera fiable i el PDF
  ha de veure's igual a qualsevol lector.

---

## [1.3.0] — 2026-09-04

### Canviat

- **Pàgina de l'entrada (la del codi QR)**: l'estat ara es veu abans de llegir
  res, amb una franja de color a tota l'amplada — verd si és vàlida, ambre si
  ja s'ha validat, vermell si està anul·lada, no pagada o no existeix — i el
  nom de l'assistent en gran. És la pantalla que es mira a la porta, de nit i
  amb pressa.
- **Pàgines interiors** (informació, les meves entrades, detall de la
  inscripció): totes tenen ara una capçalera de color amb el títol, una frase
  d'context i l'enllaç per tornar enrere, en comptes de començar amb un títol
  nu sobre el fons crema.
- Els títols de secció porten un traç d'accent a sota, com el subratllat del
  cartell.

### Afegit

- Compte enrere a la portada quan s'ha indicat la data exacta de
  l'esdeveniment: «Falten 22 dies», «Demà!» o «És avui!».
---

## [1.2.0] — 2026-09-04

### Afegit

- **Imatge de fons a la capçalera del web públic**, configurable des de
  Aparença: pujada de la fotografia, intensitat del vel de color (0–90),
  part de la imatge que es conserva en escapçar-la i opció de continuar
  mostrant el cartell al costat del títol o substituir-lo pels atractius de
  l'esdeveniment. Sense imatge es manté el degradat de la marca.

### Canviat

- La capçalera del web públic ja no es parteix en dues línies als mòbils: els
  enllaços ocupen una sola fila i, en pantalles estretes, «Informació» s'amaga
  perquè el botó d'inscriure's hi càpiga sencer.

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

# Add-on-ul Newsman pentru CS-Cart - Ghid de configurare

Acest ghid trece in revista toate setarile add-on-ului Newsman pentru a putea conecta magazinul CS-Cart la contul tau Newsman si a incepe colectarea abonatilor, trimiterea de newslettere si urmarirea comportamentului clientilor.

---

## Unde gasesti setarile add-on-ului

Dupa instalarea si activarea add-on-ului, pagina de setari Newsman este disponibila in doua locuri:

- **Admin > Marketing > Newsman** - intrarea principala, adaugata automat de add-on.
- **Admin > Add-ons > Manage add-ons > Newsman > iconita roata > Settings** - pagina standard de setari CS-Cart pentru add-on-uri; aici gasesti cateva optiuni tehnice care nu sunt expuse pe pagina principala.

Configurarea curenta se face pe pagina principala Newsman din **Marketing > Newsman**. Sectiunile de mai jos descriu fiecare setare, de sus in jos.

---

## Primii pasi - Conectarea la Newsman

Inainte de a folosi orice functionalitate, add-on-ul trebuie conectat la contul tau Newsman. Sunt doua metode:

### Varianta A: Configurare rapida cu OAuth (recomandata)

1. Mergi in **Admin > Marketing > Newsman**.
2. Apasa butonul **Connect with Newsman** (denumit **Reconnect** daca exista deja o conexiune).
3. Vei fi redirectionat catre site-ul Newsman. Autentifica-te si acorda permisiunile cerute.
4. Vei fi redirectionat inapoi la o pagina admin Newsman din CS-Cart, unde alegi lista de email dintr-un dropdown. Selecteaza lista si apasa **Save**.
5. Gata - API Key, User ID, List si Authentication Token sunt completate automat.

### Varianta B: Configurare manuala

1. Autentifica-te in contul tau Newsman la [newsman.app](https://newsman.app).
2. Mergi in setarile contului si copiaza **API Key** si **User ID**.
3. In CS-Cart, mergi in **Admin > Marketing > Newsman**.
4. Introdu **API Key** si **User ID** in campurile corespunzatoare.
5. Apasa **Save**. Un indicator verde confirma conexiunea.
6. Apoi selecteaza o **Email List** din dropdown; lista e populata din contul tau Newsman.
7. Optional, selecteaza un **Segment** din lista respectiva.
8. Selecteaza o **CS-Cart mailing list** (obligatoriu) pentru a defini ce abonati CS-Cart participa la sincronizarea bidirectionala (vezi mai jos).
9. Apasa **Save** din nou.

---

## Reconectare cu Newsman OAuth

Daca trebuie sa reconectezi add-on-ul la un alt cont Newsman sau daca ti s-au schimbat credentialele, mergi in **Admin > Marketing > Newsman** si apasa butonul **Reconnect** din partea de jos a paginii de setari. Acest flux OAuth actualizeaza API Key, User ID, List si Authentication Token cu noile credentiale.

---

## Sectiunile paginii de setari

Pagina **Admin > Marketing > Newsman** este organizata pe sectiuni. Fiecare sectiune este descrisa mai jos.

### Account (Cont)

- **User ID** (obligatoriu) - User ID-ul Newsman. Completat automat de OAuth.
- **API Key** (obligatoriu) - API Key-ul Newsman. Completat automat de OAuth.
- **Statusul conexiunii** - Un indicator colorat arata daca API Key si User ID se autentifica corect cu Newsman:
  - Punct verde: Connected to Newsman.
  - Punct rosu: Could not connect to Newsman. Please check your credentials or try again later.

### General

- **Email List** (obligatoriu) - Lista Newsman unde sunt adaugati abonatii din acest magazin CS-Cart. Dropdown-ul este populat din contul tau Newsman.
- **Segment** - Optional, restrange sincronizarea la un segment specific din lista selectata. Lasa gol pentru sincronizare cu toata lista.
- **CS-Cart mailing list** (obligatoriu) - Lista de mailing CS-Cart folosita pentru sincronizarea bidirectionala cu Newsman:
  - Doar abonatii listei CS-Cart selectate sunt trimisi catre Newsman.
  - Webhook-urile Newsman de subscribe/unsubscribe afecteaza doar aceasta lista.
  - Daca alegi **Any list (no restriction)**, actiunile de subscribe/unsubscribe sunt ignorate - alege o lista aici pentru a activa sincronizarea.
- **Double Opt-in** - Cand este activ, abonatii noi primesc un email de confirmare de la Newsman si sunt adaugati in lista doar dupa ce fac click pe link-ul din email. Recomandat pentru deliverability mai bun.
- **Send User IP** - Cand un vizitator se aboneaza sau plaseaza o comanda, add-on-ul poate trimite adresa IP a clientului catre Newsman (util pentru analytics si anti-abuz). Daca il dezactivezi, add-on-ul foloseste **Server IP** de mai jos ca fallback.
- **Server IP** - Adresa IP folosita ca fallback cand **Send User IP** este dezactivat. De regula poti lasa gol; add-on-ul detecteaza automat IP-ul serverului.

### Remarketing

- **Enable Remarketing** - Activeaza scriptul Newsman de remarketing pe storefront (product views, category views, modificari cos, checkout, comenzi).
- **Remarketing ID** - Remarketing ID-ul tau Newsman (copiaza-l din **newsman.app > Integrations > NewsMAN Remarketing**).
- **Remarketing ID Status** - Un indicator colorat arata daca Remarketing ID este setat corect:
  - Punct verde: Remarketing ID is valid.
  - Punct rosu: Remarketing ID is not set.
- **Anonymize IP** - Cand este activ, ultimul octet al IP-ului vizitatorului este mascat inainte de tracking. Util pentru conformitate cu politici stricte de confidentialitate.
- **Send Telephone** - Cand este activ, numarul de telefon al clientului (cand este prezent in comanda sau profil) este inclus in evenimentele de remarketing.
- **Theme Cart Compatibility** (activ implicit) - Controleaza modul in care scriptul de remarketing detecteaza modificarile cosului:
  - **Activ**: scriptul face polling catre endpoint-ul Newsman pentru a detecta modificarile cosului; functioneaza pe orice tema, dar adauga o cerere recurenta usoara.
  - **Inactiv**: scriptul citeste JSON-ul cosului direct din DOM-ul bloc-ului mini-cart (fara polling). Dezactiveaza aceasta optiune doar daca blocul mini-cart al temei tale se re-randeaza la fiecare modificare a cosului - altfel, modificarile pot fi pierdute.

### Developer

- **Log Level** - Cat de detaliate sunt fisierele de log ale add-on-ului. Mareste-l cand depanezi; redu-l in productie.
  - **None**: nu se scrie nimic in log.
  - **Error**: doar erori (implicit).
  - **Warning**, **Notice**, **Info**, **Debug**: detalii progresive, de la putin la mult.
  - Log-urile se scriu in `var/newsman_logs/` din instalarea CS-Cart.
- **Log Retention (days)** (implicit 30) - Cate zile de log-uri pastreaza add-on-ul. Fisierele mai vechi sunt sterse automat de cron.
- **API Timeout (seconds)** (implicit 30) - Timeout-ul pentru cererile individuale catre API-ul Newsman. Mareste-l daca vezi erori de timeout pe retele lente.
- **Enable IP Restriction** - Cand este activ, scripturile de remarketing sunt randate doar pentru adresa IP configurata mai jos. Util pentru teste fara a afecta vizitatorii reali.
- **Developer IP** - Adresa IP care primeste scripturile de tracking cand **Enable IP Restriction** este activ.

### Export Authorization

Aceste setari protejeaza endpoint-urile de export Newsman (product feed, export cupoane, istoricul comenzilor etc.) pe care Newsman le apeleaza in magazinul tau.

- **Authentication Token** - Token-ul pe care Newsman il foloseste pentru a se autentifica la apelurile catre magazinul tau. Afisat mascat (`*****XX`). Token-ul este rotit automat cand se schimba API Key, User ID, Email List sau conexiunea OAuth - nu trebuie sa-l gestionezi manual.
- **Header Name** - Nume optional de header HTTP suplimentar, peste token-ul de autentificare (alfanumeric, separat prin liniute). Seteaza aceeasi valoare si in **newsman.app > E-Commerce > Coupons > Authorisation Header name** si in campurile Feed > Header Authorization corespunzatoare.
- **Header Key** - Valoarea optionala pentru header-ul HTTP definit mai sus (alfanumeric, separat prin liniute). Seteaza aceeasi valoare si in **newsman.app > E-Commerce > Coupons > Authorisation Header value** si in echivalentul Feed.

---

## Salvarea modificarilor

Deruleaza in jos si apasa **Save** pe pagina de setari Newsman. Dupa salvare, sterge cache-ul CS-Cart din **Admin > Administration > Storage > Clear Cache**, pentru ca modificarile de template (de exemplu activarea scripturilor de remarketing) sa fie preluate de storefront.

---

## Setari Add-ons > Manage add-ons

Cateva optiuni suplimentare sunt expuse pe pagina standard CS-Cart de setari a add-on-ului (accesibila din **Admin > Add-ons > Manage add-ons > Newsman > iconita roata > Settings**). Acestea oglindesc optiunile de pe pagina principala Newsman si sunt pastrate in storage-ul CS-Cart pentru acces la nivel de addon din hooks:

- **API Key**, **User ID**, **CS-Cart mailing list to sync**
- **Double Opt-in**, **Send User IP**
- **Enable Remarketing**, **Anonymize IP**, **Send Telephone**, **Theme Cart Compatibility**
- **Log Level**, **API Timeout (seconds)**, **Log Retention (days)**
- **Export Auth Header Name**, **Export Auth Header Key**
- **Use Developer IP**, **Developer IP Address**

In mod normal nu trebuie sa modifici nimic aici - e mai sigur sa modifici setarile pe pagina principala Newsman din **Marketing**.

---

## Depanare

- **"Could not connect to Newsman"** - Verifica din nou API Key si User ID. Daca sunt corecte si eroarea persista, apasa **Reconnect** pentru a relua fluxul OAuth.
- **Subscribe/unsubscribe de la newsletter nu ajunge la Newsman** - Asigura-te ca este selectata o **CS-Cart mailing list** in sectiunea General. Sincronizarea este restransa in mod intentionat la o singura lista CS-Cart, asa ca abonatii adaugati la alte liste sunt sariti.
- **Scripturile de remarketing nu apar pe storefront** - Verifica ca **Enable Remarketing** este activ, ca **Remarketing ID** este setat (status verde) si ca ai sters cache-ul CS-Cart.
- **Ai nevoie de mai multe detalii in log** - Seteaza temporar **Log Level** la **Debug**; fisierele de log sunt in `var/newsman_logs/`.

---

## Storefronts si Multi-Vendor

Daca instalarea ta CS-Cart foloseste storefronts (sau Multi-Vendor Plus / Ultimate), Newsman se configureaza pe fiecare storefront in parte. Deschide admin-ul fiecarui storefront si configureaza add-on-ul Newsman separat. Sincronizarea si remarketing-ul sunt scoped la storefront-ul de unde provin, asa ca fiecare storefront poate fi conectat la o lista Newsman diferita.

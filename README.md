# WC Order Notifier

**WC Order Notifier** je WordPress/WooCommerce dodatak koji prikazuje vizualne obavijesti u admin sučelju kada stigne nova narudžba. Dizajniran je za shop managere i administratore kojima je važno brzo reagirati na nove narudžbe bez potrebe za ručnim osvježavanjem stranice.

![Toastr notification preview](https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css)

## 🎯 Ključne značajke

- Prikaz notifikacija pomoću Toastr.js
- Provjera statusa novih narudžbi pomoću AJAX-a
- Mogućnost automatskog osvježavanja stranice narudžbi
- Adaptivni interval provjere (manje opterećenje servera)
- Postavke unutar WooCommerce > Postavke > Order Notifier
- Stilizirana značkica s animacijom u admin sučelju

## ⚙️ Instalacija

1. Preuzmi ili kloniraj repozitorij u `/wp-content/plugins/`:
   ```bash
   git clone https://github.com/zvonac99/order-notifier.git
2. Aktiviraj dodatak kroz WordPress admin sučelje (Dodaci > Aktiviraj).

🔧 Konfiguracija
Idi na WooCommerce > Postavke > Order Notifier i podesi sljedeće:

Interval (sekundi) – koliko često se provjerava ima li novih narudžbi

Statusi za praćenje – npr. processing, on-hold, completed

Prikaz notifikacija – samo na stranici narudžbi ili bilo gdje u adminu

Auto-refresh stranice – automatski ponovno učitaj listu narudžbi

Adaptivni interval – rasteže vrijeme između provjera ako nema aktivnosti

Broj pokušaja i korak povećanja – detaljna kontrola adaptivnog ponašanja

📦 Stilovi
Dodatak koristi prilagođeni CSS za značkicu narudžbe

🛡️ Sigurnost
AJAX zahtjevi za provjeru novih narudžbi zaštićeni su WordPress nonce-om

Korištenje current_user_id() i hash-a za korisnički tracking (lokalno)

Nema spremanja korisničkih podataka

📅 Verzija
Trenutna verzija: 1.5.0

🧑‍💻 Autor
zvonac99

# WC Order Notifier

**HR 🇭🇷**  
WooCommerce dodatak za prikaz obavijesti o novim narudžbama u administratorskom sučelju.  
Praktično rješenje za shop managere koji žele odmah znati kada stigne nova narudžba bez potrebe za stalnim osvježavanjem stranice.

**EN 🇬🇧**  
A WooCommerce extension that displays real-time notifications about new orders in the admin area.  
A practical solution for shop managers who want instant alerts when a new order is received without refreshing the page.

---

## 🎯 Značajke / Features

- 🔔 Toastr.js notifikacije o novim narudžbama
- 🕒 Intervalno provjeravanje statusa narudžbi putem AJAX-a
- ⚙️ WooCommerce sučelje za postavke
- 🌐 Mogućnost prikaza notifikacija samo na stranici narudžbi ili svugdje u adminu
- 📈 Adaptivni intervali (manje provjera kada nema aktivnosti)
- 🔁 Opcionalni automatski reload WooCommerce narudžbi

---

## ⚙️ Instalacija / Installation

1. Preuzmite master.zip ili klonirajte repozitorij u `wp-content/plugins` direktorij:
git clone https://github.com/zvonac99/order-notifier.git

2. Aktivirajte dodatak u **WordPress > Dodaci**.
3. Idite na **WooCommerce > Settings > Order Notifier** za konfiguraciju.

---

## 🛠️ Postavke / Settings

U izborniku **WooCommerce > Settings > Order Notifier** dostupne su sljedeće opcije:

| Opcija / Option                | Opis / Description |
|-------------------------------|--------------------|
| Interval (sekundi) / Interval (seconds) | Vrijeme između provjera novih narudžbi / Time between order checks |
| Statusi za praćenje / Tracked statuses | Koje statuse narudžbi plugin nadzire (npr. obrada, na čekanju) |
| Prikaz notifikacija / Notification display scope | Samo na stranici narudžbi ili svugdje u adminu |
| Auto-refresh stranice / Auto-refresh page | Automatski osvježi listu narudžbi kada stigne nova |
| Adaptivni interval / Adaptive interval | Povećava razmak između provjera ako nema aktivnosti |
| Pokušaji prije povećanja / Attempts before increasing | Koliko puta bez nove narudžbe prije povećanja intervala |
| Korak povećanja / Step size | Za koliko sekundi se poveća interval |

---

## 👨‍💻 Razvoj / Development

- PHP 7.4+
- WordPress 6+
- WooCommerce 7+
- Toastr.js za notifikacije
- AJAX i WP nonce sigurnost

---

## 📝 Licenca / License

.....

---

**Autor / Author:**  
zvonac99  
[github.com/zvonac99](https://github.com/zvonac99)


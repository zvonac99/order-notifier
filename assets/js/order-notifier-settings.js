document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('archived-log-select');
  const loadBtn = document.getElementById('load-log');
  const deleteBtn = document.getElementById('delete-log');
  const output = document.getElementById('log-output');
  const deleteCurrentBtn = document.getElementById('delete-current-log'); // novi gumb
  const refreshBtn = document.getElementById('refresh-current-log'); // novi gumb za osvježavanje

  refreshBtn?.addEventListener('click', () => {
    refreshCurrentLog();
  });


  if (!window.notifierData || !notifierData.nonce || !notifierData.ajaxUrl) {
    console.error('Nedostaju AJAX podaci.');
    return;
  }

  // UČITAVANJE ARHIVIRANOG LOGA
  loadBtn?.addEventListener('click', () => {
    const file = select.value;
    if (!file) return alert('Odaberi log datoteku.');

    fetch(notifierData.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'load_archived_log',
        file: file,
        _ajax_nonce: notifierData.nonce
      })
    })
    .then(res => res.text())
    .then(data => {
      output.textContent = data || '(Log datoteka je prazna ili nije dostupna)';
    })
    .catch(err => {
      console.error(err);
      output.textContent = 'Greška prilikom učitavanja loga.';
    });
  });

  // BRISANJE ARHIVIRANOG LOGA
  deleteBtn?.addEventListener('click', () => {
    const file = select.value;
    if (!file) return alert('Odaberi log datoteku za brisanje.');
    if (!confirm('Jeste li sigurni da želite obrisati ovu log datoteku?')) return;

    fetch(notifierData.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'delete_archived_log',
        file: file,
        _ajax_nonce: notifierData.nonce
      })
    })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
        output.textContent = 'Log datoteka obrisana.';
        select.querySelector(`option[value="${file}"]`)?.remove();
      } else {
        output.textContent = 'Greška prilikom brisanja.';
      }
    })
    .catch(err => {
      console.error(err);
      output.textContent = 'Greška prilikom slanja zahtjeva.';
    });
  });

  // BRISANJE TRENUTNOG (AKTIVNOG) LOGA
  deleteCurrentBtn?.addEventListener('click', () => {
    if (!confirm('Jeste li sigurni da želite obrisati trenutni log?')) return;

    fetch(notifierData.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'delete_current_log',
        _ajax_nonce: notifierData.nonce
      })
    })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
        output.textContent = 'Trenutni log je obrisan.';
        refreshCurrentLog(); // osvježi prikaz!
      } else {
        output.textContent = response.data?.message || 'Greška prilikom brisanja trenutnog loga.';
      }
    })
    .catch(err => {
      console.error(err);
      output.textContent = 'Greška prilikom slanja zahtjeva.';
    });
  });
});

function refreshCurrentLog() {

    fetch(notifierData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'get_current_log',
            _ajax_nonce: notifierData.nonce
        }),
    })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
            const output = document.querySelector('#log-output');
            if (output) {
                output.innerText = response.data.log;
            }
        } else {
            alert('Neuspješno dohvaćanje loga.');
        }
    });
}
